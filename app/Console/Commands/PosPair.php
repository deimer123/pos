<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Empareja esta terminal de Turion con una empresa del droplet: cambia un
 * codigo de un solo uso (generado desde Filament, ver EmparejarTerminal)
 * por un token de Sanctum permanente + el catalogo inicial completo, y
 * siembra ese catalogo en la base de datos local (SQLite).
 *
 * Solo tiene sentido correrlo contra la base de datos LOCAL de una
 * terminal de Turion, nunca contra el droplet.
 */
class PosPair extends Command
{
    protected $signature = 'pos:pair
        {codigo : Codigo de emparejamiento generado en el panel del negocio}
        {servidor : URL base del droplet (ej: https://159-89-81-81.sslip.io)}
        {--terminal= : Nombre para identificar esta terminal (por defecto el nombre de la maquina)}';

    protected $description = 'Empareja esta terminal de Turion con una empresa, descargando su catalogo inicial.';

    public function handle(): int
    {
        $servidor = rtrim($this->argument('servidor'), '/');
        $terminal = $this->option('terminal') ?: gethostname();

        $this->info('Contactando '.$servidor.'...');

        $respuesta = Http::timeout(30)->post($servidor.'/api/pairing/bootstrap', [
            'codigo' => $this->argument('codigo'),
            'terminal_nombre' => $terminal,
        ]);

        if (! $respuesta->successful()) {
            $this->error('No se pudo emparejar: '.($respuesta->json('message') ?? $respuesta->status()));

            return self::FAILURE;
        }

        $data = $respuesta->json();

        $this->info('Emparejado con: '.$data['empresa_nombre']);
        $this->info('Sembrando catálogo local...');

        $this->sembrarCatalogo($data['catalogo']);

        DB::table('sync_state')->delete();
        DB::table('sync_state')->insert([
            'empresa_id' => $data['empresa_id'],
            'empresa_nombre' => $data['empresa_nombre'],
            'terminal_token' => $data['token'],
            'servidor_url' => $servidor,
            'ultima_sincronizacion_at' => now(),
            'ultima_subida_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->info('Terminal emparejada y catálogo listo.');

        return self::SUCCESS;
    }

    private function sembrarCatalogo(array $catalogo): void
    {
        $tablas = [
            'users', 'roles', 'permissions', 'role_has_permissions',
            'model_has_roles', 'model_has_permissions',
            'configuracion_empresas', 'cuentas_contables', 'actors',
            'products', 'alternate_codes', 'product_combos',
            'producto_variantes', 'recetas', 'receta_items',
            'mesas', 'hotel_habitaciones', 'mecanicos',
        ];

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
            DB::transaction(function () use ($catalogo, $tablas) {
                foreach ($tablas as $tabla) {
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
