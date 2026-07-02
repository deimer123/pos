<?php

namespace App\Livewire;

use App\Models\FacturaDetalle;
use App\Models\LiquidacionMecanico;
use App\Models\LiquidacionMecanicoDetalle;
use App\Models\Mecanico;
use App\Models\Mesa;
use App\Models\Product;
use App\Models\TallerOrden;
use App\Models\TallerRepuesto;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class TallerPanel extends Component
{
    // Lista
    public string $filtroEstado = 'activas';
    public string $busqueda     = '';
    public string $fechaDesde   = '';
    public string $fechaHasta   = '';

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

    // ── Vista activa: 'ordenes' | 'mecanicos'
    public string $vistaActiva = 'ordenes';

    // ── Liquidación
    public bool  $modalLiquidacion    = false;
    public ?int  $liquidarMecanicoId  = null;
    public string $liqFechaDesde      = '';
    public string $liqFechaHasta      = '';
    public string $liqMedioPago       = 'efectivo';
    public string $liqNotas           = '';
    public array  $liqServicios       = []; // pending services preview
    public float  $liqTotalServicios  = 0;
    public float  $liqMontoMecanico   = 0;
    public float  $liqPorcentajeMecanico = 0;

    private function empresaId(): int
    {
        return auth()->user()->getEmpresaActualId();
    }

    public function getOrdenesProperty()
    {
        return TallerOrden::where('empresa_id', $this->empresaId())
            // Cobradas: mostrar por semana si no hay rango manual
            ->when($this->filtroEstado === 'entregado' && !$this->fechaDesde && !$this->fechaHasta,
                fn($q) => $q->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
            )
            ->when($this->fechaDesde, fn($q) => $q->whereDate('created_at', '>=', $this->fechaDesde))
            ->when($this->fechaHasta, fn($q) => $q->whereDate('created_at', '<=', $this->fechaHasta))
            ->when($this->filtroEstado === 'activas', fn($q) => $q->where('estado', '!=', 'entregado'))
            ->when($this->filtroEstado && $this->filtroEstado !== 'activas', fn($q) => $q->where('estado', $this->filtroEstado))
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

    public function limpiarFechas(): void
    {
        $this->fechaDesde = '';
        $this->fechaHasta = '';
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

    public function guardarNotaTrabajo(int $id, string $nota): void
    {
        TallerOrden::where('empresa_id', $this->empresaId())
            ->where('id', $id)
            ->update(['nota_trabajo' => trim($nota) ?: null]);
    }

    public function abrirOrden(int $id): void
    {
        $this->redirect(route('pos') . '?taller=' . $id);
    }

    public function eliminarOrden(int $id): void
    {
        TallerOrden::where('empresa_id', $this->empresaId())->findOrFail($id)->delete();
    }

    // ── Mecánicos / Liquidación ──────────────────────────────────────────────

    public function getMecanicosProperty()
    {
        $empresaId = $this->empresaId();
        $mecanicos = Mecanico::where('empresa_id', $empresaId)->where('activo', true)->get();

        return $mecanicos->map(function (Mecanico $m) use ($empresaId) {
            $pendiente = $this->pendienteMecanico($m->id, $empresaId);
            $m->total_pendiente   = $pendiente['total_servicios'];
            $m->monto_pendiente   = $pendiente['monto_mecanico'];
            $m->porcentaje_prom   = $pendiente['porcentaje'];
            $m->servicios_pending = $pendiente['count'];
            return $m;
        });
    }

    private function pendienteMecanico(int $mecanicoId, int $empresaId): array
    {
        $rows = DB::table('factura_detalles as fd')
            ->join('facturas as f', 'f.id', '=', 'fd.factura_id')
            ->leftJoin('liquidacion_mecanico_detalles as lmd', 'lmd.factura_detalle_id', '=', 'fd.id')
            ->where('f.empresa_id', $empresaId)
            ->where('fd.mecanico_id', $mecanicoId)
            ->where('fd.tipo_servicio', 'propio')
            ->whereNull('lmd.id')
            ->select(DB::raw('SUM(fd.subtotal) as total_servicios, COUNT(fd.id) as total_count, AVG(COALESCE(100 - fd.porcentaje_empresa, 100)) as porcentaje_prom'))
            ->first();

        $totalServicios = (float) ($rows->total_servicios ?? 0);
        $pct            = (float) ($rows->porcentaje_prom ?? 0);
        return [
            'total_servicios' => $totalServicios,
            'monto_mecanico'  => round($totalServicios * $pct / 100, 2),
            'porcentaje'      => $pct,
            'count'           => (int) ($rows->total_count ?? 0),
        ];
    }

    public function abrirLiquidacion(int $mecanicoId): void
    {
        $this->liquidarMecanicoId = $mecanicoId;
        $this->liqFechaDesde      = now()->startOfMonth()->toDateString();
        $this->liqFechaHasta      = now()->toDateString();
        $this->liqMedioPago       = 'efectivo';
        $this->liqNotas           = '';
        $this->calcularLiquidacion();
        $this->modalLiquidacion   = true;
    }

    public function updatedLiqFechaDesde(): void { $this->calcularLiquidacion(); }
    public function updatedLiqFechaHasta(): void  { $this->calcularLiquidacion(); }

    private function calcularLiquidacion(): void
    {
        if (! $this->liquidarMecanicoId) return;

        $empresaId = $this->empresaId();
        $q = DB::table('factura_detalles as fd')
            ->join('facturas as f', 'f.id', '=', 'fd.factura_id')
            ->leftJoin('liquidacion_mecanico_detalles as lmd', 'lmd.factura_detalle_id', '=', 'fd.id')
            ->where('f.empresa_id', $empresaId)
            ->where('fd.mecanico_id', $this->liquidarMecanicoId)
            ->where('fd.tipo_servicio', 'propio')
            ->whereNull('lmd.id');

        if ($this->liqFechaDesde) $q->whereDate('f.fecha', '>=', $this->liqFechaDesde);
        if ($this->liqFechaHasta) $q->whereDate('f.fecha', '<=', $this->liqFechaHasta);

        $rows = $q->select([
            'fd.id',
            'f.id as factura_id',
            DB::raw("DATE_FORMAT(f.fecha,'%d/%m/%Y') as fecha_fmt"),
            'fd.subtotal',
            'fd.porcentaje_empresa',
        ])->get();

        $this->liqServicios = $rows->map(fn($r) => [
            'detalle_id'        => $r->id,
            'factura_id'        => $r->factura_id,
            'fecha'             => $r->fecha_fmt,
            'subtotal'          => (float) $r->subtotal,
            'pct_empresa'       => (float) ($r->porcentaje_empresa ?? 0),
            'monto_mecanico'    => round((float) $r->subtotal * (100 - (float) ($r->porcentaje_empresa ?? 0)) / 100, 2),
        ])->toArray();

        $this->liqTotalServicios    = collect($this->liqServicios)->sum('subtotal');
        $this->liqMontoMecanico     = collect($this->liqServicios)->sum('monto_mecanico');
        $this->liqPorcentajeMecanico = $this->liqTotalServicios > 0
            ? round($this->liqMontoMecanico / $this->liqTotalServicios * 100, 2)
            : 0;
    }

    public function confirmarLiquidacion(): void
    {
        if (! $this->liquidarMecanicoId || empty($this->liqServicios)) {
            $this->dispatch('notify', type: 'error', message: 'No hay servicios para liquidar en el período seleccionado.');
            return;
        }

        DB::transaction(function () {
            $liquidacion = LiquidacionMecanico::create([
                'empresa_id'          => $this->empresaId(),
                'mecanico_id'         => $this->liquidarMecanicoId,
                'fecha_desde'         => $this->liqFechaDesde ?: now()->startOfMonth()->toDateString(),
                'fecha_hasta'         => $this->liqFechaHasta ?: now()->toDateString(),
                'total_servicios'     => $this->liqTotalServicios,
                'porcentaje_mecanico' => $this->liqPorcentajeMecanico,
                'monto_mecanico'      => $this->liqMontoMecanico,
                'estado'              => 'pagado',
                'fecha_pago'          => today()->toDateString(),
                'medio_pago'          => $this->liqMedioPago,
                'notas'               => $this->liqNotas ?: null,
                'user_id'             => auth()->id(),
            ]);

            foreach ($this->liqServicios as $svc) {
                LiquidacionMecanicoDetalle::create([
                    'liquidacion_id'       => $liquidacion->id,
                    'factura_detalle_id'   => $svc['detalle_id'],
                    'subtotal_servicio'    => $svc['subtotal'],
                    'monto_mecanico'       => $svc['monto_mecanico'],
                ]);
            }
        });

        $this->modalLiquidacion = false;
        $this->liquidarMecanicoId = null;
        $this->liqServicios = [];
        $this->dispatch('notify', type: 'success', message: 'Liquidación registrada exitosamente.');
    }

    public function getLiquidacionesHistoricoProperty()
    {
        if (! $this->liquidarMecanicoId) return collect();
        return LiquidacionMecanico::where('mecanico_id', $this->liquidarMecanicoId)
            ->orderByDesc('created_at')->limit(10)->get();
    }

    public function render()
    {
        return view('livewire.taller-panel', [
            'ordenes'            => $this->ordenes,
            'productosSugeridos' => $this->productosSugeridos,
            'mecanicos'          => $this->mecanicos,
        ]);
    }
}
