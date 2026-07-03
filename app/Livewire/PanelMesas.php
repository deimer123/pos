<?php

namespace App\Livewire;

use App\Models\ConfiguracionEmpresa;
use App\Models\Domicilio;
use App\Models\Factura;
use App\Models\Mesa;
use App\Models\OrdenMesa;
use Livewire\Component;

class PanelMesas extends Component
{
    public string $zonaFiltro = '';
    public bool $mostrarDomicilios = false;
    public string $vistaActual = 'mesas'; // mesas | taller
    public ?bool $domiciliosForzado = null;

    public function mount($domiciliosForzado = null): void
    {
        if ($domiciliosForzado !== null) {
            $this->domiciliosForzado = filter_var($domiciliosForzado, FILTER_VALIDATE_BOOLEAN);
        }
    }

    // Formulario nueva orden de taller
    public bool $mostrarFormTaller = false;
    public string $tallerNombre     = '';
    public string $tallerTelefono   = '';
    public string $tallerPlaca      = '';
    public string $tallerMarca      = '';
    public string $tallerModelo     = '';
    public string $tallerColor      = '';
    public string $tallerKm         = '';
    public string $tallerDiagnostico = '';

    protected $listeners = [
        'orden-guardada' => '$refresh',
        'mesa-liberada'  => '$refresh',
    ];

    private function empresaId(): int
    {
        return auth()->user()->getEmpresaActualId();
    }

    public function getUsaTallerProperty(): bool
    {
        return (bool) ConfiguracionEmpresa::where('empresa_id', $this->empresaId())
            ->value('usa_taller');
    }

    public function getOrdenestallerProperty()
    {
        return \App\Models\TallerOrden::where('empresa_id', $this->empresaId())
            ->with('repuestos')
            ->orderByRaw("FIELD(estado,'listo','en_proceso','pendiente','entregado','cancelado')")
            ->orderByDesc('created_at')
            ->get();
    }

    public function getUsaDomiciliosProperty(): bool
    {
        if ($this->domiciliosForzado !== null) {
            return $this->domiciliosForzado;
        }

        return (bool) ConfiguracionEmpresa::where('empresa_id', $this->empresaId())
            ->value('usa_domicilios');
    }

    public function getPuedeGestionarDomiciliosProperty(): bool
    {
        return auth()->user()->hasAnyRole(['cajero', 'admin_empresa']);
    }

    public function updatedMostrarDomicilios(): void
    {
        if (! $this->puedeGestionarDomicilios) {
            $this->mostrarDomicilios = false;
        }
    }

