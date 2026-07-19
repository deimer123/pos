<?php

namespace App\Services\Turion;

use Illuminate\Support\Facades\DB;

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
                foreach (self::TABLAS as $tabla) {
                    if (! isset($catalogo[$tabla])) {
                        continue;
                    }

                    DB::table($tabla)->delete();

                    foreach (array_chunk($catalogo[$tabla], 200) as $lote) {
                        if (empty($lote)) {
                            continue;
                        }

                        DB::table($tabla)->insert(array_map(fn ($fila) => (array) $fila, $lote));
                    }
                }
            });
        } finally {
            if (DB::getDriverName() === 'sqlite') {
                DB::statement('PRAGMA foreign_keys = ON');
            }
        }
    }
}
