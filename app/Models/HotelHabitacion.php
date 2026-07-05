<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HotelHabitacion extends Model
{
    protected $table = 'hotel_habitaciones';

    protected $fillable = [
        'empresa_id', 'numero', 'zona', 'camas_dobles', 'camas_sencillas',
        'tiene_aire', 'tiene_ventilador', 'precios_por_persona',
        'recargo_aire', 'recargo_ventilador', 'activa', 'observaciones',
    ];

    protected $casts = [
        'camas_dobles'         => 'integer',
        'camas_sencillas'      => 'integer',
        'tiene_aire'           => 'boolean',
        'tiene_ventilador'     => 'boolean',
        'precios_por_persona'  => 'array',
        'recargo_aire'         => 'float',
        'recargo_ventilador'   => 'float',
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

    // Precio total de la noche: base por ocupación + el recargo de aire O
    // ventilador que el huésped haya elegido para esa reserva (no ambos a la
    // vez, aunque la habitación tenga las dos opciones disponibles).
    public function precioNochePara(int $personas, ?string $climatizacion = null): float
    {
        $total = $this->precioParaPersonas($personas);

        if ($climatizacion === 'aire' && $this->tiene_aire) {
            $total += (float) $this->recargo_aire;
        } elseif ($climatizacion === 'ventilador' && $this->tiene_ventilador) {
            $total += (float) $this->recargo_ventilador;
        }

        return round($total, 2);
    }

    // Precio base de la habitación sola (1 persona, sin aire ni ventilador).
    public function getPrecioDesdeAttribute(): float
    {
        return $this->precioParaPersonas(1);
    }

    // Opciones de climatización que esta habitación realmente tiene
    // instaladas, para preguntarle al huésped cuál quiere usar (y cobrar solo
    // esa, no las dos).
    public function opcionesClimatizacion(): array
    {
        $opciones = ['ninguno' => 'Sin aire/ventilador'];

        if ($this->tiene_aire) {
            $opciones['aire'] = '❄️ Aire (+$' . number_format((float) $this->recargo_aire, 0, ',', '.') . ')';
        }

        if ($this->tiene_ventilador) {
            $opciones['ventilador'] = '🌀 Ventilador (+$' . number_format((float) $this->recargo_ventilador, 0, ',', '.') . ')';
        }

        return $opciones;
    }
}
