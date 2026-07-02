<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            'super_admin',
            'admin_empresa',
            'vendedor',
            'digitador',
            'cajero',
            'mesero',
            'cocina',
            'repartidor',
            'taller',
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $this->command->info('✔ Roles creados o actualizados correctamente.');
    }
}
