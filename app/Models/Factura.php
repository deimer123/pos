<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Factura extends Model
{
    protected $fillable = [
        'empresa_id',
        'cliente_id',
        'user_id',              // ← vendedor / admin que creó la factura
        'vendedor_id',          // ← vendedor asignado a la factura
        'cajero_id',            // ← cajero que registró el pago (si es diferente al vendedor)      
        'tipo_factura',         // 'salida' | 'electronica'
        'factus_reference_code',
        'factus_bill_id',
        'factus_number',
        'factus_cufe',
        'factus_status',
        'factus_response',
        'factus_validated_at',
        'tipo_pago',            // 'contado' | 'credito'
        'medio_pago',           // 'efectivo' | 'transferencia' | 'otro'
        'fecha',
        'fecha_compra',
        'fecha_pago',
        'fecha_vencimiento',
        'total',
        'saldo',
        'estado_pago',          // 'pendiente' | 'parcial' | 'pagada' | 'vencida'
        'observaciones',
        'devuelta_total',
        'transferencia_obs',
    ];

    protected $casts = [
        'fecha'             => 'datetime',
        'fecha_compra'      => 'datetime',
        'fecha_pago'        => 'datetime',
        'fecha_vencimiento' => 'date',
        'total'             => 'decimal:2',
        'saldo'             => 'decimal:2',
        'devuelta_total'    => 'bool',
        'factus_response'   => 'array',
        'factus_validated_at' => 'datetime',
    ];

    #-------------------------------------------------
    # Relaciones
    #-------------------------------------------------
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(User::class, 'empresa_id');
    }

    public function cliente()
{
    return $this->belongsTo(Actor::class, 'cliente_id', 'id');
}

public function configuracionEmpresa()
{
    return $this->hasOne(\App\Models\ConfiguracionEmpresa::class, 'empresa_id', 'empresa_id');
}

    public function vendedor(): BelongsTo
    {
        // Usuario (vendedor/admin) que creó la factura
        return $this->belongsTo(User::class, 'user_id');
    }

    public function vendedorAsignado(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendedor_id');
    }

    public function cajero(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cajero_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(FacturaDetalle::class);
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(FacturaPago::class);
    }

    #-------------------------------------------------
    # Helpers de dominio
    #-------------------------------------------------
    public function agregarDetalle(array $data): FacturaDetalle
    {
        // data: ['producto_id','descripcion_larga','cantidad','precio','descuento']
        $cantidad = (float)($data['cantidad'] ?? 1);
        $precio   = (float)($data['precio'] ?? 0);
        $desc     = (float)($data['descuento'] ?? 0);

        $subtotal = round($cantidad * $precio, 2);

        return $this->detalles()->create([
            'producto_id'       => $data['producto_id'],
            'descripcion_larga' => $data['descripcion_larga'] ?? null,
            'cantidad'          => $cantidad,
            'precio'            => $precio,
            'subtotal'          => $subtotal,
            'descuento'         => $desc,
        ]);
    }

   public function recalcularTotales(): void
{
    // Totales de la factura
    $totalOriginal = (float) $this->detalles()->sum(\DB::raw('precio * cantidad'));
    $totalDevuelto = (float) $this->detalles()->sum(\DB::raw('precio * devuelto_cantidad'));
    $totalPagado   = (float) $this->pagos()->sum('monto');

    // Total neto a pagar
    $totalNeto = max(0.0, round($totalOriginal - $totalDevuelto, 2));
    $this->total = $totalOriginal;

    // Saldo = total neto - pagado
    $saldo = max(0.0, round($totalNeto - $totalPagado, 2));
    $this->saldo = $saldo;

    // Estado de la factura
    if ($saldo <= 0.0) {
        if ($totalPagado >= $totalNeto && !$this->devuelta_total && $totalNeto > 0) {
            // Totalmente pagada
            $this->estado_pago = 'pagada';
            $this->fecha_pago  = now();
        } elseif ($this->devuelta_total || $totalDevuelto >= $totalOriginal) {
            // Totalmente devuelta: el detalle queda marcado con devuelta_total.
            // estado_pago se conserva dentro de los valores permitidos por la BD.
            $this->estado_pago = 'pagada';
            $this->fecha_pago  = null;
        } else {
            // Parcial (caso raro)
            $this->estado_pago = 'parcial';
            $this->fecha_pago  = null;
        }
    } else {
        // Pendiente o parcial según pagos
        $this->estado_pago = $totalPagado > 0 ? 'parcial' : 'pendiente';

        // Solo marca vencida si es crédito y pasó la fecha de vencimiento
        if ($this->tipo_pago === 'credito' && !empty($this->fecha_vencimiento)) {
            $venc = $this->fecha_vencimiento instanceof \Carbon\Carbon
                ? $this->fecha_vencimiento->toDateString()
                : \Carbon\Carbon::parse($this->fecha_vencimiento)->toDateString();

            if (now()->toDateString() > $venc) {
                $this->estado_pago = 'vencida';
            }
        }

        // Si no está saldada, no fijes fecha de pago
        $this->fecha_pago = null;
    }

    $this->save();
}

public function registrarAbono(
    float $monto,
    string $medio = 'efectivo',
    ?string $nota = null,
    ?int $userId = null,
    ?string $transferenciaObs = null
): \App\Models\FacturaPago {
    $pago = $this->pagos()->create([
        'medio_pago'        => $medio,
        'vendedor_id'       => $this->vendedor_id,
        'cajero_id'         => $userId,
        'monto'             => round($monto, 2),
        'fecha'             => now(),
        'user_id'           => $userId,
        'nota'              => $nota,
        'transferencia_obs' => $transferenciaObs,
    ]);

    $this->recalcularTotales();

    return $pago;
}
    #-------------------------------------------------
    # Scopes útiles (opcionales)
    #-------------------------------------------------
    public function scopeCreditoPendiente($query)
    {
        return $query->where('tipo_pago', 'credito')
                     ->whereIn('estado_pago', ['pendiente', 'parcial', 'vencida']);
    }

    public function scopeDeEmpresa($query, int $empresaId)
    {
        return $query->where('empresa_id', $empresaId);
    }
    public function getNumeroVisualAttribute(): string
    {
        if ($this->tipo_factura === 'electronica' && filled($this->factus_number)) {
            return (string) $this->factus_number;
        }

        $numero = str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);

        return ($this->tipo_factura === 'salida' ? 'SAL-' : 'FAC-') . $numero;
    }
}
