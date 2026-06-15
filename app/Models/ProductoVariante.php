<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductoVariante extends Model
{
    use HasFactory;

    protected $table = 'producto_variantes';

    protected $fillable = [
        'empresa_id',
        'product_id',
        'codigo',
        'nombre',
        'atributos',
        'peso',
        'precio_extra',
        'costo_extra',
        'stock',
        'activo',
    ];

    protected $casts = [
        'atributos' => 'array',
        'peso' => 'decimal:3',
        'precio_extra' => 'decimal:2',
        'costo_extra' => 'decimal:2',
        'stock' => 'decimal:3',
        'activo' => 'boolean',
    ];

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
