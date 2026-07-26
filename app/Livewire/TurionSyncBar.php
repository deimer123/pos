<?php

namespace App\Livewire;

use App\Services\Turion\ConectividadDroplet;
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
    public bool $enLinea = true;
    public int $pendientes = 0;
    public ?string $ultimaSincronizacion = null;
    public ?string $ultimaSubida = null;
    public ?string $mensaje = null;
    public bool $conError = false;

    public function mount(): void
    {
        $this->esTurion = DB::getDriverName() === 'sqlite';

        if ($this->esTurion) {
            $this->enLinea = ConectividadDroplet::estaEnLinea();
            $this->refrescarEstado();
        }
    }

    // Turion sincroniza el catalogo solo, en segundo plano (ver
    // src-tauri/src/lib.rs) -- este poll es solo para que la hora de
    // "ultima sincronizacion" que ve el cajero se actualice sola cuando
    // eso pase, sin que tenga que refrescar la pagina a mano. Tambien
    // refresca si hay conexion, para que los botones se deshabiliten
    // solos apenas se cae internet, sin que el cajero tenga que darle
    // click para descubrirlo.
    public function refrescarEstadoPeriodico(): void
    {
        if ($this->esTurion) {
            $this->enLinea = ConectividadDroplet::estaEnLinea();
            $this->refrescarEstado();
        }
    }

    public function sincronizar(): void
    {
        if (! $this->esTurion) {
            return;
        }

        $this->enLinea = ConectividadDroplet::estaEnLinea();

        if (! $this->enLinea) {
            $this->mensaje = 'Sin conexión con el droplet: no se puede sincronizar ahora.';
            $this->conError = true;

            return;
        }

        $codigo = Artisan::call('pos:sync-catalog');
        $this->mensaje = trim(Artisan::output()) ?: 'Sincronizado.';
        $this->conError = $codigo !== 0;
        $this->refrescarEstado();
    }

    public function subir(): void
    {
        if (! $this->esTurion) {
            return;
        }

        $this->enLinea = ConectividadDroplet::estaEnLinea();

        if (! $this->enLinea) {
            $this->mensaje = 'Sin conexión con el droplet: no se puede subir ahora.';
            $this->conError = true;

            return;
        }

        $codigo = Artisan::call('pos:push');
        $this->mensaje = trim(Artisan::output()) ?: 'Subido.';
        $this->conError = $codigo !== 0;
        $this->refrescarEstado();
    }

    /**
     * Borra el emparejamiento local (sync_state) para que esta terminal
     * vuelva a pedir el codigo de emparejamiento -- util si se empareja
     * por error con la empresa equivocada, o para reasignar la terminal a
     * otro negocio. No toca pending_sync_operations: lo que ya estaba
     * pendiente de subir sigue ahi por si se vuelve a emparejar con la
     * misma empresa.
     */
    public function olvidarEmparejamiento()
    {
        if (! $this->esTurion) {
            return;
        }

        DB::table('sync_state')->delete();

        return redirect()->route('emparejar');
    }

    private function refrescarEstado(): void
    {
        $this->pendientes = (int) DB::table('pending_sync_operations')->where('estado', 'pendiente')->count();

        $estado = DB::table('sync_state')->first();
        $this->ultimaSincronizacion = $this->formatearFecha($estado?->ultima_sincronizacion_at);
        $this->ultimaSubida = $this->formatearFecha($estado?->ultima_subida_at);
    }

    private function formatearFecha(?string $valor): ?string
    {
        return $valor ? \Illuminate\Support\Carbon::parse($valor)->format('d/m/Y h:i A') : null;
    }

    public function render()
    {
        return view('livewire.turion-sync-bar');
    }
}
