<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AjusteInventarioDetalle extends Model
{
    protected $table = 'ajuste_inventario_detalles';

    protected $fillable = [
        'ajuste_inventario_id', // 👈 OBLIGATORIO
        'producto_id',
        'producto_variante_id',
        'cantidad_anterior',
        'cantidad_nueva',
        'diferencia',
    ];

    public function ajuste()
    {
        return $this->belongsTo(AjusteInventario::class, 'ajuste_inventario_id');
    }

    public function producto()
{
    return $this->belongsTo(
        Product::class,
        'producto_id',
        'id_producto'
    );
}

public function variante()
{
    return $this->belongsTo(ProductoVariante::class, 'producto_variante_id');
}

}

