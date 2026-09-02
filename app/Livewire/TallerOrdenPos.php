<?php

namespace App\Livewire;

use App\Models\Factura;
use App\Models\Product;
use App\Models\TallerOrden;
use App\Models\TallerRepuesto;
use Livewire\Component;
use Livewire\WithFileUploads;

class TallerOrdenPos extends Component
{
    use WithFileUploads;

    public int $ordenId;

    public string $buscarProducto = '';
    public $fotoTemp = null;

    // Modal facturar
    public bool $modalFacturar = false;
    public string $tipoPago    = 'contado';
    public string $medioPago   = 'efectivo';
    public string $obsFactura  = '';

    // Edición inline de ítem
    public ?int $editandoId    = null;
    public string $editDesc    = '';
    public float  $editCant    = 1;
    public float  $editPrecio  = 0;
    public string $editTipo    = 'repuesto';
    public float  $editCosto   = 0;

    public function mount(int $ordenId): void
    {
        $this->ordenId = $ordenId;
        // Verificar acceso
        TallerOrden::where('empresa_id', $this->empresaId())->findOrFail($ordenId);
    }

    private function empresaId(): int
    {
        return auth()->user()->getEmpresaActualId();
    }

    public function getOrdenProperty(): TallerOrden
    {
        return TallerOrden::where('empresa_id', $this->empresaId())
            ->with(['repuestos'])
            ->findOrFail($this->ordenId);
    }

    public function getProductosSugeridosProperty()
    {
        if (strlen($this->buscarProducto) < 2) return collect();

        return Product::where('empresa_id', $this->empresaId())
            ->where(fn($q) => $q->where('nombre', 'like', '%'.$this->buscarProducto.'%')
                                ->orWhere('codigo', 'like', '%'.$this->buscarProducto.'%'))
            ->limit(10)
            ->get(['id', 'id_producto', 'nombre', 'precio_venta', 'codigo']);
    }

    public function getTotalProperty(): float
    {
        return (float) TallerRepuesto::where('orden_id', $this->ordenId)->sum('subtotal');
    }

    // ─── Productos / ítems ───────────────────────────────────────────────────

    public function agregarProducto(int $id): void
    {
        $producto = Product::where('empresa_id', $this->empresaId())->find($id);
        if (! $producto) return;

        $existente = TallerRepuesto::where('orden_id', $this->ordenId)
            ->where('producto_id', $id)
            ->first();

        if ($existente) {
            $existente->update([
                'cantidad' => $existente->cantidad + 1,
                'subtotal' => ($existente->cantidad + 1) * $existente->precio_unitario,
            ]);
        } else {
            TallerRepuesto::create([
                'orden_id'        => $this->ordenId,
                'producto_id'     => $id,
                'descripcion'     => $producto->nombre,
                'cantidad'        => 1,
                'precio_unitario' => (float) $producto->precio_venta,
                'subtotal'        => (float) $producto->precio_venta,
                'tipo'            => 'repuesto',
                'costo_proveedor' => 0,
            ]);
        }

        $this->buscarProducto = '';
    }

    public function agregarManual(string $tipo = 'mano_obra'): void
    {
        TallerRepuesto::create([
            'orden_id'        => $this->ordenId,
            'producto_id'     => null,
            'descripcion'     => '',
            'cantidad'        => 1,
            'precio_unitario' => 0,
            'subtotal'        => 0,
            'tipo'            => $tipo,
            'costo_proveedor' => 0,
        ]);
    }

    public function editarItem(int $id): void
    {
        $item = TallerRepuesto::where('id', $id)->where('orden_id', $this->ordenId)->first();
        if (! $item) return;

        $this->editandoId = $id;
        $this->editDesc   = $item->descripcion;
        $this->editCant   = $item->cantidad;
        $this->editPrecio = $item->precio_unitario;
        $this->editTipo   = $item->tipo ?? 'repuesto';
        $this->editCosto  = $item->costo_proveedor ?? 0;
    }

    public function guardarItem(): void
    {
        if (! $this->editandoId) return;

        TallerRepuesto::where('id', $this->editandoId)
            ->where('orden_id', $this->ordenId)
            ->update([
                'descripcion'     => $this->editDesc,
                'cantidad'        => max(0.001, $this->editCant),
                'precio_unitario' => max(0, $this->editPrecio),
                'subtotal'        => round(max(0.001, $this->editCant) * max(0, $this->editPrecio), 2),
                'tipo'            => $this->editTipo,
                'costo_proveedor' => max(0, $this->editCosto),
            ]);

        $this->editandoId = null;
    }

    public function quitarItem(int $id): void
    {
        TallerRepuesto::where('id', $id)->where('orden_id', $this->ordenId)->delete();
        if ($this->editandoId === $id) {
            $this->editandoId = null;
        }
    }

    public function guardarNotaTrabajo(string $nota): void
    {
        TallerOrden::where('empresa_id', $this->empresaId())
            ->where('id', $this->ordenId)
            ->update(['nota_trabajo' => trim($nota) ?: null]);
    }

    // ─── Estado de la orden ──────────────────────────────────────────────────

