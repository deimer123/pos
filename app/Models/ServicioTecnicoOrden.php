<?php

namespace App\Models;

use App\Services\Turion\ColaSincronizacion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;

class ServicioTecnicoOrden extends Model
{
    protected $table = 'servicio_tecnico_ordenes';

    protected $fillable = [
        'empresa_id',
        'servidor_id',
        'numero_orden',
        'cliente_id',
        'cliente_nombre',
        'cliente_telefono',
        'marca',
        'modelo',
        'imei_serial',
        'color',
        'clave_desbloqueo',
        'diagnostico',
        'observaciones',
        'estado',
        'factura_id',
        'mesa_id',
        'creado_por',
        'fecha_entrega_estimada',
        'entregado_at',
        'fotos',
        'videos',
        'nota_trabajo',
    ];

    protected $casts = [
        'fecha_entrega_estimada' => 'datetime',
        'entregado_at'           => 'datetime',
        'fotos'                  => 'array',
        'videos'                 => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $orden) {
            if (! $orden->numero_orden) {
                $max = static::where('empresa_id', $orden->empresa_id)->max('numero_orden') ?? 0;
                $orden->numero_orden = $max + 1;
            }
        });

        // Solo tiene efecto en la base de datos local de Turion: una orden
        // de servicio tecnico abierta sin conexion se encola completa para
        // crearse en el servidor al pulsar "Subir" (ver
        // PosSyncController::servicioTecnicoCrear()).
        static::created(function (self $orden) {
            ColaSincronizacion::encolar('servicio_tecnico_crear', [
                'local_id' => $orden->id,
                'cliente_nombre' => $orden->cliente_nombre,
                'cliente_telefono' => $orden->cliente_telefono,
                'marca' => $orden->marca,
                'modelo' => $orden->modelo,
                'imei_serial' => $orden->imei_serial,
                'color' => $orden->color,
                'clave_desbloqueo' => $orden->clave_desbloqueo,
                'diagnostico' => $orden->diagnostico,
                'observaciones' => $orden->observaciones,
                'fecha_entrega_estimada' => $orden->fecha_entrega_estimada?->toIso8601String(),
            ]);
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(ServicioTecnicoItem::class, 'orden_id');
    }

    public function factura(): BelongsTo
    {
        return $this->belongsTo(Factura::class);
    }

    public function getTotalItemsAttribute(): float
    {
        return (float) $this->items->sum('subtotal');
    }

    // Normaliza el teléfono guardado (texto libre, sin formato fijo) al formato
    // que exige un link wa.me: solo dígitos, con indicativo de país. Si ya
    // trae 57 (u otro indicativo de más de 10 dígitos) se deja tal cual.
    public function getTelefonoWhatsappAttribute(): ?string
    {
        $digitos = preg_replace('/\D/', '', (string) $this->cliente_telefono);

        if (! $digitos) {
            return null;
        }

        if (strlen($digitos) === 10) {
            $digitos = '57' . $digitos;
        }

        return $digitos;
    }

    // Link público y firmado al PDF (válido 30 días), pensado para compartir
    // por WhatsApp.
    public function getLinkPdfPublicoAttribute(): string
    {
        return URL::temporarySignedRoute(
            'servicio-tecnico.orden.pdf.publico',
            now()->addDays(30),
            ['id' => $this->id]
        );
    }
}
