<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Gasto extends Model
{
    protected $table = 'gastos';

    protected $fillable = [

        'id_gasto',
        'empresa_id',
        'tipo',

        'categoria',
        'descripcion',

        'monto',
        'fecha',

        'metodo_pago',
        'observacion',

        'created_by',
        'caja_id',
    ];

    protected $casts = [

        'fecha' => 'date',
        'monto' => 'decimal:2',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELACIONES
    |--------------------------------------------------------------------------
    */

    public function empresa()
    {
        return $this->belongsTo(User::class, 'empresa_id');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function caja()
    {
        return $this->belongsTo(Caja::class, 'caja_id');
    }

    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */

    public function scopeEmpresa($query, $empresaId)
    {
        return $query->where('empresa_id', $empresaId);
    }
}