    public function cambiarEstado(string $estado): void
    {
        $update = ['estado' => $estado];
        if ($estado === 'entregado') {
            $update['entregado_at'] = now();
        }
        TallerOrden::where('id', $this->ordenId)
            ->where('empresa_id', $this->empresaId())
            ->update($update);
    }

    // ─── Fotos ───────────────────────────────────────────────────────────────

    // Se dispara automaticamente cuando Livewire termina de subir el
    // archivo a almacenamiento temporal (wire:model) -- antes se llamaba a
    // subirFoto() con onchange="@this.call(...)" en el input, que corria en
    // paralelo a esa subida y no esperaba a que terminara, asi que la
    // miniatura de una foto solo aparecia al seleccionar la siguiente.
    public function updatedFotoTemp(): void
    {
        $this->subirFoto();
    }

    public function subirFoto(): void
    {
        $this->validate(['fotoTemp' => 'image|max:5120']);

        $path  = $this->fotoTemp->store('taller', 'public');
        $orden = $this->orden;
        $fotos = $orden->fotos ?? [];
        $fotos[] = $path;
        $orden->update(['fotos' => $fotos]);

        $this->fotoTemp = null;
    }

    public function eliminarFoto(int $index): void
    {
        $orden = $this->orden;
        $fotos = $orden->fotos ?? [];

        if (isset($fotos[$index])) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($fotos[$index]);
            array_splice($fotos, $index, 1);
            $orden->update(['fotos' => array_values($fotos)]);
        }
    }

    // ─── Facturación ─────────────────────────────────────────────────────────

    public function abrirModalFacturar(): void
    {
        if ($this->total <= 0) {
            $this->dispatch('swal-error', 'Agregue al menos un ítem con precio mayor a $0 antes de facturar.');
            return;
        }
        $this->modalFacturar = true;
    }

    public function facturar(): void
    {
        $orden      = $this->orden;
        $empresaId  = $this->empresaId();

        $repuestos = TallerRepuesto::where('orden_id', $this->ordenId)->get();
        $itemsFacturables = $repuestos->where('subtotal', '>', 0);

        if ($itemsFacturables->isEmpty()) {
            $this->dispatch('swal-error', 'No hay ítems con precio para facturar.');
            return;
        }

        $total = $itemsFacturables->sum('subtotal');

        // facturas.medio_pago solo acepta efectivo/transferencia/otro --
        // tarjeta y nequi (opciones del select, mas claras para quien
        // factura) se guardan como "otro", igual que ya hace el resto del
        // POS (ver CarritoVenta) con cualquier medio que no sea esos dos.
        $medioPago = in_array($this->medioPago, ['efectivo', 'transferencia'], true) ? $this->medioPago : 'otro';

        $factura = Factura::create([
            'empresa_id'    => $empresaId,
            'cliente_id'    => $orden->cliente_id,
            'user_id'       => auth()->id(),
            'vendedor_id'   => auth()->id(),
            'cajero_id'     => auth()->id(),
            'tipo_factura'  => 'salida',
            'tipo_pago'     => $this->tipoPago,
            'medio_pago'    => $medioPago,
            'fecha'         => now(),
            'fecha_compra'  => now(),
            'fecha_pago'    => $this->tipoPago === 'contado' ? now() : null,
            'total'         => $total,
            'saldo'         => $this->tipoPago === 'credito' ? $total : 0,
            'estado_pago'   => $this->tipoPago === 'contado' ? 'pagada' : 'pendiente',
            'observaciones' => $this->obsFactura ?: 'Taller #'.str_pad($orden->numero_orden, 4, '0', STR_PAD_LEFT).' · '.$orden->placa.' · '.$orden->cliente_nombre,
            'tipo_pedido'   => 'local',
            'costo_empaque' => 0,
        ]);

        foreach ($itemsFacturables as $r) {
            $factura->detalles()->create([
                // factura_detalles.producto_id es NOT NULL -- items
                // manuales (mano de obra, tercero) no tienen Product real,
                // mismo 0 centinela que usa FacturarVentaService para ese
                // caso.
                'producto_id'       => $r->producto?->id_producto ?? 0,
                'descripcion_larga' => $r->descripcion,
                'cantidad'          => $r->cantidad,
                'precio'            => $r->precio_unitario,
                'subtotal'          => $r->subtotal,
                'descuento'         => 0,
            ]);

            // Descontar stock para repuestos con producto (no para terceros)
            if ($r->producto_id && $r->tipo !== 'tercero') {
                $producto = Product::where('empresa_id', $empresaId)
                    ->find($r->producto_id);
                if ($producto) {
                    guardarKardex(
                        $producto->id_producto,
                        'venta',
                        $r->cantidad,
                        $empresaId,
                        $factura->id,
                        (float) $producto->existencias
                    );
                    $producto->decrement('existencias', $r->cantidad);
                }
            }
        }

        $orden->update([
            'estado'      => 'entregado',
            'factura_id'  => $factura->id,
            'entregado_at'=> now(),
        ]);

        $this->modalFacturar = false;

        $this->dispatch('open-print', ['url' => route('factura.ver', $factura->id)]);

        $this->redirect(route('taller'));
    }

    public function render()
    {
        return view('livewire.taller-orden-pos', [
            'orden'              => $this->orden,
            'productosSugeridos' => $this->productosSugeridos,
            'total'              => $this->total,
        ]);
    }
}
