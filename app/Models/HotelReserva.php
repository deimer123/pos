<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelReserva extends Model
{
    protected $table = 'hotel_reservas';

    protected $fillable = [
        'empresa_id', 'habitacion_id', 'numero_reserva',
        'huesped_nombre', 'huesped_telefono', 'huesped_documento', 'numero_personas',
        'fecha_checkin', 'fecha_checkout', 'checkin_real_at', 'checkout_real_at',
        'precio_noche', 'estado', 'factura_id', 'observaciones', 'creado_por',
    ];

    protected $casts = [
        'numero_personas'  => 'integer',
        'fecha_checkin'    => 'date',
        'fecha_checkout'   => 'date',
        'checkin_real_at'  => 'datetime',
        'checkout_real_at' => 'datetime',
        'precio_noche'     => 'float',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $reserva) {
            if (! $reserva->numero_reserva) {
                $max = static::where('empresa_id', $reserva->empresa_id)->max('numero_reserva') ?? 0;
                $reserva->numero_reserva = $max + 1;
            }
        });
    }

    public function habitacion(): BelongsTo
    {
        return $this->belongsTo(HotelHabitacion::class, 'habitacion_id');
    }

    public function factura(): BelongsTo
    {
        return $this->belongsTo(Factura::class);
    }

    public function getNumeroNochesAttribute(): int
    {
        $noches = $this->fecha_checkin->diffInDays($this->fecha_checkout);

        return max(1, $noches);
    }

    public function getTotalEstimadoAttribute(): float
    {
        return round($this->precio_noche * $this->numero_noches, 2);
    }
}
