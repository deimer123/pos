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
        'tipo_seleccion',
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

    public function itemsProductos()
    {
        return $this->hasMany(CatalogoProducto::class, 'catalogo_id');
    }

    public function familias()
    {
        return $this->belongsToMany(Familia::class, 'catalogo_familia', 'catalogo_id', 'familia_id');
    }

    public function subfamilias()
    {
        return $this->belongsToMany(Subfamilia::class, 'catalogo_subfamilia', 'catalogo_id', 'subfamilia_id', 'id', 'id_familia2');
    }

    public function itemsFamilias()
    {
        return $this->hasMany(CatalogoFamilia::class, 'catalogo_id');
    }

    public function itemsSubfamilias()
    {
        return $this->hasMany(CatalogoSubfamilia::class, 'catalogo_id');
    }
}
