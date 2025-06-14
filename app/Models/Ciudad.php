<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ciudad extends Model
{
    use HasFactory;

    protected $table = 'ciudades'; // 👈 Agrega esto

    protected $fillable = [
        'nombre',
        'codigo_dian',
        'departamento_id',
    ];

    public function departamento()
{
    return $this->belongsTo(Departamento::class);
}
}
