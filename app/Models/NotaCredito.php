<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotaCredito extends Model
{
    protected $table = 'nota_creditos';

    protected $fillable = [
        'empresa_id',
        'compra_id',
        'user_id',
        'numero',
        'total',
        'motivo',
        'estado',
        'empresa_deudora',
        'fecha_cobro',
    ];

    /* ----------------------------------------------
     |  RELACIONES
     |-----------------------------------------------*/

    // 🔹 Relación con la compra
    public function compra()
    {
        return $this->belongsTo(Compra::class, 'compra_id');
    }

    // 🔹 Usuario que generó la nota crédito
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // 🔹 Items de la nota crédito
    public function items()
    {
        return $this->hasMany(NotaCreditoItem::class, 'nota_credito_id');
    }

    // 🔹 Relación con devolución (si quieres amarrarla)
    public function devolucion()
    {
        return $this->hasOne(DevolucionCompra::class, 'nota_credito_id');
    }

    // 🔹 Empresa (si usas users como empresa)
    public function empresa()
    {
        return $this->belongsTo(User::class, 'empresa_id');
    }

    /* ----------------------------------------------
     |  MÉTODOS ÚTILES
     |-----------------------------------------------*/

    // Saber si está cobrada
    public function estaCobrada(): bool
    {
        return $this->estado === 'cobrada';
    }

    // Saber si está pendiente
    public function estaPendiente(): bool
    {
        return $this->estado === 'pendiente';
    }

    // Saber si está anulada
    public function estaAnulada(): bool
    {
        return $this->estado === 'anulada';
    }

   public function proveedor(): BelongsTo
{
    return $this->belongsTo(Actor::class, 'proveedor_id', 'id_clip_pro');
}

public function getFacturaNumeroAttribute()
{
    return $this->compra?->numero_factura ?? 'SIN FACTURA';
}

public function getProveedorNombreAttribute()
{
    return $this->compra?->proveedor?->nombre ?? 'SIN PROVEEDOR';
}


}
