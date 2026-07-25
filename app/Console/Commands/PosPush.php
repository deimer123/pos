<?php

namespace App\Console\Commands;

use App\Services\Turion\ConectividadDroplet;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Sube al droplet lo que esta terminal de Turion hizo sin conexion (boton
 * "Subir"): recorre pending_sync_operations en el orden en que se crearon
 * y llama al endpoint de PosSyncController que corresponda segun el tipo,
 * usando el token de Sanctum guardado en sync_state al emparejar.
 *
 * Las ordenes de taller y reservas de hotel creadas offline solo existen
 * localmente hasta que se suben -- "taller_item"/"taller_facturar" (y sus
 * equivalentes de hotel) referencian el taller_orden_id/hotel_reserva_id
 * LOCAL, asi que antes de subirlos se resuelve cual fue el id real que el
 * servidor le asigno a esa orden/reserva cuando se subio su "_crear".
 */
class PosPush extends Command
{
    protected $signature = 'pos:push';

    protected $description = 'Sube al droplet las ventas, mesas, ordenes de taller y reservas de hotel pendientes.';

    private array $tallerIdMap = [];
    private array $hotelIdMap = [];

    public function handle(): int
    {
        $syncState = DB::table('sync_state')->first();

        if (! $syncState || ! $syncState->terminal_token) {
            $this->error('Esta terminal todavia no esta emparejada.');

            return self::FAILURE;
        }

        $operaciones = DB::table('pending_sync_operations')
            ->where('estado', 'pendiente')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        if ($operaciones->isEmpty()) {
            $this->info('No hay nada pendiente por subir.');

            return self::SUCCESS;
        }

        $this->cargarMapasYaSincronizados();

        $subidas = 0;
        $errores = 0;

        foreach ($operaciones as $operacion) {
            $payload = json_decode($operacion->payload, true) ?? [];

            try {
                $resultado = $this->subirOperacion($operacion->tipo, $payload);

                DB::table('pending_sync_operations')->where('id', $operacion->id)->update([
                    'estado' => 'sincronizado',
                    'resultado_id' => $resultado['id'] ?? null,
                    'sincronizado_at' => now(),
                    'error' => null,
                    'updated_at' => now(),
                ]);

                if ($operacion->tipo === 'taller_crear' && isset($payload['local_id'], $resultado['id'])) {
                    $this->tallerIdMap[$payload['local_id']] = $resultado['id'];
                }

                if ($operacion->tipo === 'hotel_crear' && isset($payload['local_id'], $resultado['id'])) {
                    $this->hotelIdMap[$payload['local_id']] = $resultado['id'];
                }

                $subidas++;
            } catch (\Throwable $e) {
                DB::table('pending_sync_operations')->where('id', $operacion->id)->update([
                    'estado' => 'error',
                    'error' => $e->getMessage(),
                    'updated_at' => now(),
                ]);

                $errores++;
                $this->warn("Operacion #{$operacion->id} ({$operacion->tipo}) fallo: ".$e->getMessage());
            }
        }

        DB::table('sync_state')->update(['ultima_subida_at' => now()]);

        $this->info("Subida terminada: {$subidas} operacion(es) sincronizada(s), {$errores} con error.");

        return $errores > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Si una corrida anterior ya subio con exito algunas "_crear" (pero se
     * quedaron otras operaciones pendientes por un corte de conexion a
     * mitad de camino), esos mapeos local->servidor tambien deben quedar
     * disponibles ahora, no solo los de esta misma corrida.
     */
    private function cargarMapasYaSincronizados(): void
    {
        DB::table('pending_sync_operations')
            ->whereIn('tipo', ['taller_crear', 'hotel_crear'])
            ->where('estado', 'sincronizado')
            ->get()
            ->each(function ($op) {
                $payload = json_decode($op->payload, true) ?? [];

                if (! isset($payload['local_id']) || ! $op->resultado_id) {
                    return;
                }

                if ($op->tipo === 'taller_crear') {
                    $this->tallerIdMap[$payload['local_id']] = $op->resultado_id;
                } else {
                    $this->hotelIdMap[$payload['local_id']] = $op->resultado_id;
                }
            });
    }

    private function subirOperacion(string $tipo, array $payload): array
    {
        [$ruta, $body] = match ($tipo) {
            'venta' => ['/api/pairing/subir/venta', $payload],
            'mesa_item' => ['/api/pairing/subir/mesa/item', $payload],
            'mesa_facturar' => ['/api/pairing/subir/mesa/facturar', $payload],
            'taller_crear' => ['/api/pairing/subir/taller/crear', $payload],
            'taller_item' => ['/api/pairing/subir/taller/item', $this->conTallerOrdenIdResuelto($payload)],
            'taller_facturar' => ['/api/pairing/subir/taller/facturar', $this->conTallerOrdenIdResuelto($payload)],
            'hotel_crear' => ['/api/pairing/subir/hotel/crear', $payload],
            'hotel_item' => ['/api/pairing/subir/hotel/item', $this->conHotelReservaIdResuelto($payload)],
            'hotel_facturar' => ['/api/pairing/subir/hotel/facturar', $this->conHotelReservaIdResuelto($payload)],
            default => throw new \RuntimeException("Tipo de operacion desconocido: {$tipo}"),
        };

        return ConectividadDroplet::llamar($ruta, $body);
    }

    private function conTallerOrdenIdResuelto(array $payload): array
    {
        $localId = $payload['taller_orden_id'];

        if (! isset($this->tallerIdMap[$localId])) {
            throw new \RuntimeException("Todavia no se ha subido la orden de taller local #{$localId}.");
        }

        $payload['taller_orden_id'] = $this->tallerIdMap[$localId];

        return $payload;
    }

    private function conHotelReservaIdResuelto(array $payload): array
    {
        $localId = $payload['hotel_reserva_id'];

        if (! isset($this->hotelIdMap[$localId])) {
            throw new \RuntimeException("Todavia no se ha subido la reserva de hotel local #{$localId}.");
        }

        $payload['hotel_reserva_id'] = $this->hotelIdMap[$localId];

        return $payload;
    }
}
