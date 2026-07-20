<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Datos de referencia que hacen falta para operar el POS localmente (tipos
 * de documento, roles, departamentos/ciudades, plan de cuentas) pero que
 * NO son parte del catalogo de una empresa especifica (ver CatalogoExporter/
 * CatalogoImportador) -- se siembran una sola vez, en el primer arranque de
 * cada terminal de Turion (ver src-tauri/src/lib.rs).
 *
 * A diferencia de DatabaseSeeder (pensado para levantar un droplet nuevo de
 * desarrollo), este seeder NO crea ningun usuario -- los usuarios reales
 * llegan al emparejar la terminal con una empresa real.
 */
class TurionLocalSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            TiposDocumentoSeeder::class,
            RolesSeeder::class,
            DepartamentosSeeder::class,
            CiudadesSeeder::class,
            CuentasContablesPucSeeder::class,
        ]);
    }
}
