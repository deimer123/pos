<?php

namespace App\Services\Turion;

use App\Models\Actor;
use App\Models\Prefactura;
use App\Models\User;

/**
 * Fusiona en la base de datos LOCAL de Turion la lista de prefacturas
 * "borrador" que devuelve el droplet (ver PairingController::prefacturas()),
 * llamado al sincronizar (ver PosSyncCatalog).
 *
 * A diferencia del catalogo (que se reemplaza por completo), aqui NO se
 * puede borrar-y-recrear todo: una prefactura creada offline en esta misma
 * terminal (sin servidor_id todavia) tiene que quedar intacta hasta que se
 * suba con "Subir". Por eso la fusion es por servidor_id:
 *   - Prefactura remota sin fila local con ese servidor_id -> se crea.
 *   - Prefactura remota con fila local ya existente -> se actualiza.
 *   - Fila local CON servidor_id que ya no aparece en la lista remota ->
 *     se borra (significa que se facturo o se elimino directo en el
 *     droplet -- asi es como Turion se entera y no la deja facturar de
 *     nuevo).
 *   - Filas locales SIN servidor_id (creadas offline, aun no subidas) no
 *     se tocan nunca aqui.
 */
class PrefacturaSyncPull
{
    public static function fusionar(array $remotas, int $empresaId): void
    {
        $idsRemotos = collect($remotas)->pluck('id')->all();

        Prefactura::where('empresa_id', $empresaId)
            ->whereNotNull('servidor_id')
            ->whereNotIn('servidor_id', $idsRemotos)
            ->get()
            ->each(function (Prefactura $prefactura) {
                $prefactura->productos()->delete();
                $prefactura->delete();
            });

        foreach ($remotas as $remota) {
            $prefactura = Prefactura::where('empresa_id', $empresaId)
                ->where('servidor_id', $remota['id'])
                ->first() ?? new Prefactura(['empresa_id' => $empresaId]);

            $prefactura->fill([
                'empresa_id' => $empresaId,
                'servidor_id' => $remota['id'],
                'cliente_id' => self::idSiExisteLocal(Actor::class, $remota['cliente_id'] ?? null),
                'vendedor_id' => self::idSiExisteLocal(User::class, $remota['vendedor_id'] ?? null),
                'observaciones' => $remota['observaciones'] ?? '',
                'estado' => $remota['estado'] ?? 'borrador',
            ])->save();

            $prefactura->productos()->delete();

            foreach ($remota['items'] ?? [] as $item) {
                $cantidad = (float) $item['cantidad'];
                $precio = (float) $item['precio'];

                $prefactura->productos()->create([
                    'empresa_id' => $empresaId,
                    'producto_id' => is_numeric($item['producto_id']) ? (int) $item['producto_id'] : 0,
                    'descripcion_larga' => $item['nombre'],
                    'cantidad' => $cantidad,
                    'precio_unitario' => $precio,
                    'subtotal' => round($precio * $cantidad, 2),
                    'descuento' => (float) ($item['descuento'] ?? 0),
                ]);
            }
        }
    }

    /**
     * cliente_id/vendedor_id vienen de actors/users, que el catalogo ya
     * replica localmente con el mismo id que en el droplet -- pero si el
     * catalogo no se ha sincronizado recien y ese registro puntual no
     * existe todavia aqui, se guarda null en vez de romper el guardado
     * por la llave foranea (prefacturas.cliente_id/vendedor_id).
     */
    private static function idSiExisteLocal(string $modelo, ?int $id): ?int
    {
        if (! $id) {
            return null;
        }

        return $modelo::whereKey($id)->exists() ? $id : null;
    }
}
