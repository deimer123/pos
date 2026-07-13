<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Catalogo extends Model
{
    protected $table = 'catalogos';

    protected $fillable = [
        'empresa_id',
        'nombre',
        'slug',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function empresa()
    {
        return $this->belongsTo(User::class, 'empresa_id');
    }

    public function productos()
    {
        return $this->belongsToMany(Product::class, 'catalogo_producto', 'catalogo_id', 'product_id');
    }
}
