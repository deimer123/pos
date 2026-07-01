<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TallerOrden extends Model
{
    protected $table = 'taller_ordenes';

    protected $fillable = [
        'empresa_id',
        'numero_orden',
        'cliente_id',
        'cliente_nombre',
        'cliente_telefono',
        'placa',
        'marca',
        'modelo',
        'color',
        'km_ingreso',
        'diagnostico',
        'observaciones',
        'estado',
        'factura_id',
        'mesa_id',
        'creado_por',
        'fecha_entrega_estimada',
        'entregado_at',
    ];

    protected $casts = [
        'km_ingreso'             => 'integer',
        'fecha_entrega_estimada' => 'datetime',
        'entregado_at'           => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $orden) {
            if (! $orden->numero_orden) {
                $max = static::where('empresa_id', $orden->empresa_id)->max('numero_orden') ?? 0;
                $orden->numero_orden = $max + 1;
            }
        });
    }

    public function repuestos(): HasMany
    {
        return $this->hasMany(TallerRepuesto::class, 'orden_id');
    }

    public function factura(): BelongsTo
    {
        return $this->belongsTo(Factura::class);
    }

    public function getTotalRepuestosAttribute(): float
    {
        return (float) $this->repuestos->sum('subtotal');
    }
}
