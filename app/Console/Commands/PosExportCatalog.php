<?php

namespace App\Console\Commands;

use App\Services\Turion\CatalogoExporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Arma el paquete de datos que una terminal de Turion necesita para
 * emparejarse o refrescar su catalogo local: la empresa y sus empleados
 * (con contraseña ya hasheada, para poder loguearse sin internet), roles
 * y permisos, catalogo de productos completo (con codigos alternos,
 * combos, recetas y variantes), configuracion de la empresa, mesas,
 * habitaciones de hotel y mecanicos de taller.
 *
 * Uso manual/diagnostico -- el emparejamiento real de una terminal usa el
 * mismo paquete (via CatalogoExporter) a traves de PairingController.
 */
class PosExportCatalog extends Command
{
    protected $signature = 'pos:export-catalog
        {empresa : ID de la empresa (users.id con tipo_usuario=empresa)}
        {--output= : Ruta del archivo .json de salida (por defecto storage/app/pos-exports/empresa-{id}.json)}';

    protected $description = 'Arma el paquete JSON de catalogo/emparejamiento que Turion descarga para operar sin conexion.';

    public function handle(CatalogoExporter $exporter): int
    {
        $empresaId = (int) $this->argument('empresa');

        $empresa = DB::table('users')
            ->where('id', $empresaId)
            ->where('tipo_usuario', 'empresa')
            ->first();

        if (! $empresa) {
            $this->error("No existe una empresa con id {$empresaId} (tipo_usuario=empresa).");

            return self::FAILURE;
        }

        $paquete = $exporter->paraEmpresa($empresaId);

        $json = json_encode($paquete, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $output = $this->option('output') ?: storage_path("app/pos-exports/empresa-{$empresaId}.json");

        if (! is_dir(dirname($output))) {
            mkdir(dirname($output), 0755, true);
        }

        file_put_contents($output, $json);

        $this->info(sprintf(
            'Paquete exportado a %s (%s KB, %d productos, %d usuarios).',
            $output,
            number_format(strlen($json) / 1024, 1),
            count($paquete['products']),
            count($paquete['users']),
        ));

        return self::SUCCESS;
    }
}
