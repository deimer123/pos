<?php

namespace App\Livewire;

use App\Models\OrdenMesa;
use App\Models\OrdenMesaItem;
use App\Services\PedidoListoNotifier;
use Livewire\Component;

class PantallaCocina extends Component
{
    public string $filtro = 'pendientes'; // pendientes | entregadas

    private function empresaId(): int
    {
        return auth()->user()->getEmpresaActualId();
    }

    public function getOrdenesProperty()
    {
        $query = OrdenMesa::where('empresa_id', $this->empresaId())
            ->whereIn('estado', ['abierta', 'en_preparacion'])
            ->with([
                'mesa',
                'items' => fn ($q) => $q
                    ->whereIn('estado_cocina', ['enviado', 'preparando', 'listo'])
                    ->with('producto'),
            ])
            ->orderBy('numero_cocina_dia');

        if ($this->filtro === 'entregadas') {
            $query->where('entregada', true);
        } else {
            $query->where('entregada', false);
        }

        return $query->get()->filter(fn ($o) => $o->items->isNotEmpty());
    }

    public function marcarListo(int $itemId): void
    {
        $item = OrdenMesaItem::where('id', $itemId)
            ->where('empresa_id', $this->empresaId())
            ->whereIn('estado_cocina', ['enviado', 'preparando'])
            ->first();

        if (! $item) {
            return;
        }

        $item->update(['estado_cocina' => 'listo']);

        $orden = OrdenMesa::find($item->orden_mesa_id);

        if (! $orden) {
            return;
        }

        $itemsCocina = $orden->items()->whereIn('estado_cocina', ['enviado', 'preparando', 'listo'])->get();
        $todosListos = $itemsCocina->isNotEmpty() && $itemsCocina->every(fn ($i) => $i->estado_cocina === 'listo');

        if ($todosListos) {
            app(PedidoListoNotifier::class)->notificarOrdenLista($orden);
        }
    }

    public function marcarEntregada(int $ordenId): void
    {
        OrdenMesa::where('id', $ordenId)
            ->where('empresa_id', $this->empresaId())
            ->update(['entregada' => true]);
    }

    public function render()
    {
        return view('livewire.pantalla-cocina', [
            'ordenes' => $this->ordenes,
        ])->layout('layouts.cocina');
    }
}
