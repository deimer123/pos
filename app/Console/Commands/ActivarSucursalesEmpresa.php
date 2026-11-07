<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Sucursales\ActivarSucursales;
use Illuminate\Console\Command;

class ActivarSucursalesEmpresa extends Command
{
    protected $signature = 'sucursales:activar
        {empresa : ID de la empresa (users.id)}
        {--dry-run : Solo muestra qué se haría, sin cambiar nada}
        {--force : No pide confirmación}';

    protected $description = 'Activa sucursales en una empresa: crea la sucursal Principal y le asigna lo que la empresa ya tenia, verificando los conteos.';

    public function handle(ActivarSucursales $servicio): int
    {
        $empresa = User::find($this->argument('empresa'));

        if (! $empresa || ! $empresa->esEmpresa()) {
            $this->error('No existe una empresa con ese ID.');

            return self::FAILURE;
        }

        $resumen = $servicio->resumen($empresa);

        $this->info("Empresa: {$empresa->name} (ID {$empresa->id})");

        if ($resumen['motivo']) {
            $this->error($resumen['motivo']);

            return self::FAILURE;
        }

        $this->line($resumen['ya_activa'] ? 'Ya tiene el interruptor encendido (se asignará lo que falte).' : 'Interruptor apagado.');
        $this->line($resumen['tiene_principal'] ? 'Ya existe la sucursal Principal.' : 'Se creará la sucursal Principal.');
        $this->line("Empleados a asignar a la Principal: {$resumen['empleados']}");

        $this->table(
            ['Tabla', 'Registros', 'Sin sucursal (se asignarán)'],
            collect($resumen['tablas'])
                ->map(fn (array $c, string $tabla) => [$tabla, $c['total'], $c['pendientes']])
                ->values()
                ->all(),
        );

        if ($this->option('dry-run')) {
            $this->warn('--dry-run: no se cambió nada.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('¿Activar sucursales con estos cambios? Se hace en una sola transacción.')) {
            $this->warn('Cancelado, no se cambió nada.');

            return self::SUCCESS;
        }

        try {
            $resultado = $servicio->activar($empresa);
        } catch (\Throwable $e) {
            $this->error('No se activó, la base quedó como estaba: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->info('Sucursales activadas. Sucursal Principal ID ' . $resultado['sucursal_id'] . '.');
        $this->line('Registros asignados: ' . array_sum($resultado['asignadas']) . ' | Empleados asignados: ' . $resultado['empleados']);

        return self::SUCCESS;
    }
}
