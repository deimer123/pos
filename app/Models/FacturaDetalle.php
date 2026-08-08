<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacturaDetalle extends Model
{
    protected $table = 'factura_detalles';

    protected $fillable = [
        'factura_id','producto_id','producto_variante_id','producto_lote_id','descripcion_larga',
        'cantidad','precio','subtotal','descuento','devuelto_cantidad','mecanico_id','tipo_servicio','porcentaje_empresa',
        'tipo_servicio','porcentaje_empresa',
    ];

    protected $casts = [
        'cantidad'=>'decimal:2','precio'=>'decimal:2',
        'subtotal'=>'decimal:2','descuento'=>'decimal:2',
        'devuelto_cantidad' => 'decimal:2',
        'porcentaje_empresa' => 'decimal:2',
    ];

    public function getValorEmpresaAttribute(): float
    {
        if ($this->tipo_servicio !== 'propio') {
            return 0.0;
        }

        return round((float) $this->subtotal * ((float) $this->porcentaje_empresa / 100), 2);
    }

    public function getValorTerceroAttribute(): float
    {
        if (! $this->tipo_servicio) {
            return 0.0;
        }

        return round((float) $this->subtotal - $this->valor_empresa, 2);
    }

    public function factura(): BelongsTo
    {
        return $this->belongsTo(Factura::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'producto_id', 'id_producto');
    }

    public function mecanico(): BelongsTo
    {
        return $this->belongsTo(Mecanico::class);
    }

    public function lote(): BelongsTo
    {
        return $this->belongsTo(ProductoLote::class, 'producto_lote_id');
    }
}
