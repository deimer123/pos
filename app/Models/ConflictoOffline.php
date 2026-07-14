<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Operacion offline que fallo al sincronizar (ej. stock insuficiente
 * porque otra caja vendio lo mismo mientras ambas estaban sin internet).
 * Queda visible aqui para que un admin la revise a mano; el resto de la
 * cola sigue sincronizando normal.
 */
class ConflictoOffline extends Model
{
    protected $table = 'conflictos_offline';

    protected $fillable = [
        'uuid',
        'tipo',
        'empresa_id',
        'payload',
        'error',
        'estado',
        'resuelto_por',
        'resuelto_en',
    ];

    protected $casts = [
        'payload' => 'array',
        'resuelto_en' => 'datetime',
    ];
}
