<?php

namespace App\Models;

use App\Services\Turion\ColaSincronizacion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HotelReserva extends Model
{
    protected $table = 'hotel_reservas';

    protected $fillable = [
        'empresa_id', 'servidor_id', 'habitacion_id', 'actor_id', 'numero_reserva',
        'huesped_nombre', 'huesped_telefono', 'huesped_documento', 'numero_personas',
        'climatizacion', 'fecha_checkin', 'fecha_checkout', 'checkin_real_at', 'checkout_real_at',
        'precio_noche', 'abono_monto', 'abono_medio_pago',
        'estado', 'factura_id', 'observaciones', 'creado_por',
    ];

    protected $casts = [
        'numero_personas'  => 'integer',
        'fecha_checkin'    => 'date',
        'fecha_checkout'   => 'date',
        'checkin_real_at'  => 'datetime',
        'checkout_real_at' => 'datetime',
        'precio_noche'     => 'float',
        'abono_monto'      => 'float',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $reserva) {
            if (! $reserva->numero_reserva) {
                $max = static::where('empresa_id', $reserva->empresa_id)->max('numero_reserva') ?? 0;
                $reserva->numero_reserva = $max + 1;
            }
        });

        // Solo tiene efecto en la base de datos local de Turion: una
        // reserva abierta sin conexion se encola completa para crearse en
        // el servidor al pulsar "Subir" (ver PosSyncController::hotelCrear()).
        static::created(function (self $reserva) {
            ColaSincronizacion::encolar('hotel_crear', [
                'local_id' => $reserva->id,
                'habitacion_id' => $reserva->habitacion_id,
                'huesped_nombre' => $reserva->huesped_nombre,
                'huesped_telefono' => $reserva->huesped_telefono,
                'huesped_documento' => $reserva->huesped_documento,
                'numero_personas' => $reserva->numero_personas,
                'fecha_checkin' => $reserva->fecha_checkin?->toDateString(),
                'fecha_checkout' => $reserva->fecha_checkout?->toDateString(),
                'precio_noche' => $reserva->precio_noche,
                'observaciones' => $reserva->observaciones,
                // 'reservada' (fecha futura, sin check-in todavia) vs
                // 'checkin' (el huesped llega de una vez) -- sin esto el
                // droplet siempre la creaba como 'checkin', mostrando la
                // habitacion ocupada aunque la reserva fuera para un dia
                // futuro.
                'estado' => $reserva->estado,
            ]);
        });
    }

    public function habitacion(): BelongsTo
    {
        return $this->belongsTo(HotelHabitacion::class, 'habitacion_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'actor_id');
    }

    public function factura(): BelongsTo
    {
        return $this->belongsTo(Factura::class);
    }

    public function consumos(): HasMany
    {
        return $this->hasMany(HotelReservaConsumo::class, 'reserva_id');
    }

    // Si hay fecha de salida definida, se calculan las noches por
    // diferencia de fechas. Si el huésped no sabe cuándo se va (fecha de
    // salida en blanco), se usa la hora de corte del hotel (por defecto
    // 2:00pm) para calcular cuántas noches lleva hasta ahora.
    public function getNumeroNochesAttribute(): int
    {
        if ($this->fecha_checkout) {
            return max(1, $this->fecha_checkin->diffInDays($this->fecha_checkout));
        }

        return $this->calcularNochesAbiertas();
    }

    public function calcularNochesAbiertas(?string $horaInicioDia = null): int
    {
        [$horas] = array_map('intval', explode(':', $horaInicioDia ?: '14:00:00'));

        $inicio = $this->checkin_real_at ?? now();
        $ahora  = now();

        $inicioAjustado = $inicio->copy()->subHours($horas)->startOfDay();
        $ahoraAjustado  = $ahora->copy()->subHours($horas)->startOfDay();

        return max(1, $inicioAjustado->diffInDays($ahoraAjustado) + 1);
    }

    public function getTotalEstimadoAttribute(): float
    {
        return round($this->precio_noche * $this->numero_noches, 2);
    }

    public function getTotalConsumosAttribute(): float
    {
        return round((float) $this->consumos()->sum('subtotal'), 2);
    }

    // Lo que falta por cobrar al hacer check-out: hospedaje + consumos
    // extra, menos el abono que ya se recibió al reservar.
    public function getSaldoPendienteAttribute(): float
    {
        return round($this->total_estimado + $this->total_consumos - $this->abono_monto, 2);
    }
}
