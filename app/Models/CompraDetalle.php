<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompraDetalle extends Model
{
    use HasFactory;

    protected $table = 'compra_detalles'; // importante
    protected $fillable = [
        'compra_id',
        'product_id',
        'codigo_ingresado',
        'nombre_producto',
        'cantidad',
        'costo_unitario',
        'desc_comercial',
        'descuento_pct',
        'iva_pct',
        'precio_venta_act',
        'utilidad_pct',
        'precio_venta',
        'subtotal',
        'impuesto',
        'total',
    ];

    public function compra()
    {
        return $this->belongsTo(Compra::class, 'compra_id');
    }

    public function producto()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function getCostoConDescuentoAttribute(): float
{
    // Si existe desc_comercial, aplica el descuento al costo unitario
    $desc = $this->desc_comercial ?? $this->descuento_pct ?? 0;

    $precioBase = $this->costo_unitario;

    // Descuento comercial expresado en porcentaje
    if ($desc > 0) {
        $precioBase -= ($precioBase * ($desc / 100));
    }

    return round($precioBase, 2);
}

}
