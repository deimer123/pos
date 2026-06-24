<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductCombo extends Model
{
    protected $table = 'product_combos';

    protected $fillable = [
        'empresa_id',
        'product_id',
        'nombre',
        'cantidad_minima',
        'precio_combo',
        'activo',
    ];

    protected $casts = [
        'cantidad_minima' => 'decimal:2',
        'precio_combo'    => 'decimal:2',
        'activo'          => 'boolean',
    ];

    public function producto()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
