<?php

namespace App\Console\Commands;

use App\Services\Factus\FactusClient;
use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;

class FactusTestConnection extends Command
{
    protected $signature = 'factus:test-connection {--refresh-token : Solicita un token nuevo a Factus}';

    protected $description = 'Prueba la autenticacion contra la API de Factus.';

    public function handle(FactusClient $factus): int
    {
        foreach (['base_url', 'username', 'password', 'client_id', 'client_secret'] as $key) {
            if (blank(config("services.factus.{$key}"))) {
                $this->error("Falta configurar services.factus.{$key}.");

                return self::FAILURE;
            }
        }

        try {
            $token = $factus->token((bool) $this->option('refresh-token'));
        } catch (RequestException $exception) {
            $this->error('Factus rechazo la autenticacion.');
            $this->line($exception->response?->body() ?: $exception->getMessage());

            return self::FAILURE;
        } catch (\Throwable $exception) {
            $this->error('No se pudo conectar con Factus.');
            $this->line($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Conexion con Factus correcta.');
        $this->line('Token recibido: '.substr($token, 0, 8).'...');

        return self::SUCCESS;
    }
}
