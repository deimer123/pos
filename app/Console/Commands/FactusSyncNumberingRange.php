<?php

namespace App\Console\Commands;

use App\Services\Factus\FactusClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class FactusSyncNumberingRange extends Command
{
    protected $signature = 'factus:sync-numbering-range
        {--document=21 : Codigo de documento Factus. 21 corresponde a Factura de Venta}
        {--use-first : Guarda en .env el primer rango activo encontrado}';

    protected $description = 'Consulta rangos de numeracion activos en Factus y opcionalmente guarda el primero en .env.';

    public function handle(FactusClient $factus): int
    {
        try {
            $response = $factus->numberingRanges([
                'document' => $this->option('document'),
                'is_active' => 1,
            ]);
        } catch (\Throwable $exception) {
            $this->error('No se pudieron consultar los rangos de numeracion en Factus.');
            $this->line($exception->getMessage());

            return self::FAILURE;
        }

        $ranges = $this->extractRanges($response);

        if ($ranges === []) {
            $this->warn('Factus no devolvio rangos activos para el documento solicitado.');

            return self::FAILURE;
        }

        $this->table(
            ['ID', 'Documento', 'Prefijo', 'Desde', 'Hasta', 'Actual', 'Resolucion', 'Activo', 'Vencido'],
            array_map(fn (array $range) => [
                $range['id'] ?? '',
                $range['document'] ?? '',
                $range['prefix'] ?? '',
                $range['from'] ?? '',
                $range['to'] ?? '',
                $range['current'] ?? '',
                $range['resolution_number'] ?? '',
                $range['is_active'] ?? '',
                $range['is_expired'] ?? '',
            ], $ranges)
        );

        if (! $this->option('use-first')) {
            $this->info('Para guardar el primero activo ejecuta el comando con --use-first.');

            return self::SUCCESS;
        }

        $selected = collect($ranges)
            ->first(fn (array $range) => (string) ($range['is_active'] ?? '1') === '1'
                && (string) ($range['is_expired'] ?? '0') === '0')
            ?? $ranges[0];

        $id = (string) ($selected['id'] ?? '');

        if ($id === '') {
            $this->error('El rango seleccionado no tiene ID.');

            return self::FAILURE;
        }

        $this->writeEnvValue('FACTUS_NUMBERING_RANGE_ID', $id);
        config(['services.factus.numbering_range_id' => $id]);

        $this->info("Rango Factus guardado en .env: {$id}");

        return self::SUCCESS;
    }

    private function extractRanges(array $response): array
    {
        $data = $response['data'] ?? $response;

        if (isset($data['data']) && is_array($data['data'])) {
            $data = $data['data'];
        }

        if (! is_array($data)) {
            return [];
        }

        return array_values(array_filter($data, 'is_array'));
    }

    private function writeEnvValue(string $key, string $value): void
    {
        $path = base_path('.env');
        $contents = File::exists($path) ? File::get($path) : '';
        $line = "{$key}={$value}";

        if (preg_match("/^{$key}=.*$/m", $contents)) {
            $contents = preg_replace("/^{$key}=.*$/m", $line, $contents);
        } else {
            $contents = rtrim($contents).PHP_EOL.$line.PHP_EOL;
        }

        File::put($path, $contents);
    }
}
