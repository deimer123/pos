<?php

namespace App\Console\Commands;

use App\Models\Actor;
use App\Models\HotelReserva;
use App\Models\Prefactura;
use App\Models\TallerOrden;
use App\Services\Turion\ConectividadDroplet;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Sube al droplet lo que esta terminal de Turion hizo sin conexion (boton
 * "Subir"): recorre pending_sync_operations en el orden en que se crearon
 * y llama al endpoint de PosSyncController que corresponda segun el tipo,
 * usando el token de Sanctum guardado en sync_state al emparejar.
 *
 * Las ordenes de taller y reservas de hotel referencian el taller_orden_id/
 * hotel_reserva_id LOCAL, asi que antes de subir un item/facturar se
 * resuelve cual es su id real en el servidor -- leyendo directo la columna
 * servidor_id de la fila local (no solo de un "_crear" en esta misma
 * corrida): esa columna tambien queda poblada si la orden/reserva vino de
 * un "Sincronizar" (bajada del droplet, ver TallerSyncPull/HotelSyncPull)
 * en vez de haberse creado offline aqui.
 */
class PosPush extends Command
{
    protected $signature = 'pos:push';

    protected $description = 'Sube al droplet las ventas, mesas, ordenes de taller y reservas de hotel pendientes.';

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
                    // Se guarda en la fila local (no solo en memoria): sin
                    // esto, borrar o cancelar esta orden MAS TARDE (otra
                    // sesion, no esta misma corrida de "Subir") no tendria
                    // como saber cual es su id en el droplet hasta el
                    // proximo "Sincronizar".
                    DB::table('taller_ordenes')->where('id', $payload['local_id'])->update(['servidor_id' => $resultado['id']]);
                }

                if ($operacion->tipo === 'hotel_crear' && isset($payload['local_id'], $resultado['id'])) {
                    DB::table('hotel_reservas')->where('id', $payload['local_id'])->update(['servidor_id' => $resultado['id']]);
                }

                if ($operacion->tipo === 'actor_crear' && isset($payload['actor_local_id'], $resultado['id'])) {
                    // Guarda el id real del droplet en la fila local: a
                    // partir de ahora, el proximo "Sincronizar" (que
                    // reemplaza todo el catalogo de clientes) ya no
                    // necesita preservar este actor a mano -- ya tiene
                    // contraparte real alla.
                    Actor::where('id', $payload['actor_local_id'])->update(['servidor_id' => $resultado['id']]);
                }

                if ($operacion->tipo === 'prefactura_guardar' && isset($payload['prefactura_local_id'])) {
                    // La prefactura ya se facturo (o se borro) directo en
                    // el droplet mientras esta terminal estaba
                    // desconectada -- se borra tambien aqui para no
                    // dejarla disponible para facturarla otra vez desde
                    // Turion.
                    if ($resultado['ya_facturada'] ?? false) {
                        $this->borrarPrefacturaLocal((int) $payload['prefactura_local_id']);
                    } elseif (isset($resultado['id'])) {
                        // Guarda el id real del droplet directo en la fila
                        // local: asi la proxima edicion sabe que ya tiene
                        // contraparte alla sin tener que escanear la cola.
                        Prefactura::where('id', $payload['prefactura_local_id'])
                            ->update(['servidor_id' => $resultado['id']]);
                    }
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
            'prefactura_guardar' => ['/api/pairing/subir/prefactura/guardar', $this->conPrefacturaServidorIdResuelto($payload)],
            'prefactura_borrar' => ['/api/pairing/subir/prefactura/borrar', $payload],
            'taller_borrar' => ['/api/pairing/subir/taller/borrar', $payload],
            'hotel_cancelar' => ['/api/pairing/subir/hotel/cancelar', $payload],
            'hotel_actualizar' => ['/api/pairing/subir/hotel/actualizar', $payload],
            'taller_actualizar' => ['/api/pairing/subir/taller/actualizar', $payload],
            'mesa_liberar' => ['/api/pairing/subir/mesa/liberar', $payload],
            'mesa_en_espera' => ['/api/pairing/subir/mesa/en-espera', $payload],
            'mesa_actualizar' => ['/api/pairing/subir/mesa/actualizar', $payload],
            'actor_crear' => ['/api/pairing/subir/actor/crear', $payload],
            default => throw new \RuntimeException("Tipo de operacion desconocido: {$tipo}"),
        };

        return ConectividadDroplet::llamar($ruta, $body);
    }

    private function conTallerOrdenIdResuelto(array $payload): array
    {
        $localId = $payload['taller_orden_id'];
        $servidorId = TallerOrden::find($localId)?->servidor_id;

        if (! $servidorId) {
            throw new \RuntimeException("Todavia no se ha subido la orden de taller local #{$localId}.");
        }

        $payload['taller_orden_id'] = $servidorId;

        return $payload;
    }

    private function conHotelReservaIdResuelto(array $payload): array
    {
        $localId = $payload['hotel_reserva_id'];
        $servidorId = HotelReserva::find($localId)?->servidor_id;

        if (! $servidorId) {
            throw new \RuntimeException("Todavia no se ha subido la reserva de hotel local #{$localId}.");
        }

        $payload['hotel_reserva_id'] = $servidorId;

        return $payload;
    }

    private function borrarPrefacturaLocal(int $localId): void
    {
        $prefactura = Prefactura::find($localId);

        if ($prefactura) {
            $prefactura->productos()->delete();
            $prefactura->delete();
        }
    }

    /**
     * A diferencia de taller/hotel (que exigen un "_crear" previo), una
     * prefactura no tiene un tipo de operacion separado para su primera
     * subida -- "prefactura_guardar" sirve tanto para crearla en el
     * droplet la primera vez como para actualizarla despues, o para bajar
     * una que se creo alla (ver PosSyncPrefacturas). Si la fila local ya
     * tiene servidor_id (porque se bajo del droplet, o porque una subida
     * anterior ya la creo alla), se manda para que el controlador la
     * actualice en vez de crear una segunda.
     */
    private function conPrefacturaServidorIdResuelto(array $payload): array
    {
        $servidorId = Prefactura::find($payload['prefactura_local_id'])?->servidor_id;

        if ($servidorId) {
            $payload['servidor_id'] = $servidorId;
        }

        return $payload;
    }
}
