<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DevolucionCompra extends Model
{
    use HasFactory;

    protected $table = 'devolucion_compras';

    protected $fillable = [
        'compra_id',
        'empresa_id',
        'user_id',
        'tipo',              // total | parcial
        'motivo',
        'total',
        'nota_credito_id',   // opcional: relación con nota crédito generada
    ];

    protected $casts = [
        'total' => 'decimal:2',
    ];

    /* ============================================================
     | 🔹 RELACIONES
     |============================================================ */

    // 🧾 La compra a la que pertenece la devolución
    public function compra(): BelongsTo
    {
        return $this->belongsTo(Compra::class, 'compra_id');
    }

    // 📦 Detalles de los productos devueltos
    public function detalles(): HasMany
    {
        return $this->hasMany(DevolucionCompraDetalle::class, 'devolucion_compra_id');
    }

    // 👤 Usuario que registró la devolución
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // 🏢 Empresa a la que pertenece la devolución
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(User::class, 'empresa_id');
    }

    // 🧾 Relación opcional con la nota de crédito generada
    public function notaCredito(): BelongsTo
    {
        return $this->belongsTo(NotaCredito::class, 'nota_credito_id');
    }

    /* ============================================================
     | 🔹 MÉTODOS AUXILIARES
     |============================================================ */

    /**
     * 🔹 Calcula los totales de la devolución basándose en sus detalles.
     * Se puede usar antes o después de guardar la devolución.
     */
    public function recalcularTotales(): void
    {
        $subtotal = $this->detalles()->sum('subtotal_sin_impuesto');
        $impuesto = $this->detalles()->sum('valor_impuesto');
        $total = $subtotal + $impuesto;

        $this->updateQuietly([
            'total' => $total,
        ]);
    }

    /**
     * 🔹 Devuelve el total formateado en moneda COP.
     */
    public function getTotalFormateadoAttribute(): string
    {
        return '$' . number_format($this->total ?? 0, 0, ',', '.');
    }

    /**
     * 🔹 Devuelve un texto legible del tipo de devolución.
     */
    public function getTipoTextoAttribute(): string
    {
        return match ($this->tipo) {
            'total' => 'Devolución total',
            'parcial' => 'Devolución parcial',
            default => ucfirst($this->tipo ?? 'Desconocido'),
        };
    }
}
