<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotaCreditoItem extends Model
{
    protected $table = 'nota_credito_items';

    protected $fillable = [
        'nota_credito_id',
        'devolucion_detalle_id',
        'product_id',
        'cantidad',
        'costo_unitario',
        'valor_impuesto',
        'subtotal_sin_impuesto',
        'subtotal_con_impuesto',
    ];

    /* ----------------------------------------------
     |  RELACIONES
     |-----------------------------------------------*/

    // 🔹 Relación con nota crédito
    public function notaCredito()
    {
        return $this->belongsTo(NotaCredito::class, 'nota_credito_id');
    }

    // 🔹 Relación con detalle de devolución (opcional)
    public function devolucionDetalle()
    {
        return $this->belongsTo(DevolucionCompraDetalle::class, 'devolucion_detalle_id');
    }

    // 🔹 Relación con producto (opcional pero útil)
    public function producto()
    {
        return $this->belongsTo(Product::class, 'product_id', 'id_producto');
    }

    /* ----------------------------------------------
     |  MÉTODOS ÚTILES
     |-----------------------------------------------*/

    // 🔹 Total por item
    public function getTotalAttribute()
    {
        return $this->subtotal_con_impuesto ?? 0;
    }

    // 🔹 Nombre del producto (si existe)
    public function getNombreProductoAttribute()
    {
        return $this->producto->descripcion_larga
            ?? $this->producto->descripcion_corta
            ?? $this->product_id;
    }

    public function compra()
{
    return $this->belongsTo(\App\Models\Compra::class, 'compra_id');
}

public function proveedor()
{
    return $this->hasOneThrough(
        \App\Models\Actor::class,   // modelo proveedor
        \App\Models\Compra::class,  // tabla compras
        'id',                       // compras.id
        'id_clip_pro',              // actors.id_clip_pro
        'compra_id',                // nota_creditos.compra_id
        'proveedor_id'              // compras.proveedor_id
    );
}
}
