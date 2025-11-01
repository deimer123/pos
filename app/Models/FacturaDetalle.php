<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacturaDetalle extends Model
{
    protected $table = 'factura_detalles';

    protected $fillable = [
        'factura_id','producto_id','descripcion_larga',
        'cantidad','precio','subtotal','descuento','devuelto_cantidad',
    ];

    protected $casts = [
        'cantidad'=>'decimal:2','precio'=>'decimal:2',
        'subtotal'=>'decimal:2','descuento'=>'decimal:2',
        'devuelto_cantidad' => 'decimal:2',
    ];

    public function factura(): BelongsTo
    {
        return $this->belongsTo(Factura::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'producto_id', 'id_producto');
    }
}
