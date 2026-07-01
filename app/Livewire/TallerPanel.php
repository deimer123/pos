<?php

namespace App\Livewire;

use App\Models\Mesa;
use App\Models\Product;
use App\Models\TallerOrden;
use App\Models\TallerRepuesto;
use Livewire\Component;

class TallerPanel extends Component
{
    // Lista
    public string $filtroEstado = '';
    public string $busqueda     = '';

    // Modal nueva/editar orden
    public bool $modalOrden = false;
    public ?int $ordenId    = null;

    public string $clienteNombre   = '';
    public string $clienteTelefono = '';
    public string $placa           = '';
    public string $marca           = '';
    public string $modelo          = '';
    public string $color           = '';
    public string $kmIngreso       = '';
    public string $diagnostico     = '';
    public string $observaciones   = '';
    public string $estado          = 'pendiente';

    // Repuestos dentro del modal de orden
    public array $repuestos = [];

    // Búsqueda de productos para repuestos
    public string $buscarProducto = '';

    private function empresaId(): int
    {
        return auth()->user()->getEmpresaActualId();
    }

    public function getOrdenesProperty()
    {
        return TallerOrden::where('empresa_id', $this->empresaId())
            ->when($this->filtroEstado, fn($q) => $q->where('estado', $this->filtroEstado))
            ->when($this->busqueda, fn($q) => $q->where(function($q2) {
                $q2->where('placa', 'like', '%'.$this->busqueda.'%')
                   ->orWhere('cliente_nombre', 'like', '%'.$this->busqueda.'%')
                   ->orWhere('marca', 'like', '%'.$this->busqueda.'%');
            }))
            ->with('repuestos')
            ->orderByDesc('created_at')
            ->get();
    }

    public function getProductosSugeridosProperty()
    {
        if (strlen($this->buscarProducto) < 2) return collect();

        return Product::where('empresa_id', $this->empresaId())
            ->where(fn($q) => $q->where('nombre', 'like', '%'.$this->buscarProducto.'%')
                                ->orWhere('codigo', 'like', '%'.$this->buscarProducto.'%'))
            ->limit(8)
            ->get(['id','nombre','precio_venta','codigo']);
    }

    public function nuevaOrden(): void
    {
        $this->reset(['ordenId','clienteNombre','clienteTelefono','placa','marca','modelo',
                      'color','kmIngreso','diagnostico','observaciones','repuestos','buscarProducto']);
        $this->estado     = 'pendiente';
        $this->modalOrden = true;
    }

    public function editarOrden(int $id): void
    {
        $orden = TallerOrden::where('empresa_id', $this->empresaId())->with('repuestos')->findOrFail($id);

        $this->ordenId         = $orden->id;
        $this->clienteNombre   = $orden->cliente_nombre;
        $this->clienteTelefono = $orden->cliente_telefono ?? '';
        $this->placa           = $orden->placa;
        $this->marca           = $orden->marca ?? '';
        $this->modelo          = $orden->modelo ?? '';
        $this->color           = $orden->color ?? '';
        $this->kmIngreso       = $orden->km_ingreso ? (string) $orden->km_ingreso : '';
        $this->diagnostico     = $orden->diagnostico ?? '';
        $this->observaciones   = $orden->observaciones ?? '';
        $this->estado          = $orden->estado;

        $this->repuestos = $orden->repuestos->map(fn($r) => [
            'id'             => $r->id,
            'producto_id'    => $r->producto_id,
            'descripcion'    => $r->descripcion,
            'cantidad'       => $r->cantidad,
            'precio_unitario'=> $r->precio_unitario,
            'subtotal'       => $r->subtotal,
        ])->toArray();

        $this->buscarProducto = '';
        $this->modalOrden     = true;
    }

    public function agregarProducto(int $productoId): void
    {
        $producto = Product::where('empresa_id', $this->empresaId())->find($productoId);
        if (! $producto) return;

        // Si ya existe, incrementar cantidad
        foreach ($this->repuestos as &$r) {
            if ($r['producto_id'] == $productoId) {
                $r['cantidad']  += 1;
                $r['subtotal']   = round($r['cantidad'] * $r['precio_unitario'], 2);
                $this->buscarProducto = '';
                return;
            }
        }

        $this->repuestos[] = [
            'id'             => null,
            'producto_id'    => $productoId,
            'descripcion'    => $producto->nombre,
            'cantidad'       => 1,
            'precio_unitario'=> (float) $producto->precio_venta,
            'subtotal'       => (float) $producto->precio_venta,
        ];

        $this->buscarProducto = '';
    }

    public function agregarRepuestoManual(): void
    {
        $this->repuestos[] = [
            'id'             => null,
            'producto_id'    => null,
            'descripcion'    => '',
            'cantidad'       => 1,
            'precio_unitario'=> 0,
            'subtotal'       => 0,
        ];
    }

    public function actualizarRepuesto(int $index): void
    {
        if (! isset($this->repuestos[$index])) return;
        $r = &$this->repuestos[$index];
        $r['subtotal'] = round((float)$r['cantidad'] * (float)$r['precio_unitario'], 2);
    }

