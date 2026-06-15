<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrdenMesaItem extends Model
{
    use HasFactory;

    protected $table = 'orden_mesa_items';

    protected $fillable = [
        'empresa_id',
        'orden_mesa_id',
        'product_id',
        'cantidad',
        'precio_unitario',
        'subtotal',
        'estado_cocina',
        'requiere_cocina',
        'nota_cocina',
        'enviado_cocina_en',
    ];

    protected $casts = [
        'cantidad' => 'decimal:3',
        'precio_unitario' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'requiere_cocina' => 'boolean',
        'enviado_cocina_en' => 'datetime',
    ];

    public function orden(): BelongsTo
    {
        return $this->belongsTo(OrdenMesa::class, 'orden_mesa_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
