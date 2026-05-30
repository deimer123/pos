<?php

namespace App\Services\Factus;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class FactusClient
{
    public function __construct(
        private readonly ?string $baseUrl = null,
    ) {
    }

    /**
     * @throws RequestException
     */
    public function token(bool $forceRefresh = false): string
    {
        $cacheKey = 'factus.oauth_token';

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
        return Http::withToken($this->token())
            ->acceptJson()
            ->asJson()
            ->timeout(45)
            ->post($this->url($path), $payload)
            ->throw()
            ->json();
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
    public function municipalities(?string $name = null): array
    {
        return $this->get('/v1/municipalities', array_filter([
            'name' => $name,
        ], fn ($value) => $value !== null && $value !== ''));
    }

    private function oauthCredentials(): array
    {
        $credentials = [
            'grant_type' => 'password',
            'client_id' => config('services.factus.client_id'),
            'client_secret' => config('services.factus.client_secret'),
            'username' => config('services.factus.username'),
            'password' => config('services.factus.password'),
        ];

        $missing = collect($credentials)
            ->except('grant_type')
            ->filter(fn ($value) => blank($value))
            ->keys()
            ->map(fn ($key) => 'FACTUS_'.strtoupper($key))
            ->values()
            ->all();

        if ($missing !== []) {
            throw new \RuntimeException('Faltan credenciales de Factus en .env: '.implode(', ', $missing).'.');
        }

        return $credentials;
    }

    private function url(string $path): string
    {
        $baseUrl = rtrim($this->baseUrl ?: config('services.factus.base_url'), '/');

        return $baseUrl.'/'.ltrim($path, '/');
    }
}
