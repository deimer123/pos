<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Llamar a los seeders necesarios
        $this->call([
            TiposDocumentoSeeder::class,
            RolesSeeder::class,
            DepartamentosSeeder::class,
            CiudadesSeeder::class,
            CuentasContablesPucSeeder::class,
        ]);

        // Crear usuario Super Admin y asignar rol
        $user = User::factory()->create([
            'name' => 'Deimer Villamizar',
            'email' => 'admin@example.com',
            'password' => bcrypt('1234'),
        ]);

        $user->assignRole('super_admin');
    }
}
