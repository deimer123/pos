<?php

namespace App\Console\Commands;

use App\Services\Factus\FactusClient;
use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;

class FactusTestBill extends Command
{
    protected $signature = 'factus:test-bill';

    protected $description = 'Envia una factura de prueba simple al sandbox de Factus.';

    public function handle(FactusClient $factus): int
    {
        $numberingRangeId = config('services.factus.numbering_range_id');

        if (blank($numberingRangeId)) {
            $this->error('Falta FACTUS_NUMBERING_RANGE_ID. Ejecuta factus:sync-numbering-range --use-first.');

            return self::FAILURE;
        }

        $payload = [
            'reference_code' => 'POS-TEST-'.now()->format('YmdHis'),
            'document' => '01',
            'numbering_range_id' => (int) $numberingRangeId,
            'send_email' => false,
            'observation' => 'Factura sandbox generada desde POS',
            'payment_details' => [[
                'payment_form' => '1',
                'payment_method_code' => '10',
                'reference_code' => 'PAGO-TEST',
                'amount' => '11900.00',
            ]],
            'customer' => [
                'identification_document_id' => 3,
                'identification' => '123456789',
                'names' => 'Cliente de Prueba POS',
                'address' => 'calle 1 # 1-1',
                'email' => 'cliente.prueba@example.com',
                'phone' => '3000000000',
                'legal_organization_id' => 2,
                'tribute_id' => 21,
                'municipality_id' => 980,
            ],
            'items' => [[
                'code_reference' => 'PROD-TEST-001',
                'name' => 'Producto de prueba POS',
                'quantity' => '1.00',
                'discount_rate' => '0.00',
                'price' => '10000.00',
                'tax_rate' => '19.00',
                'unit_measure_id' => 70,
                'standard_code_id' => 1,
                'is_excluded' => 0,
                'tribute_id' => 1,
                'withholding_taxes' => [],
            ]],
        ];

        try {
            $response = $factus->validateBill($payload);
        } catch (RequestException $exception) {
            $this->error('Factus rechazo la factura de prueba.');
            $this->line($exception->response?->body() ?: $exception->getMessage());

            return self::FAILURE;
        } catch (\Throwable $exception) {
            $this->error('No se pudo enviar la factura de prueba.');
            $this->line($exception->getMessage());

            return self::FAILURE;
        }

        $data = $response['data'] ?? [];
        $bill = $data['bill'] ?? $data;
        $this->info($response['message'] ?? 'Factura de prueba enviada.');
        $this->line('Referencia: '.$payload['reference_code']);
        $this->line('Numero Factus: '.($bill['number'] ?? 'N/A'));
        $this->line('Validada: '.(((bool) ($bill['is_validated'] ?? false) || (int) ($bill['status'] ?? 0) === 1 || filled($bill['validated'] ?? null)) ? 'si' : 'no'));
        $this->line('CUFE: '.($bill['cufe'] ?? 'N/A'));

        return self::SUCCESS;
    }
}