    public function quitarRepuesto(int $index): void
    {
        array_splice($this->repuestos, $index, 1);
        $this->repuestos = array_values($this->repuestos);
    }

    public function guardarOrden(): void
    {
        $this->validate([
            'clienteNombre' => 'required|string|max:200',
            'placa'         => 'required|string|max:20',
        ], [
            'clienteNombre.required' => 'El nombre del cliente es obligatorio.',
            'placa.required'         => 'La placa es obligatoria.',
        ]);

        $data = [
            'empresa_id'      => $this->empresaId(),
            'cliente_nombre'  => trim($this->clienteNombre),
            'cliente_telefono'=> trim($this->clienteTelefono) ?: null,
            'placa'           => strtoupper(trim($this->placa)),
            'marca'           => trim($this->marca) ?: null,
            'modelo'          => trim($this->modelo) ?: null,
            'color'           => trim($this->color) ?: null,
            'km_ingreso'      => $this->kmIngreso ? (int) str_replace('.', '', $this->kmIngreso) : null,
            'diagnostico'     => trim($this->diagnostico) ?: null,
            'observaciones'   => trim($this->observaciones) ?: null,
            'estado'          => $this->estado,
            'creado_por'      => auth()->id(),
        ];

        if ($this->ordenId) {
            $orden = TallerOrden::where('empresa_id', $this->empresaId())->findOrFail($this->ordenId);
            $orden->update($data);
        } else {
            $orden = TallerOrden::create($data);
        }

        // Sincronizar repuestos
        $idsExistentes = [];
        foreach ($this->repuestos as $r) {
            if (! empty($r['descripcion']) && (float)$r['precio_unitario'] > 0) {
                $subtotal = round((float)$r['cantidad'] * (float)$r['precio_unitario'], 2);
                if ($r['id']) {
                    TallerRepuesto::where('id', $r['id'])->update([
                        'descripcion'     => $r['descripcion'],
                        'cantidad'        => $r['cantidad'],
                        'precio_unitario' => $r['precio_unitario'],
                        'subtotal'        => $subtotal,
                    ]);
                    $idsExistentes[] = $r['id'];
                } else {
                    $nuevo = TallerRepuesto::create([
                        'orden_id'        => $orden->id,
                        'producto_id'     => $r['producto_id'] ?? null,
                        'descripcion'     => $r['descripcion'],
                        'cantidad'        => $r['cantidad'],
                        'precio_unitario' => $r['precio_unitario'],
                        'subtotal'        => $subtotal,
                    ]);
                    $idsExistentes[] = $nuevo->id;
                }
            }
        }

        // Eliminar los que fueron quitados
        TallerRepuesto::where('orden_id', $orden->id)
            ->when($idsExistentes, fn($q) => $q->whereNotIn('id', $idsExistentes))
            ->delete();

        $this->modalOrden = false;
        $this->dispatch('success', 'Orden #' . $orden->numero_orden . ' guardada.');
    }

    public function cambiarEstado(int $id, string $nuevoEstado): void
    {
        $orden = TallerOrden::where('empresa_id', $this->empresaId())->findOrFail($id);
        $update = ['estado' => $nuevoEstado];
        if ($nuevoEstado === 'entregado') {
            $update['entregado_at'] = now();
        }
        $orden->update($update);
    }

    public function facturarOrden(int $id): void
    {
        $empresaId = $this->empresaId();
        $orden = TallerOrden::where('empresa_id', $empresaId)->with('repuestos')->findOrFail($id);

        // Buscar o crear mesa virtual TALL-
        $mesa = Mesa::where('empresa_id', $empresaId)
            ->where('codigo', 'like', 'TALL-%')
            ->whereDoesntHave('ordenes', fn($q) => $q->whereIn('estado', ['abierta', 'en_preparacion']))
            ->first();

        if (! $mesa) {
            $siguiente = Mesa::where('empresa_id', $empresaId)
                ->where('codigo', 'like', 'TALL-%')
                ->count() + 1;

            $mesa = Mesa::create([
                'empresa_id' => $empresaId,
                'codigo'     => 'TALL-' . $siguiente,
                'nombre'     => 'Taller ' . $siguiente,
                'zona'       => null,
                'capacidad'  => null,
                'estado'     => 'libre',
                'activo'     => true,
            ]);
        }

        // Vincular la orden con esta mesa y marcarla en proceso si aún no lo está
        $orden->update([
            'mesa_id' => $mesa->id,
            'estado'  => in_array($orden->estado, ['pendiente', 'en_proceso', 'listo']) ? $orden->estado : $orden->estado,
        ]);

        $this->redirect(route('pos.mesa', $mesa->id) . '?taller_orden=' . $orden->id);
    }

    public function eliminarOrden(int $id): void
    {
        TallerOrden::where('empresa_id', $this->empresaId())->findOrFail($id)->delete();
    }

    public function render()
    {
        return view('livewire.taller-panel', [
            'ordenes'            => $this->ordenes,
            'productosSugeridos' => $this->productosSugeridos,
        ]);
    }
}
