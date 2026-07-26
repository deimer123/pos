<?php

namespace App\Livewire;

use App\Models\Mesa;
use App\Models\OrdenMesa;
use App\Models\OrdenMesaItem;
use App\Models\Product;
use App\Services\Turion\ColaSincronizacion;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class ComandaMesa extends Component
{
    public int $mesaId;
    public string $busqueda = '';
    public string $observaciones = '';

    public ?int $ordenId = null;

    public function mount(int $mesaId): void
    {
        $this->mesaId = $mesaId;
        $this->cargarOrdenActiva();
    }

    private function empresaId(): int
    {
        return auth()->user()->getEmpresaActualId();
    }

    private function cargarOrdenActiva(): void
    {
        $orden = OrdenMesa::where('empresa_id', $this->empresaId())
            ->where('mesa_id', $this->mesaId)
            ->whereIn('estado', ['abierta', 'en_preparacion', 'lista'])
            ->latest()
            ->first();

        if ($orden) {
            $this->ordenId      = $orden->id;
            $this->observaciones = $orden->observaciones ?? '';
        }
    }

    public function getMesaProperty(): Mesa
    {
        return Mesa::findOrFail($this->mesaId);
    }

    public function getOrdenProperty(): ?OrdenMesa
    {
        return $this->ordenId
            ? OrdenMesa::with('items.producto')->find($this->ordenId)
            : null;
    }

    public function getProductosProperty()
    {
        if (strlen($this->busqueda) < 2) return collect();

        return Product::where('empresa_id', $this->empresaId())
            ->where(fn ($q) => $q
                ->where('descripcion_larga', 'like', '%' . $this->busqueda . '%')
                ->orWhere('id_producto', 'like', '%' . $this->busqueda . '%')
            )
            ->limit(12)
            ->get();
    }

    private function obtenerOCrearOrden(): OrdenMesa
    {
        if ($this->ordenId) {
            return OrdenMesa::findOrFail($this->ordenId);
        }

        $orden = OrdenMesa::create([
            'empresa_id'  => $this->empresaId(),
            'mesa_id'     => $this->mesaId,
            'usuario_id'  => auth()->id(),
            'estado'      => 'abierta',
            'abierta_en'  => now(),
        ]);

        $this->ordenId = $orden->id;

        Mesa::where('id', $this->mesaId)->update(['estado' => 'ocupada']);

        return $orden;
    }

    public function agregarProducto(int $productId): void
    {
        $producto = Product::where('empresa_id', $this->empresaId())
            ->where('id', $productId)
            ->firstOrFail();

        $orden = $this->obtenerOCrearOrden();

        $item = OrdenMesaItem::where('orden_mesa_id', $orden->id)
            ->where('product_id', $productId)
            ->where('estado_cocina', 'pendiente')
            ->first();

        if ($item) {
            $item->cantidad  += 1;
            $item->subtotal   = $item->cantidad * $item->precio_unitario;
            $item->save();
        } else {
            OrdenMesaItem::create([
                'empresa_id'      => $this->empresaId(),
                'orden_mesa_id'   => $orden->id,
                'product_id'      => $productId,
                'cantidad'        => 1,
                'precio_unitario' => $producto->precio_venta1,
                'subtotal'        => $producto->precio_venta1,
                'estado_cocina'   => 'pendiente',
                'requiere_cocina' => true,
            ]);
        }

        $this->recalcularTotales($orden);
        $this->busqueda = '';
        $this->dispatch('orden-guardada');
    }

    public function cambiarCantidad(int $itemId, float $delta): void
    {
        $item = OrdenMesaItem::findOrFail($itemId);
        $nuevaCantidad = $item->cantidad + $delta;

        if ($nuevaCantidad <= 0) {
            $item->delete();
        } else {
            $item->cantidad = $nuevaCantidad;
            $item->subtotal = $nuevaCantidad * $item->precio_unitario;
            $item->save();
        }

        $this->recalcularTotales(OrdenMesa::find($this->ordenId));
    }

    public function eliminarItem(int $itemId): void
    {
        OrdenMesaItem::destroy($itemId);
        $this->recalcularTotales(OrdenMesa::find($this->ordenId));
    }

    public function enviarACocina(): void
    {
        if (! $this->ordenId) return;

        $orden = OrdenMesa::findOrFail($this->ordenId);

        $orden->items()
            ->where('estado_cocina', 'pendiente')
            ->update([
                'estado_cocina'     => 'enviado',
                'enviado_cocina_en' => now(),
            ]);

        $orden->update(['estado' => 'en_preparacion', 'observaciones' => $this->observaciones]);

        $this->dispatch('notify', ['message' => 'Comanda enviada a cocina', 'type' => 'success']);
        $this->dispatch('orden-guardada');
    }

    public function facturar(): void
    {
        if (! $this->ordenId) return;

        $orden = OrdenMesa::with('items.producto')->findOrFail($this->ordenId);

        // Pasar items al carrito del POS
        $carritoItems = [];
        foreach ($orden->items as $item) {
            $p = $item->producto;
            if (! $p) continue;
            $carritoItems[] = [
                'id_producto'        => $p->id_producto,
                'nombre'             => $p->descripcion_larga,
                'cantidad'           => (float) $item->cantidad,
                'precio'             => (float) $item->precio_unitario,
                'origen_mesa_id'     => $this->mesaId,
                'origen_orden_id'    => $orden->id,
            ];
        }

        $this->dispatch('cargar-desde-mesa', [
            'items'   => $carritoItems,
            'mesaId'  => $this->mesaId,
            'ordenId' => $orden->id,
        ]);
    }

    public function liberarMesa(): void
    {
        if ($this->ordenId) {
            OrdenMesa::where('id', $this->ordenId)->update([
                'estado'     => 'cancelada',
                'cerrada_en' => now(),
            ]);

            // Sin esto, si esta mesa tenia una orden activa tambien en el
            // droplet, el siguiente "Sincronizar" no la tocaba (las
            // ordenes de mesa no bajan por pull), pero el droplet
            // quedaria con una orden abierta que aca ya se cerro.
            if (DB::getDriverName() === 'sqlite') {
                ColaSincronizacion::encolarMesaLiberada($this->mesaId);
            }
        }

        Mesa::where('id', $this->mesaId)->update(['estado' => 'libre']);

        $this->dispatch('mesa-liberada');
        $this->dispatch('cerrar-comanda');
    }

    private function recalcularTotales(OrdenMesa $orden): void
    {
        $orden->load('items');
        $subtotal = $orden->items->sum('subtotal');
        $orden->update(['subtotal' => $subtotal, 'total' => $subtotal]);
    }

    public function render()
    {
        return view('livewire.comanda-mesa', [
            'mesa'      => $this->mesa,
            'orden'     => $this->orden,
            'productos' => $this->productos,
        ]);
    }
}
