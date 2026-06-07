<?php

namespace App\Services\Factus;

use App\Models\ConfiguracionEmpresa;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FactusClient
{
    public function __construct(
        private readonly ?string $baseUrl = null,
        private readonly ?array $credentials = null,
        private readonly ?string $cacheKeySuffix = null,
    ) {
    }

    public static function forEmpresa(ConfiguracionEmpresa $configuracion): self
    {
        $environmentUrl = $configuracion->factus_environment === 'production'
            ? 'https://api.factus.com.co'
            : 'https://api-sandbox.factus.com.co';

        return new self(
            $configuracion->factus_base_url ?: $environmentUrl,
            [
                'username' => $configuracion->factus_username,
                'password' => $configuracion->factus_password,
                'client_id' => $configuracion->factus_client_id,
                'client_secret' => $configuracion->factus_client_secret,
            ],
            'empresa_'.$configuracion->getKey(),
        );
    }

    /**
     * @throws RequestException
     */
    public function token(bool $forceRefresh = false): string
    {
        $cacheKey = 'factus.oauth_token.'.$this->credentialSignature();

        if ($forceRefresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, now()->addMinutes(50), function () {
            $credentials = $this->oauthCredentials();

            $response = Http::asForm()
                ->acceptJson()
                ->timeout(30)
                ->post($this->url('/oauth/token'), $credentials)
                ->throw();

            $data = $response->json();
            $token = $data['access_token'] ?? null;

            if (! is_string($token) || $token === '') {
                throw new \RuntimeException('Factus no devolvio un access_token valido.');
            }

            return $token;
        });
    }

    /**
     * @throws RequestException
     */
    public function get(string $path, array $query = []): array
    {
        return Http::withToken($this->token())
            ->acceptJson()
            ->timeout(30)
            ->get($this->url($path), $query)
            ->throw()
            ->json();
    }

    /**
     * @throws RequestException
     */
    public function post(string $path, array $payload = []): array
    {
        $response = Http::withToken($this->token())
            ->acceptJson()
            ->asJson()
            ->timeout(45)
            ->post($this->url($path), $payload);

        if ($response->failed()) {
            Log::warning('Factus rechazo una peticion POST', [
                'path' => $path,
                'status' => $response->status(),
                'response' => $response->json() ?? $response->body(),
                'payload' => $payload,
            ]);
        }

        return $response->throw()->json();
    }

    /**
     * @throws RequestException
     */
    public function validateBill(array $payload): array
    {
        return $this->post('/v1/bills/validate', $payload);
    }

    /**
     * @throws RequestException
     */
    public function validateCreditNote(array $payload): array
    {
        return $this->post('/v1/credit-notes/validate', $payload);
    }

    /**
     * @throws RequestException
     */
    public function numberingRanges(array $filters = []): array
    {
        $query = [];

        foreach ($filters as $key => $value) {
            if ($value !== null && $value !== '') {
                $query["filter[{$key}]"] = $value;
            }
        }

        return $this->get('/v1/numbering-ranges', $query);
    }

    /**
     * @throws RequestException
     */
    public function dianNumberingRanges(): array
    {
        return $this->get('/v2/numbering-ranges/dian');
    }

    /**
     * @throws RequestException
     */
    public function municipalities(?string $name = null): array
    {
        return $this->get('/v1/municipalities', array_filter([
            'name' => $name,
        ], fn ($value) => $value !== null && $value !== ''));
    }

    private function oauthCredentials(): array
    {
        $credentials = $this->credentials;

        $credentials = [
            'grant_type' => 'password',
            'client_id' => $credentials !== null ? ($credentials['client_id'] ?? null) : config('services.factus.client_id'),
            'client_secret' => $credentials !== null ? ($credentials['client_secret'] ?? null) : config('services.factus.client_secret'),
            'username' => $credentials !== null ? ($credentials['username'] ?? null) : config('services.factus.username'),
            'password' => $credentials !== null ? ($credentials['password'] ?? null) : config('services.factus.password'),
        ];

        $missing = collect($credentials)
            ->except('grant_type')
            ->filter(fn ($value) => blank($value))
            ->keys()
            ->map(fn ($key) => 'FACTUS_'.strtoupper($key))
            ->values()
            ->all();

        if ($missing !== []) {
            $source = $this->credentials === null ? '.env' : 'la configuracion de esta empresa';

            throw new \RuntimeException('Faltan credenciales de Factus en '.$source.': '.implode(', ', $missing).'.');
        }

        return $credentials;
    }

    private function url(string $path): string
    {
        $baseUrl = rtrim($this->baseUrl ?: config('services.factus.base_url'), '/');

        return $baseUrl.'/'.ltrim($path, '/');
    }

    private function credentialSignature(): string
    {
        return md5(json_encode([
            'base_url' => $this->baseUrl ?: config('services.factus.base_url'),
            'username' => $this->credentials['username'] ?? config('services.factus.username'),
            'client_id' => $this->credentials['client_id'] ?? config('services.factus.client_id'),
            'suffix' => $this->cacheKeySuffix,
        ]));
    }
}
