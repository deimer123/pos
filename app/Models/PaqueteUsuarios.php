<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaqueteUsuarios extends Model
{
    protected $table = 'paquetes_usuarios';

    protected $fillable = [
        'nombre',
        'usuarios_adicionales',
        'precio',
        'activo',
        'orden',
    ];

    protected $casts = [
        'precio' => 'decimal:2',
        'activo' => 'boolean',
    ];
}
