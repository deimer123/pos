<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductCombo extends Model
{
    protected $table = 'product_combos';

    protected $fillable = [
        'product_id',
        'empresa_id',
        'nombre',
        'cantidad_minima',
        'precio_unitario_combo',
        'activo',
    ];

    protected $casts = [
        'cantidad_minima'        => 'float',
        'precio_unitario_combo'  => 'float',
        'activo'                 => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(User::class, 'empresa_id');
    }
}
