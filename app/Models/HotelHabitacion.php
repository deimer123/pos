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
        'tiene_aire', 'tiene_ventilador', 'precios_por_persona',
        'activa', 'observaciones',
    ];

    protected $casts = [
        'camas_dobles'         => 'integer',
        'camas_sencillas'      => 'integer',
        'tiene_aire'           => 'boolean',
        'tiene_ventilador'     => 'boolean',
        'precios_por_persona'  => 'array',
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

    // Precio de la noche completa para una cantidad exacta de personas.
    // Cada habitación define su propio precio por número de ocupantes (no es
    // necesariamente un valor por persona multiplicado, ya que dos personas
    // no siempre cuestan el doble que una).
    public function precioParaPersonas(int $personas): float
    {
        $precios = $this->precios_por_persona ?? [];

        if (isset($precios[(string) $personas])) {
            return (float) $precios[(string) $personas];
        }

        // Si no hay un precio configurado exacto, usar el del mayor número
        // de personas configurado que sea menor o igual al solicitado.
        $disponibles = array_filter(
            array_keys($precios),
            fn ($n) => (int) $n <= $personas
        );

        if (empty($disponibles)) {
            return 0;
        }

        $masCercano = max(array_map('intval', $disponibles));

        return (float) $precios[(string) $masCercano];
    }

    public function getPrecioDesdeAttribute(): float
    {
        return $this->precioParaPersonas(1);
    }
}
