<?php

namespace App\Services\Ubl21;

use App\Models\ConfiguracionEmpresa;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente para el segundo proveedor de facturacion electronica (API DIAN
 * UBL 2.1 propia, distinta a Factus -- ver App\Services\Factus\FactusClient
 * para el equivalente). A diferencia de Factus (OAuth2 password grant), este
 * proveedor usa un unico Bearer token fijo (el "api_token" que devuelve al
 * registrar la empresa), sin refresco -- se manda tal cual en cada request.
 */
class Ubl21Client
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiToken,
    ) {
    }

    public static function forEmpresa(ConfiguracionEmpresa $configuracion): self
    {
        if (blank($configuracion->ubl21_base_url) || blank($configuracion->ubl21_api_token)) {
            throw new \RuntimeException('Esta empresa no tiene configurado el proveedor UBL 2.1 (falta URL o token).');
        }

        return new self(
            rtrim($configuracion->ubl21_base_url, '/'),
            $configuracion->ubl21_api_token,
        );
    }

    /**
     * @throws RequestException
     */
    public function get(string $path): array
    {
        return $this->request('get', $path);
    }

    /**
     * @throws RequestException
     */
    public function post(string $path, array $payload = []): array
    {
        return $this->request('post', $path, $payload);
    }

    /**
     * @throws RequestException
     */
    private function request(string $method, string $path, array $payload = []): array
    {
        $response = Http::withToken($this->apiToken)
            ->acceptJson()
            ->asJson()
            ->timeout(45)
            ->{$method}($this->url($path), $payload);

        if ($response->failed()) {
            Log::warning('Proveedor UBL 2.1 rechazo una peticion', [
                'method' => $method,
                'path' => $path,
                'status' => $response->status(),
                'response' => $response->json() ?? $response->body(),
                'payload' => $payload,
            ]);
        }

        return $response->throw()->json();
    }

    public function emitirFactura(array $payload): array
    {
        return $this->post('/invoice', $payload);
    }

    public function emitirNotaCredito(array $payload): array
    {
        return $this->post('/credit-note', $payload);
    }

    // El proveedor no entrega una URL descargable para el PDF (urlinvoicepdf
    // en la respuesta de emitirFactura es solo el nombre del archivo, sin
    // dominio) -- hay que pedirlo aparte, autenticado, y llega en base64.
    public function pdfBase64(string $prefix, string $number, string $cufe): ?string
    {
        $response = $this->post("/regeneratepdf/{$prefix}/{$number}/{$cufe}");

        return $response['filebase64'] ?? null;
    }

    public function consecutivoActual(int $tipoDocumentoId, ?string $prefijo = null): array
    {
        $path = "/invoice/current_number/{$tipoDocumentoId}";

        if ($prefijo) {
            $path .= '/'.$prefijo;
        }

        return $this->get($path);
    }

    private function url(string $path): string
    {
        return $this->baseUrl.'/'.ltrim($path, '/');
    }
}
