<?php

namespace Database\Seeders;

use App\Models\CuentaContable;
use App\Models\User;
use Illuminate\Database\Seeder;

class CuentasContablesPucSeeder extends Seeder
{
    /**
     * Seed a basic PUC chart for every company user.
     */
    public function run(): void
    {
        $cuentas = $this->cuentasBase();

        User::query()
            ->where('tipo_usuario', 'empresa')
            ->get()
            ->each(function (User $empresa) use ($cuentas): void {
                foreach ($cuentas as $cuenta) {
                    CuentaContable::firstOrCreate(
                        [
                            'empresa_id' => $empresa->id,
                            'codigo' => $cuenta['codigo'],
                        ],
                        [
                            'nombre' => $cuenta['nombre'],
                            'tipo' => $cuenta['tipo'],
                            'categoria' => $cuenta['categoria'],
                            'activo' => true,
                        ]
                    );
                }
            });
    }

    protected function cuentasBase(): array
    {
        return [
            ['codigo' => '1', 'nombre' => 'Activo', 'tipo' => 'grupo', 'categoria' => 'activo'],
            ['codigo' => '11', 'nombre' => 'Disponible', 'tipo' => 'grupo', 'categoria' => 'activo'],
            ['codigo' => '1105', 'nombre' => 'Caja', 'tipo' => 'cuenta', 'categoria' => 'activo'],
            ['codigo' => '1110', 'nombre' => 'Bancos', 'tipo' => 'cuenta', 'categoria' => 'activo'],
            ['codigo' => '13', 'nombre' => 'Deudores comerciales y otras cuentas por cobrar', 'tipo' => 'grupo', 'categoria' => 'activo'],
            ['codigo' => '1305', 'nombre' => 'Clientes', 'tipo' => 'cuenta', 'categoria' => 'activo'],
            ['codigo' => '14', 'nombre' => 'Inventarios', 'tipo' => 'grupo', 'categoria' => 'activo'],
            ['codigo' => '15', 'nombre' => 'Propiedad, planta y equipo', 'tipo' => 'grupo', 'categoria' => 'activo'],
            ['codigo' => '2', 'nombre' => 'Pasivo', 'tipo' => 'grupo', 'categoria' => 'pasivo'],
            ['codigo' => '21', 'nombre' => 'Obligaciones financieras', 'tipo' => 'grupo', 'categoria' => 'pasivo'],
            ['codigo' => '22', 'nombre' => 'Proveedores', 'tipo' => 'grupo', 'categoria' => 'pasivo'],
            ['codigo' => '23', 'nombre' => 'Cuentas por pagar', 'tipo' => 'grupo', 'categoria' => 'pasivo'],
            ['codigo' => '24', 'nombre' => 'Impuestos, gravámenes y tasas', 'tipo' => 'grupo', 'categoria' => 'pasivo'],
            ['codigo' => '25', 'nombre' => 'Obligaciones laborales', 'tipo' => 'grupo', 'categoria' => 'pasivo'],
            ['codigo' => '3', 'nombre' => 'Patrimonio', 'tipo' => 'grupo', 'categoria' => 'patrimonio'],
            ['codigo' => '31', 'nombre' => 'Capital social', 'tipo' => 'grupo', 'categoria' => 'patrimonio'],
            ['codigo' => '33', 'nombre' => 'Reservas', 'tipo' => 'grupo', 'categoria' => 'patrimonio'],
            ['codigo' => '36', 'nombre' => 'Resultados del ejercicio', 'tipo' => 'grupo', 'categoria' => 'patrimonio'],
            ['codigo' => '4', 'nombre' => 'Ingresos', 'tipo' => 'grupo', 'categoria' => 'ingresos'],
            ['codigo' => '41', 'nombre' => 'Ingresos operacionales', 'tipo' => 'grupo', 'categoria' => 'ingresos'],
            ['codigo' => '4135', 'nombre' => 'Comercio al por mayor y al por menor', 'tipo' => 'cuenta', 'categoria' => 'ingresos'],
            ['codigo' => '42', 'nombre' => 'Ingresos no operacionales', 'tipo' => 'grupo', 'categoria' => 'ingresos'],
            ['codigo' => '5', 'nombre' => 'Gastos', 'tipo' => 'grupo', 'categoria' => 'gastos'],
            ['codigo' => '51', 'nombre' => 'Gastos operacionales de administración', 'tipo' => 'grupo', 'categoria' => 'gastos'],
            ['codigo' => '52', 'nombre' => 'Gastos operacionales de ventas', 'tipo' => 'grupo', 'categoria' => 'gastos'],
            ['codigo' => '53', 'nombre' => 'Gastos no operacionales', 'tipo' => 'grupo', 'categoria' => 'gastos'],
            ['codigo' => '6', 'nombre' => 'Costo de ventas', 'tipo' => 'grupo', 'categoria' => 'costo'],
            ['codigo' => '61', 'nombre' => 'Costo de ventas', 'tipo' => 'grupo', 'categoria' => 'costo'],
        ];
    }
}
