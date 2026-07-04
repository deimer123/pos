<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HotelHabitacion extends Model
{
    protected $table = 'hotel_habitaciones';

    protected $fillable = [
        'empresa_id', 'numero', 'camas_dobles', 'camas_sencillas',
        'tiene_aire', 'tiene_ventilador', 'precio_persona_noche',
        'estado', 'activa', 'observaciones',
    ];

    protected $casts = [
        'camas_dobles'         => 'integer',
        'camas_sencillas'      => 'integer',
        'tiene_aire'           => 'boolean',
        'tiene_ventilador'     => 'boolean',
        'precio_persona_noche' => 'float',
        'activa'               => 'boolean',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Empresa::class);
    }

    public function reservas(): HasMany
    {
        return $this->hasMany(HotelReserva::class, 'habitacion_id');
    }

    public function getCapacidadMaximaAttribute(): int
    {
        return ($this->camas_dobles * 2) + $this->camas_sencillas;
    }
}
