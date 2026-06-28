<?php

namespace App\Livewire;

use App\Models\Domicilio;
use App\Models\DomicilioItem;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;

#[Layout('components.layouts.domicilios')]
class PanelDomicilios extends Component
{
    // Filtros
    public string $filtroEstado = 'todos';
    public string $filtroBuscar = '';

    // Modal nuevo/editar domicilio
    public bool $mostrarModal = false;
    public ?int $domicilioId = null;

    public string $clienteNombre = '';
    public string $clienteTelefono = '';
    public string $direccion = '';
    public string $barrio = '';
    public string $ciudad = '';
    public string $referencia = '';
    public string $observaciones = '';
    public string $valorDomicilio = '0';

    // Items del pedido
    public array $items = [];
    public string $itemBuscar = '';
    public array $productosSugeridos = [];

    // Modal asignar repartidor
    public bool $mostrarModalAsignar = false;
    public ?int $asignarDomicilioId = null;
    public ?int $repartidorSeleccionado = null;

    // Modal detalle
    public bool $mostrarDetalle = false;
    public ?Domicilio $domicilioDetalle = null;

    protected array $rules = [
        'clienteNombre'   => 'required|string|max:200',
        'clienteTelefono' => 'nullable|string|max:30',
        'direccion'       => 'required|string|max:300',
        'barrio'          => 'nullable|string|max:100',
        'ciudad'          => 'nullable|string|max:100',
        'referencia'      => 'nullable|string',
        'observaciones'   => 'nullable|string',
        'valorDomicilio'  => 'nullable|numeric|min:0',
    ];

    public function render()
    {
        $empresaId = Auth::user()->empresa_id ?? Auth::id();

        $query = Domicilio::where('empresa_id', $empresaId)
            ->with(['repartidor', 'items'])
            ->orderByRaw("FIELD(estado, 'pendiente','asignado','en_camino','entregado','cancelado')")
            ->orderByDesc('created_at');

        if ($this->filtroEstado !== 'todos') {
            $query->where('estado', $this->filtroEstado);
        }

        if ($this->filtroBuscar) {
            $query->where(function ($q) {
                $q->where('cliente_nombre', 'like', '%' . $this->filtroBuscar . '%')
                  ->orWhere('direccion', 'like', '%' . $this->filtroBuscar . '%')
                  ->orWhere('cliente_telefono', 'like', '%' . $this->filtroBuscar . '%')
                  ->orWhere('id', $this->filtroBuscar);
            });
        }

        $domicilios = $query->get();

        // Contadores por estado
        $contadores = Domicilio::where('empresa_id', $empresaId)
            ->selectRaw('estado, count(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado')
            ->toArray();

        // Repartidores disponibles
        $repartidores = User::role('repartidor')
            ->where('empresa_id', $empresaId)
            ->orWhere(function($q) use ($empresaId) {
                $q->whereHas('roles', fn($r) => $r->where('name', 'repartidor'));
            })
            ->get();

        return view('livewire.panel-domicilios', compact('domicilios', 'contadores', 'repartidores'));
    }

    public function abrirNuevo()
    {
        $this->resetForm();
        $this->mostrarModal = true;
    }

    public function abrirEditar(int $id)
    {
        $d = Domicilio::with('items')->findOrFail($id);
        $this->domicilioId      = $d->id;
        $this->clienteNombre    = $d->cliente_nombre;
        $this->clienteTelefono  = $d->cliente_telefono ?? '';
        $this->direccion        = $d->direccion;
        $this->barrio           = $d->barrio ?? '';
        $this->ciudad           = $d->ciudad ?? '';
        $this->referencia       = $d->referencia ?? '';
        $this->observaciones    = $d->observaciones ?? '';
        $this->valorDomicilio   = (string) $d->valor_domicilio;
        $this->items = $d->items->map(fn($i) => [
            'producto_id'     => $i->producto_id,
            'nombre_producto' => $i->nombre_producto,
            'cantidad'        => $i->cantidad,
            'precio'          => $i->precio,
            'total'           => $i->total,
            'nota'            => $i->nota ?? '',
        ])->toArray();
        $this->mostrarModal = true;
    }

