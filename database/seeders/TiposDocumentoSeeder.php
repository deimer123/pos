<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TiposDocumentoSeeder extends Seeder
{
    public function run()
    {
        $tipos = [
            ['id' => 1, 'nombre' => 'Registro civil'],
            ['id' => 2, 'nombre' => 'Tarjeta de identidad'],
            ['id' => 3, 'nombre' => 'Cédula de ciudadanía'],
            ['id' => 4, 'nombre' => 'Tarjeta de extranjería'],
            ['id' => 5, 'nombre' => 'Cédula de extranjería'],
            ['id' => 6, 'nombre' => 'NIT'],
            ['id' => 7, 'nombre' => 'Pasaporte'],
            ['id' => 8, 'nombre' => 'Documento de identificación extranjero'],
            ['id' => 9, 'nombre' => 'PEP'],
            ['id' => 10, 'nombre' => 'NIT otro país'],
            ['id' => 11, 'nombre' => 'NUIP'],
        ];

        DB::table('tipos_documento')->insert($tipos);
    }
}
