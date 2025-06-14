<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Actor extends Model
{
    protected $primaryKey = 'id_clip_pro';
public $incrementing = false;
protected $keyType = 'int';

    use HasFactory;
    protected $fillable = [
    'id_clip_pro',
    'tipo',
    'tipo_persona',
    'tipo_documento_id',
    'identificacion',
    'nombre',
    'razon_social',
    'direccion',
    'telefono',
    'email',
    'clasificacion',
    'regimen_tributario',
    'responsable_iva',
    'departamento_id',
    'ciudad_id',
];

public function ciudad()
{
    return $this->belongsTo(Ciudad::class);
}

public function departamento()
{
    return $this->belongsTo(Departamento::class);
}

public function tipoDocumento()
{
    return $this->belongsTo(TipoDocumento::class, 'tipo_documento_id');
}


}

