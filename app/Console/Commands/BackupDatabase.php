<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class BackupDatabase extends Command
{
    protected $signature = 'app:backup-database';

    protected $description = 'Respaldo diario de la base de datos (mysqldump comprimido), guardado local y opcionalmente subido a S3.';

    // Cuantos respaldos locales conservar (uno por dia); los mas viejos se borran.
    private const RETENCION_LOCAL = 14;

    public function handle(): int
    {
        $connection = config('database.default');
        $config = config("database.connections.{$connection}");

        if (($config['driver'] ?? null) !== 'mysql') {
            $this->error("La conexion por defecto ({$connection}) no es mysql; este comando solo sabe respaldar mysql.");

            return self::FAILURE;
        }

        $nombreArchivo = 'backup-' . now()->format('Y-m-d_His') . '.sql.gz';
        $rutaLocal = storage_path('app/backups/' . $nombreArchivo);

        if (! is_dir(dirname($rutaLocal))) {
            mkdir(dirname($rutaLocal), 0755, true);
        }

        $comando = sprintf(
            'mysqldump --host=%s --port=%s --user=%s --single-transaction --quick --routines %s | gzip > %s',
            escapeshellarg($config['host']),
            escapeshellarg((string) $config['port']),
            escapeshellarg($config['username']),
            escapeshellarg($config['database']),
            escapeshellarg($rutaLocal)
        );

        $process = Process::fromShellCommandline($comando, null, [
            'MYSQL_PWD' => $config['password'],
        ]);
        $process->setTimeout(600);
        $process->run();

        if (! $process->isSuccessful() || ! file_exists($rutaLocal) || filesize($rutaLocal) === 0) {
            $this->error('El respaldo fallo: ' . $process->getErrorOutput());

            if (file_exists($rutaLocal)) {
                unlink($rutaLocal);
            }

            return self::FAILURE;
        }

        $this->info("Respaldo local creado: {$rutaLocal} (" . round(filesize($rutaLocal) / 1024, 1) . ' KB)');

        $this->subirAS3SiEstaConfigurado($rutaLocal, $nombreArchivo);
        $this->limpiarRespaldosViejos();

        return self::SUCCESS;
    }

    private function subirAS3SiEstaConfigurado(string $rutaLocal, string $nombreArchivo): void
    {
        if (! config('filesystems.disks.s3.key') || ! config('filesystems.disks.s3.bucket')) {
            $this->comment('S3 no configurado (AWS_ACCESS_KEY_ID/AWS_BUCKET vacios): el respaldo solo queda local en este servidor.');

            return;
        }

        try {
            Storage::disk('s3')->put('backups/' . $nombreArchivo, file_get_contents($rutaLocal));
            $this->info('Respaldo tambien subido a S3: backups/' . $nombreArchivo);
        } catch (\Throwable $e) {
            $this->error('No se pudo subir el respaldo a S3: ' . $e->getMessage());
        }
    }

    private function limpiarRespaldosViejos(): void
    {
        $archivos = collect(glob(storage_path('app/backups/backup-*.sql.gz')))
            ->sortByDesc(fn ($ruta) => filemtime($ruta))
            ->values();

        $archivos->slice(self::RETENCION_LOCAL)->each(function ($ruta) {
            unlink($ruta);
        });
    }
}
