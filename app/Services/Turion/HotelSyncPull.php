<?php

namespace App\Services\Turion;

use App\Models\Actor;
use App\Models\HotelHabitacion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Fusiona en la base de datos LOCAL de Turion la lista de reservas de hotel
 * activas que devuelve el droplet (ver PairingController::reservasHotel()),
 * llamado al sincronizar (ver PosSyncCatalog). Ver TallerSyncPull para el
 * porque de la fusion por servidor_id y de usar DB::table() en vez de
 * Eloquent (HotelReserva::booted() tambien encola "hotel_crear" en cada
 * creacion).
 */
class HotelSyncPull
{
    public static function fusionar(array $remotas, int $empresaId): void
    {
        $idsRemotos = collect($remotas)->pluck('id')->all();

        DB::table('hotel_reservas')
            ->where('empresa_id', $empresaId)
            ->whereNotNull('servidor_id')
            ->whereNotIn('servidor_id', $idsRemotos)
            ->get()
            ->each(function ($reserva) {
                DB::table('hotel_reserva_consumos')->where('reserva_id', $reserva->id)->delete();
                DB::table('hotel_reservas')->where('id', $reserva->id)->delete();
            });

        foreach ($remotas as $remota) {
            try {
                self::fusionarUna($remota, $empresaId);
            } catch (\Throwable $e) {
                Log::warning("HotelSyncPull: reserva remota #{$remota['id']} no se pudo fusionar: ".$e->getMessage());
            }
        }
    }

    private static function fusionarUna(array $remota, int $empresaId): void
    {
        // habitacion_id es obligatorio (FK NOT NULL) -- si el catalogo de
        // habitaciones todavia no se sincronizo (o la habitacion se borro),
        // no hay forma valida de guardar esta reserva localmente todavia;
        // se reintenta en el proximo "Sincronizar".
        if (! HotelHabitacion::whereKey($remota['habitacion_id'])->exists()) {
            throw new \RuntimeException("habitacion_id {$remota['habitacion_id']} no existe localmente todavia.");
        }

        $local = DB::table('hotel_reservas')
            ->where('empresa_id', $empresaId)
            ->where('servidor_id', $remota['id'])
            ->first();

        $datos = [
            'empresa_id' => $empresaId,
            'servidor_id' => $remota['id'],
            'habitacion_id' => $remota['habitacion_id'],
            'actor_id' => self::idSiExisteLocal(Actor::class, $remota['actor_id'] ?? null),
            'huesped_nombre' => $remota['huesped_nombre'],
            'huesped_telefono' => $remota['huesped_telefono'] ?? null,
            'huesped_documento' => $remota['huesped_documento'] ?? null,
            'numero_personas' => $remota['numero_personas'] ?? 1,
            'climatizacion' => $remota['climatizacion'] ?? null,
            'fecha_checkin' => $remota['fecha_checkin'],
            'fecha_checkout' => $remota['fecha_checkout'] ?? null,
            'checkin_real_at' => $remota['checkin_real_at'] ?? null,
            'precio_noche' => $remota['precio_noche'] ?? 0,
            'abono_monto' => $remota['abono_monto'] ?? 0,
            'abono_medio_pago' => $remota['abono_medio_pago'] ?? null,
            'estado' => $remota['estado'],
            'observaciones' => $remota['observaciones'] ?? null,
            'updated_at' => now(),
        ];

        if ($local) {
            $reservaId = $local->id;
            DB::table('hotel_reservas')->where('id', $reservaId)->update($datos);
        } else {
            // Igual que taller_ordenes.numero_orden: el numero_reserva
            // remoto puede colisionar con uno ya asignado localmente a una
            // reserva offline distinta -- se calcula uno propio.
            $datos['numero_reserva'] = (int) DB::table('hotel_reservas')->where('empresa_id', $empresaId)->max('numero_reserva') + 1;
            $datos['created_at'] = now();
            $reservaId = DB::table('hotel_reservas')->insertGetId($datos);
        }

        DB::table('hotel_reserva_consumos')->where('reserva_id', $reservaId)->delete();

        foreach ($remota['items'] ?? [] as $item) {
            $cantidad = (float) $item['cantidad'];
            $precio = (float) $item['precio'];

            DB::table('hotel_reserva_consumos')->insert([
                'reserva_id' => $reservaId,
                'producto_id' => is_numeric($item['producto_id'] ?? null) ? (int) $item['producto_id'] : null,
                'descripcion' => $item['nombre'],
                'cantidad' => $cantidad,
                'precio_unitario' => $precio,
                'subtotal' => round($precio * $cantidad, 2),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private static function idSiExisteLocal(string $modelo, ?int $id): ?int
    {
        if (! $id) {
            return null;
        }

        return $modelo::whereKey($id)->exists() ? $id : null;
    }
}
