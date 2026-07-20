<?php

namespace App\Services\Turion;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Siembra en la base de datos LOCAL (SQLite) el paquete de catalogo que
 * entrega el droplet (via PairingController::bootstrap()/refrescarCatalogo()).
 * Reemplaza el contenido completo de cada tabla -- la fuente de verdad del
 * catalogo es siempre el servidor. Usado tanto al emparejar por primera
 * vez ("pos:pair") como al pulsar "Sincronizar" ("pos:sync-catalog").
 */
class CatalogoImportador
{
    private const TABLAS = [
        'users', 'roles', 'permissions', 'role_has_permissions',
        'model_has_roles', 'model_has_permissions',
        'configuracion_empresas', 'cuentas_contables', 'actors',
        'products', 'alternate_codes', 'product_combos',
        'producto_variantes', 'recetas', 'receta_items',
        'mesas', 'hotel_habitaciones', 'mecanicos',
    ];

    // Estos campos de "users" son estado de la sesion/dispositivo ACTUAL de
    // esta terminal, no catalogo -- si se sobreescriben con lo que traiga el
    // droplet (que tiene su propio session_token de sus propias sesiones
    // web), la sesion activa local queda invalidada al instante y el
    // middleware de "una sola sesion" cierra al usuario que esta usando
    // Turion en ese momento.
    private const CAMPOS_SESION_LOCAL = [
        'session_token', 'active_tab_id', 'session_id',
        'last_login_at', 'last_login_ip', 'last_user_agent',
    ];

    public static function sembrar(array $catalogo): void
    {
        // Reemplazo completo de catalogo: no nos preocupa la integridad
        // referencial a mitad de camino (confiamos en los datos ya
        // consistentes que vienen del droplet). Ademas, un defecto de
        // esquema preexistente (prefactura_productos.producto_id referencia
        // products.id_producto, que no es su clave primaria real) hace que
        // SQLite valide -- y falle -- CUALQUIER DELETE sobre una tabla
        // referenciada transitivamente en cuanto las llaves foraneas estan
        // activas, aunque no toque esas filas.
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');
        }

        try {
            DB::transaction(function () use ($catalogo) {
                $sesionesLocales = self::capturarSesionesLocales();

                foreach (self::TABLAS as $tabla) {
                    if (! isset($catalogo[$tabla])) {
                        continue;
                    }

                    DB::table($tabla)->delete();

                    // El droplet puede tener columnas en produccion que no
                    // estan (o ya no estan) en nuestras migraciones -- ej.
                    // agregadas a mano en algun momento sin registrar una
                    // migracion. Se descartan al sembrar en vez de fallar,
                    // para que el catalogo local no dependa de que el
                    // esquema del droplet este perfectamente sincronizado
                    // con el historial de migraciones.
                    $columnasLocales = Schema::getColumnListing($tabla);

                    foreach (array_chunk($catalogo[$tabla], 200) as $lote) {
                        if (empty($lote)) {
                            continue;
                        }

                        $filas = array_map(
                            fn ($fila) => array_intersect_key((array) $fila, array_flip($columnasLocales)),
                            $lote
                        );

                        DB::table($tabla)->insert($filas);
                    }
                }

                self::restaurarSesionesLocales($sesionesLocales);
            });
        } finally {
            if (DB::getDriverName() === 'sqlite') {
                DB::statement('PRAGMA foreign_keys = ON');
            }
        }
    }

    private static function capturarSesionesLocales(): array
    {
        if (! Schema::hasTable('users')) {
            return [];
        }

        $columnas = array_intersect(self::CAMPOS_SESION_LOCAL, Schema::getColumnListing('users'));

        if (empty($columnas)) {
            return [];
        }

        return DB::table('users')
            ->get(array_merge(['id'], $columnas))
            ->keyBy('id')
            ->map(fn ($fila) => (array) $fila)
            ->all();
    }

    private static function restaurarSesionesLocales(array $sesionesLocales): void
    {
        foreach ($sesionesLocales as $userId => $valores) {
            unset($valores['id']);

            if (empty($valores) || ! DB::table('users')->where('id', $userId)->exists()) {
                continue;
            }

            DB::table('users')->where('id', $userId)->update($valores);
        }
    }
}
