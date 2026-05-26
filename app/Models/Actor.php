<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Actor extends Model
{
public bool $omitEmpresaAutoAssign = false;
    use HasFactory;
    protected $fillable = [
    'id_clip_pro',
    'empresa_id',
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
     'permite_credito',
    'dias_credito',
    'limite_credito',
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
protected static function booted()
{
    static::creating(function ($actor) {
        if (
            !property_exists($actor, 'omitEmpresaAutoAssign') || 
            !$actor->omitEmpresaAutoAssign
        ) {
            if (auth()->check() && empty($actor->empresa_id)) {
                $actor->empresa_id = auth()->user()->getEmpresaActualId();
            }
        }
    });
}

public function esCliente()
{
    return in_array($this->clasificacion, ['cliente', 'cliente_proveedor']);
}

public function esProveedor()
{
    return in_array($this->clasificacion, ['proveedor', 'cliente_proveedor']);
}

public function esClienteYProveedor()
{
    return $this->clasificacion === 'cliente_proveedor';
}

public function soloCliente()
{
    return $this->clasificacion === 'cliente';
}

public function soloProveedor()
{
    return $this->clasificacion === 'proveedor';
}

public function getRolesAttribute()
{
    return match($this->clasificacion) {
        'cliente' => 'Cliente',
        'proveedor' => 'Proveedor',
        'cliente_proveedor' => 'Cliente - Proveedor',
        default => 'Sin definir'
    };
}


}
