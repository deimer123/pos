<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Prefactura extends Model
{
    
    use HasFactory;

     protected $fillable = ['empresa_id','cliente_id', 'observaciones', 'estado'];

    public function productos()
    {
        return $this->hasMany(PrefacturaProducto::class);
    }
    

    public function cliente()
{
    return $this->belongsTo(Actor::class, 'cliente_id', 'id');
}

public function configuracionEmpresa()
{
    return $this->hasOne(\App\Models\ConfiguracionEmpresa::class, 'empresa_id', 'empresa_id');
}


}