    public function guardar()
    {
        $this->validate();

        $empresaId = Auth::user()->empresa_id ?? Auth::id();
        $total = collect($this->items)->sum('total');

        $datos = [
            'empresa_id'      => $empresaId,
            'cliente_nombre'  => $this->clienteNombre,
            'cliente_telefono'=> $this->clienteTelefono,
            'direccion'       => $this->direccion,
            'barrio'          => $this->barrio,
            'ciudad'          => $this->ciudad,
            'referencia'      => $this->referencia,
            'observaciones'   => $this->observaciones,
            'valor_domicilio' => (float) str_replace(['.', ','], ['', '.'], $this->valorDomicilio),
            'total_pedido'    => $total,
            'creado_por'      => Auth::id(),
        ];

        if ($this->domicilioId) {
            $domicilio = Domicilio::findOrFail($this->domicilioId);
            $domicilio->update($datos);
            $domicilio->items()->delete();
        } else {
            $datos['estado'] = 'pendiente';
            $domicilio = Domicilio::create($datos);
        }

        foreach ($this->items as $item) {
            $domicilio->items()->create([
                'producto_id'     => $item['producto_id'] ?? 0,
                'nombre_producto' => $item['nombre_producto'],
                'cantidad'        => $item['cantidad'],
                'precio'          => $item['precio'],
                'total'           => $item['total'],
                'nota'            => $item['nota'] ?? '',
            ]);
        }

        $this->mostrarModal = false;
        $this->resetForm();
        $this->dispatch('notify', type: 'success', message: 'Domicilio guardado.');
    }

    public function agregarItemManual()
    {
        $this->items[] = [
            'producto_id'     => 0,
            'nombre_producto' => '',
            'cantidad'        => 1,
            'precio'          => 0,
            'total'           => 0,
            'nota'            => '',
        ];
    }

    public function quitarItem(int $index)
    {
        array_splice($this->items, $index, 1);
        $this->recalcularItems();
    }

    public function updatedItems()
    {
        $this->recalcularItems();
    }

    public function recalcularItems()
    {
        foreach ($this->items as $i => $item) {
            $this->items[$i]['total'] = round((float)($item['cantidad'] ?? 1) * (float)($item['precio'] ?? 0), 2);
        }
    }

    // Cambiar estado directamente
    public function cambiarEstado(int $id, string $nuevoEstado)
    {
        $domicilio = Domicilio::findOrFail($id);
        $datos = ['estado' => $nuevoEstado];

        if ($nuevoEstado === 'en_camino') $datos['en_camino_at'] = now();
        if ($nuevoEstado === 'entregado') $datos['entregado_at'] = now();

        $domicilio->update($datos);
        $this->dispatch('notify', type: 'success', message: 'Estado actualizado.');
    }

    // Modal asignar repartidor
    public function abrirAsignar(int $id)
    {
        $this->asignarDomicilioId    = $id;
        $this->repartidorSeleccionado = Domicilio::find($id)?->repartidor_id;
        $this->mostrarModalAsignar   = true;
    }

    public function confirmarAsignar()
    {
        $domicilio = Domicilio::findOrFail($this->asignarDomicilioId);
        $domicilio->update([
            'repartidor_id' => $this->repartidorSeleccionado,
            'estado'        => 'asignado',
            'asignado_at'   => now(),
        ]);
        $this->mostrarModalAsignar = false;
        $this->dispatch('notify', type: 'success', message: 'Repartidor asignado.');
    }

    public function verDetalle(int $id)
    {
        $this->domicilioDetalle = Domicilio::with(['items', 'repartidor', 'creadoPor'])->find($id);
        $this->mostrarDetalle   = true;
    }

    public function cancelar(int $id)
    {
        Domicilio::findOrFail($id)->update(['estado' => 'cancelado']);
        $this->dispatch('notify', type: 'success', message: 'Domicilio cancelado.');
    }

    private function resetForm()
    {
        $this->domicilioId      = null;
        $this->clienteNombre    = '';
        $this->clienteTelefono  = '';
        $this->direccion        = '';
        $this->barrio           = '';
        $this->ciudad           = '';
        $this->referencia       = '';
        $this->observaciones    = '';
        $this->valorDomicilio   = '0';
        $this->items            = [];
        $this->resetErrorBag();
    }
}