    public function getDomiciliosHoyProperty(): array
    {
        $empresaId = $this->empresaId();

        $enCocina = OrdenMesa::where('empresa_id', $empresaId)
            ->where('tipo_pedido', 'domicilio')
            ->whereIn('estado', ['abierta', 'en_preparacion', 'lista'])
            ->whereDate('created_at', today())
            ->with(['mesa', 'items.producto'])
            ->latest()
            ->get()
            ->map(function ($o) {
                $itemsCocina = $o->items->whereIn('estado_cocina', ['enviado', 'preparando', 'listo']);
                $listoParaEntregar = $itemsCocina->isNotEmpty()
                    && $itemsCocina->every(fn($i) => $i->estado_cocina === 'listo')
                    && ! $o->entregada;

                return [
                    'id'        => 'O-' . $o->id,
                    'origen'    => 'cocina',
                    'mesa_id'   => $o->mesa_id,
                    'estado'    => ($listoParaEntregar || $o->estado === 'lista') ? 'listo' : 'en_cocina',
                    'cliente'   => $o->dom_nombre ?: 'Domicilio',
                    'telefono'  => $o->dom_telefono ?? '',
                    'direccion' => $o->dom_direccion ?: '-',
                    'total'     => $o->total + ($o->dom_costo_domicilio ?? 0) + ($o->dom_costo_desechables ?? 0),
                    'subtotal'  => (float) $o->total,
                    'dom_costo' => (float) ($o->dom_costo_domicilio ?? 0),
                    'desechables' => (float) ($o->dom_costo_desechables ?? 0),
                    'hora'      => $o->created_at->format('h:i A'),
                    'observaciones' => $o->dom_observaciones ?? null,
                    'repartidor' => null,
                    'numero_cocina' => $o->numero_cocina_dia,
                    'items'     => $o->items->map(fn($i) => [
                        'nombre'   => $i->producto->nombre ?? 'Producto',
                        'cantidad' => (float) $i->cantidad,
                        'precio'   => (float) $i->precio_unitario,
                        'subtotal' => (float) $i->subtotal,
                        'nota'     => $i->nota_cocina,
                    ])->values()->toArray(),
                ];
            });

        $despachados = Factura::where('empresa_id', $empresaId)
            ->where('tipo_pedido', 'domicilio')
            ->whereDate('fecha', today())
            ->with('detalles')
            ->latest()
            ->get()
            ->map(fn($f) => [
                'id'        => 'F-' . $f->id,
                'origen'    => 'factura',
                'mesa_id'   => null,
                'estado'    => 'entregado',
                'cliente'   => $f->dom_nombre ?: ($f->cliente_nombre ?? 'Domicilio'),
                'telefono'  => $f->dom_telefono ?? '',
                'direccion' => $f->dom_direccion ?: '-',
                'total'     => (float) $f->total,
                'subtotal'  => (float) ($f->total - ($f->dom_costo_domicilio ?? 0) - ($f->dom_costo_desechables ?? 0)),
                'dom_costo' => (float) ($f->dom_costo_domicilio ?? 0),
                'desechables' => (float) ($f->dom_costo_desechables ?? 0),
                'hora'      => $f->fecha instanceof \Carbon\Carbon ? $f->fecha->format('h:i A') : \Carbon\Carbon::parse($f->fecha)->format('h:i A'),
                'observaciones' => $f->dom_observaciones ?? null,
                'repartidor' => null,
                'numero_cocina' => null,
                'items'     => $f->detalles->map(fn($d) => [
                    'nombre'   => $d->descripcion_larga ?: ('Producto #' . $d->producto_id),
                    'cantidad' => (float) $d->cantidad,
                    'precio'   => (float) $d->precio,
                    'subtotal' => (float) $d->subtotal,
                    'nota'     => null,
                ])->values()->toArray(),
            ]);

        $panelDom = Domicilio::where('empresa_id', $empresaId)
            ->whereDate('created_at', today())
            ->with('repartidor')
            ->get()
            ->map(fn($d) => [
                'id'        => 'D-' . $d->id,
                'origen'    => 'panel',
                'mesa_id'   => null,
                'estado'    => $d->estado,
                'cliente'   => $d->cliente_nombre,
                'telefono'  => $d->cliente_telefono ?? '',
                'direccion' => $d->direccion ?? '-',
                'total'     => (float) $d->total_pedido + $d->valor_domicilio,
                'subtotal'  => (float) $d->total_pedido,
                'dom_costo' => (float) $d->valor_domicilio,
                'desechables' => 0.0,
                'hora'      => $d->created_at->format('h:i A'),
                'observaciones' => $d->observaciones ?? null,
                'repartidor' => $d->repartidor?->name,
                'numero_cocina' => null,
                'items'     => [],
            ]);

        return collect($enCocina)
            ->merge($panelDom->whereNotIn('estado', ['entregado', 'cancelado']))
            ->concat(collect($despachados)->merge($panelDom->whereIn('estado', ['entregado', 'cancelado'])))
            ->toArray();
    }

    public function getMesasProperty()
    {
        return Mesa::where('empresa_id', $this->empresaId())
            ->where('activo', true)
            ->where('codigo', 'not like', 'DOMV-%')
            ->where('codigo', 'not like', 'TALL-%')
            ->when($this->zonaFiltro, fn ($q) => $q->where('zona', $this->zonaFiltro))
            ->with(['ordenes' => fn ($q) => $q->whereIn('estado', ['abierta', 'en_preparacion'])->with('items')->latest()])
            ->orderBy('codigo')
            ->get();
    }

