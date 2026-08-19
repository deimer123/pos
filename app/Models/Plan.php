<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $table = 'planes';

    protected $fillable = [
        'nombre',
        'descripcion',
        'meses',
        'precio',
        'usuarios_incluidos',
        'recomendado',
        'activo',
        'orden',
    ];

    protected $casts = [
        'precio' => 'decimal:2',
        'recomendado' => 'boolean',
        'activo' => 'boolean',
    ];
}
