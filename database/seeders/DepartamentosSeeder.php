<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DepartamentosSeeder extends Seeder
{
    public function run(): void
    {
        $departamentos = [
            ['nombre' => 'ANTIOQUIA', 'codigo_dian' => 5],
            ['nombre' => 'ATLANTICO', 'codigo_dian' => 8],
            ['nombre' => 'CUNDINAMARCA', 'codigo_dian' => 11],
            ['nombre' => 'BOLIVAR', 'codigo_dian' => 13],
            ['nombre' => 'BOYACA', 'codigo_dian' => 15],
            ['nombre' => 'CALDAS', 'codigo_dian' => 17],
            ['nombre' => 'CAQUETA', 'codigo_dian' => 18],
            ['nombre' => 'CAUCA', 'codigo_dian' => 19],
            ['nombre' => 'CESAR', 'codigo_dian' => 20],
            ['nombre' => 'CORDOBA', 'codigo_dian' => 23],
            ['nombre' => 'CUNDINAMARCA', 'codigo_dian' => 25],
            ['nombre' => 'CHOCO', 'codigo_dian' => 27],
            ['nombre' => 'HUILA', 'codigo_dian' => 41],
            ['nombre' => 'GUAJIRA', 'codigo_dian' => 44],
            ['nombre' => 'MAGDALENA', 'codigo_dian' => 47],
            ['nombre' => 'META', 'codigo_dian' => 50],
            ['nombre' => 'NARIÑO', 'codigo_dian' => 52],
            ['nombre' => 'N DE SANTANDER', 'codigo_dian' => 54],
            ['nombre' => 'QUINDIO', 'codigo_dian' => 63],
            ['nombre' => 'RISARALDA', 'codigo_dian' => 66],
            ['nombre' => 'SANTANDER', 'codigo_dian' => 68],
            ['nombre' => 'SUCRE', 'codigo_dian' => 70],
            ['nombre' => 'TOLIMA', 'codigo_dian' => 73],
            ['nombre' => 'VALLE DEL CAUCA', 'codigo_dian' => 76],
            ['nombre' => 'ARAUCA', 'codigo_dian' => 81],
            ['nombre' => 'CASANARE', 'codigo_dian' => 85],
            ['nombre' => 'PUTUMAYO', 'codigo_dian' => 86],
            ['nombre' => 'SAN ANDRES', 'codigo_dian' => 88],
            ['nombre' => 'AMAZONAS', 'codigo_dian' => 91],
            ['nombre' => 'GUAINIA', 'codigo_dian' => 94],
            ['nombre' => 'GUAVIARE', 'codigo_dian' => 95],
            ['nombre' => 'VAUPES', 'codigo_dian' => 97],
            ['nombre' => 'VICHADA', 'codigo_dian' => 99],
        ];

        DB::table('departamentos')->insert($departamentos);
    }
}
