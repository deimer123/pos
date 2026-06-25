<?php

namespace App\Livewire;

use App\Models\Mesa;
use App\Models\OrdenMesa;
use Livewire\Component;

class PanelMesas extends Component
{
    public string $zonaFiltro = '';

    protected $listeners = [
        'orden-guardada' => '$refresh',
        'mesa-liberada'  => '$refresh',
    ];

    private function empresaId(): int
    {
        return auth()->user()->getEmpresaActualId();
    }

    public function getMesasProperty()
    {
        return Mesa::where('empresa_id', $this->empresaId())
            ->where('activo', true)
            ->when($this->zonaFiltro, fn ($q) => $q->where('zona', $this->zonaFiltro))
            ->with(['ordenes' => fn ($q) => $q->whereIn('estado', ['abierta', 'en_preparacion'])->with('items')->latest()])
            ->orderBy('codigo')
            ->get();
    }

    public function getCuentasPendientesProperty()
    {
        return OrdenMesa::where('empresa_id', $this->empresaId())
            ->where('estado', 'lista')
            ->with('mesa')
            ->latest()
            ->get();
    }

    public function cobrarCuentaPendiente(int $ordenId): void
    {
        $orden = OrdenMesa::where('empresa_id', $this->empresaId())
            ->where('id', $ordenId)
            ->where('estado', 'lista')
            ->firstOrFail();

        // Verificar que la mesa esté libre (sin orden activa abierta)
        $mesaOcupada = OrdenMesa::where('mesa_id', $orden->mesa_id)
            ->where('estado', 'abierta')
            ->exists();

        if ($mesaOcupada) {
            $this->dispatch('warning', 'La mesa tiene una orden activa. Ciérrela antes de cobrar esta cuenta en espera.');
            return;
        }

        // Reactivar la orden y marcar la mesa como ocupada para poder facturar
        $orden->update(['estado' => 'en_preparacion']);
        \App\Models\Mesa::where('id', $orden->mesa_id)->update(['estado' => 'ocupada']);

        $this->redirect(route('pos.mesa', $orden->mesa_id));
    }

    public function getZonasProperty()
    {
        return Mesa::where('empresa_id', $this->empresaId())
            ->where('activo', true)
            ->whereNotNull('zona')
            ->distinct()
            ->pluck('zona');
    }

    public function abrirMesa(int $mesaId): void
    {
        $this->redirect(route('pos.mesa', $mesaId));
    }

    public function render()
    {
        return view('livewire.panel-mesas', [
            'mesas'             => $this->mesas,
            'zonas'             => $this->zonas,
            'cuentasPendientes' => $this->cuentasPendientes,
        ]);
    }
}
