<?php

namespace App\Console\Commands;

use App\Services\Turion\CatalogoImportador;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Empareja esta terminal de Turion con una empresa del droplet: cambia un
 * codigo de un solo uso (generado desde Filament, ver EmparejarTerminal)
 * por un token de Sanctum permanente + el catalogo inicial completo, y
 * siembra ese catalogo en la base de datos local (SQLite).
 *
 * Solo tiene sentido correrlo contra la base de datos LOCAL de una
 * terminal de Turion, nunca contra el droplet.
 */
class PosPair extends Command
{
    protected $signature = 'pos:pair
        {codigo : Codigo de emparejamiento generado en el panel del negocio}
        {servidor : URL base del droplet (ej: https://159-89-81-81.sslip.io)}
        {--terminal= : Nombre para identificar esta terminal (por defecto el nombre de la maquina)}';

    protected $description = 'Empareja esta terminal de Turion con una empresa, descargando su catalogo inicial.';

    public function handle(): int
    {
        $servidor = rtrim($this->argument('servidor'), '/');
        $terminal = $this->option('terminal') ?: gethostname();

        $this->info('Contactando '.$servidor.'...');

        $respuesta = Http::timeout(30)->post($servidor.'/api/pairing/bootstrap', [
            'codigo' => $this->argument('codigo'),
            'terminal_nombre' => $terminal,
        ]);

        if (! $respuesta->successful()) {
            $this->error('No se pudo emparejar: '.($respuesta->json('message') ?? $respuesta->status()));

            return self::FAILURE;
        }

        $data = $respuesta->json();

        $this->info('Emparejado con: '.$data['empresa_nombre']);
        $this->info('Sembrando catálogo local...');

        CatalogoImportador::sembrar($data['catalogo']);

        DB::table('sync_state')->delete();
        DB::table('sync_state')->insert([
            'empresa_id' => $data['empresa_id'],
            'empresa_nombre' => $data['empresa_nombre'],
            'terminal_token' => $data['token'],
            'servidor_url' => $servidor,
            'ultima_sincronizacion_at' => now(),
            'ultima_subida_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->info('Terminal emparejada y catálogo listo.');

        return self::SUCCESS;
    }
}