    public function nuevoDomicilio(): void
    {
        if (! $this->puedeGestionarDomicilios) {
            return;
        }

        $empresaId = $this->empresaId();

        $mesaLibre = Mesa::where('empresa_id', $empresaId)
            ->where('codigo', 'like', 'DOMV-%')
            ->whereDoesntHave('ordenes', fn ($q) => $q->whereIn('estado', ['abierta', 'en_preparacion']))
            ->first();

        if (! $mesaLibre) {
            $siguiente = Mesa::where('empresa_id', $empresaId)
                ->where('codigo', 'like', 'DOMV-%')
                ->count() + 1;

            $mesaLibre = Mesa::create([
                'empresa_id' => $empresaId,
                'codigo'     => 'DOMV-' . $siguiente,
                'nombre'     => 'Domicilio ' . $siguiente,
                'zona'       => null,
                'capacidad'  => null,
                'estado'     => 'libre',
                'activo'     => true,
            ]);
        }

        $this->redirect(route('pos.mesa', $mesaLibre->id));
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

        $mesaOcupada = OrdenMesa::where('mesa_id', $orden->mesa_id)
            ->where('estado', 'abierta')
            ->exists();

        if ($mesaOcupada) {
            $this->dispatch('warning', 'La mesa tiene una orden activa. Ciérrela antes de cobrar esta cuenta en espera.');
            return;
        }

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

    public function abrirFormTaller(): void
    {
        $this->tallerNombre      = '';
        $this->tallerTelefono    = '';
        $this->tallerPlaca       = '';
        $this->tallerMarca       = '';
        $this->tallerModelo      = '';
        $this->tallerColor       = '';
        $this->tallerKm          = '';
        $this->tallerDiagnostico = '';
        $this->mostrarFormTaller = true;
    }

    public function guardarOrdenTaller(): void
    {
        $this->validate([
            'tallerNombre' => 'required|string|max:150',
            'tallerPlaca'  => 'required|string|max:20',
        ], [
            'tallerNombre.required' => 'El nombre del cliente es obligatorio.',
            'tallerPlaca.required'  => 'La placa del vehículo es obligatoria.',
        ]);

        \App\Models\TallerOrden::create([
            'empresa_id'     => $this->empresaId(),
            'cliente_nombre' => $this->tallerNombre,
            'cliente_telefono' => $this->tallerTelefono ?: null,
            'placa'          => strtoupper($this->tallerPlaca),
            'marca'          => $this->tallerMarca ?: null,
            'modelo'         => $this->tallerModelo ?: null,
            'color'          => $this->tallerColor ?: null,
            'km'             => $this->tallerKm ? (int) $this->tallerKm : null,
            'diagnostico'    => $this->tallerDiagnostico ?: null,
            'estado'         => 'pendiente',
        ]);

        $this->mostrarFormTaller = false;
        $this->vistaActual = 'taller';
    }

    public function facturarOrdenTaller(int $id): void
    {
        $empresaId = $this->empresaId();

        $orden = \App\Models\TallerOrden::where('empresa_id', $empresaId)
            ->findOrFail($id);

        $mesaLibre = Mesa::where('empresa_id', $empresaId)
            ->where('codigo', 'like', 'TALL-%')
            ->whereDoesntHave('ordenes', fn ($q) => $q->whereIn('estado', ['abierta', 'en_preparacion']))
            ->first();

        if (! $mesaLibre) {
            $siguiente = Mesa::where('empresa_id', $empresaId)
                ->where('codigo', 'like', 'TALL-%')
                ->count() + 1;

            $mesaLibre = Mesa::create([
                'empresa_id' => $empresaId,
                'codigo'     => 'TALL-' . $siguiente,
                'nombre'     => 'Taller ' . $siguiente,
                'zona'       => null,
                'capacidad'  => null,
                'estado'     => 'libre',
                'activo'     => true,
            ]);
        }

        $this->redirect(route('pos.mesa', $mesaLibre->id) . '?taller_orden=' . $orden->id);
    }

    public function render()
    {
        return view('livewire.panel-mesas', [
            'mesas'             => $this->mesas,
            'zonas'             => $this->zonas,
            'cuentasPendientes' => $this->cuentasPendientes,
            'usaDomicilios'     => $this->usaDomicilios,
            'puedeGestionarDomicilios' => $this->puedeGestionarDomicilios,
            'usaTaller'         => $this->usaTaller,
            'domiciliosHoy'     => $this->mostrarDomicilios ? $this->domiciliosHoy : [],
            'ordenesTaller'     => $this->vistaActual === 'taller' ? $this->ordenesTaller : collect(),
        ]);
    }
}
