<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoDocumento extends Model
{
    protected $table = 'tipos_documento';
    public $incrementing = false; // Porque asignamos el ID manualmente

    protected $fillable = ['id', 'nombre'];
}

