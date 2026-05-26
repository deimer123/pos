<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AjusteInventario extends Model
{
    protected $table = 'ajustes_inventario';

    protected $fillable = [
        'empresa_id',
        'usuario_id',
        'tipo',
        'observacion',
        'estado',
    ];

    public function detalles()
    {
        return $this->hasMany(AjusteInventarioDetalle::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
