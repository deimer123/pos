<?php

namespace App\Console\Commands;

use App\Models\Factura;
use App\Services\Factus\FactusInvoiceService;
use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;

class FactusSendBill extends Command
{
    protected $signature = 'factus:send-bill
        {factura_id : ID local de la factura}
        {--dry-run : Solo muestra el payload sin enviarlo a Factus}';

    protected $description = 'Arma y opcionalmente envia una factura local al sandbox de Factus.';

    public function handle(FactusInvoiceService $factus): int
    {
        $factura = Factura::with(['cliente.ciudad', 'detalles'])->find($this->argument('factura_id'));

        if (! $factura) {
            $this->error('No se encontro la factura indicada.');

            return self::FAILURE;
        }

        try {
            $payload = $factus->payload($factura);
        } catch (\Throwable $exception) {
            $this->error('No se pudo armar el payload de Factus.');
            $this->line($exception->getMessage());

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        try {
            $response = $factus->validate($factura);
        } catch (RequestException $exception) {
            $this->error('Factus rechazo la factura.');
            $this->line($exception->response?->body() ?: $exception->getMessage());

            return self::FAILURE;
        } catch (\Throwable $exception) {
            $this->error('No se pudo enviar la factura a Factus.');
            $this->line($exception->getMessage());

            return self::FAILURE;
        }

        $data = $response['data'] ?? [];
        $bill = $data['bill'] ?? $data;
        $this->info($response['message'] ?? 'Factura enviada a Factus.');
        $this->line('Numero Factus: '.($bill['number'] ?? 'N/A'));
        $this->line('CUFE: '.($bill['cufe'] ?? 'N/A'));

        return self::SUCCESS;
    }
}
