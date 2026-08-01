<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\LocalLicense\CodigoActivacion;
use App\Services\LocalLicense\FirmadorLicencia;
use DateTimeImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Version CLI, solo para probar el firmado end-to-end antes de tener la
 * pagina de Filament (ver app/Filament/Pages/EmitirLicenciaLocal.php, que
 * es la que de verdad se usa en produccion y ademas deja registro en
 * LicenciaLocal). Este comando NO persiste nada.
 */
class EmitirLicenciaLocal extends Command
{
    protected $signature = 'pos:licencia:emitir
        {empresa_id : ID del usuario empresa (tipo_usuario=empresa)}
        {machine_id : Fingerprint de la maquina del cliente, mostrado en su pantalla de activacion}';

    protected $description = 'Emite (firma) un codigo de activacion offline para la edicion Local, atado a una empresa y una maquina.';

    public function handle(FirmadorLicencia $firmador): int
    {
        $empresaId = (int) $this->argument('empresa_id');
        $machineId = trim((string) $this->argument('machine_id'));

        $empresa = User::where('tipo_usuario', 'empresa')->find($empresaId);

        if (! $empresa) {
            $this->error("No existe una empresa con id {$empresaId}.");

            return self::FAILURE;
        }

        $codigo = new CodigoActivacion(
            licenciaId: (string) Str::uuid(),
            empresaId: $empresa->id,
            empresaNombre: $empresa->name,
            machineId: $machineId,
            issuedAt: new DateTimeImmutable(),
        );

        $codigoFirmado = $firmador->firmar($codigo);

        $this->info('Codigo de activacion generado:');
        $this->newLine();
        $this->line($codigoFirmado);

        return self::SUCCESS;
    }
}
