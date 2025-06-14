<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Ciudad;
use App\Models\Departamento;

class FixCiudadesDepartamentoSeeder extends Seeder
{
    public function run(): void
    {
        $ciudades = Ciudad::all();

        foreach ($ciudades as $ciudad) {
            // Busca el departamento correcto por el código_dian
            $departamento = Departamento::where('codigo_dian', $ciudad->departamento_id)->first();

            if ($departamento) {
                $ciudad->departamento_id = $departamento->id;
                $ciudad->save();
            }
        }

        $this->command->info('✔ Ciudades actualizadas con departamento_id correcto.');
    }
}
