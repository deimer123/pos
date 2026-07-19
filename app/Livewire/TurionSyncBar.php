<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Botones "Sincronizar" (baja catalogo/precios/stock del droplet) y
 * "Subir" (sube ventas/mesas/taller/hotel pendientes) que solo aparecen en
 * la base de datos LOCAL de Turion -- en el droplet este componente no
 * renderiza nada.
 */
class TurionSyncBar extends Component
{
    public bool $esTurion = false;
    public int $pendientes = 0;
    public ?string $ultimaSincronizacion = null;
    public ?string $ultimaSubida = null;
    public ?string $mensaje = null;
    public bool $conError = false;

    public function mount(): void
    {
        $this->esTurion = DB::getDriverName() === 'sqlite';

        if ($this->esTurion) {
            $this->refrescarEstado();
        }
    }

    public function sincronizar(): void
    {
        $codigo = Artisan::call('pos:sync-catalog');
        $this->mensaje = trim(Artisan::output()) ?: 'Sincronizado.';
        $this->conError = $codigo !== 0;
        $this->refrescarEstado();
    }

    public function subir(): void
    {
        $codigo = Artisan::call('pos:push');
        $this->mensaje = trim(Artisan::output()) ?: 'Subido.';
        $this->conError = $codigo !== 0;
        $this->refrescarEstado();
    }

    private function refrescarEstado(): void
    {
        $this->pendientes = (int) DB::table('pending_sync_operations')->where('estado', 'pendiente')->count();

        $estado = DB::table('sync_state')->first();
        $this->ultimaSincronizacion = $estado?->ultima_sincronizacion_at;
        $this->ultimaSubida = $estado?->ultima_subida_at;
    }

    public function render()
    {
        return view('livewire.turion-sync-bar');
    }
}
