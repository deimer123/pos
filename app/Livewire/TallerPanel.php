<?php

namespace App\Livewire;

use App\Models\Caja;
use App\Models\FacturaDetalle;
use App\Models\Gasto;
use App\Models\LiquidacionMecanico;
use App\Models\LiquidacionMecanicoDetalle;
use App\Models\Mecanico;
use App\Models\MecanicoPrestamo;
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

    // ── Modal Servicio (crear/editar servicio de mecánico)
    public bool   $modalServicio      = false;
    public ?int   $servicioId         = null;   // null = crear, int = editar
    public ?int   $svcMecanicoId      = null;
    public string $svcNombre          = '';
    public string $svcPrecio          = '';
    public string $svcCosto           = '';   // solo para servicios a terceros
    public string $svcPctEmpresa      = '0';
    public string $svcTipoServicio    = 'propio';
    public string $svcTerceroNombre   = '';
    public bool   $svcBloquearTipo    = false;   // true = creando desde un mecanico, no puede cambiarse a tercero
    public ?int   $svcExpandMecanico  = null;   // mecánico cuya lista de servicios está expandida

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
    public float  $liqPrestamosPendientes = 0;
    public float  $liqMontoNeto          = 0;

    // ── Historial de servicios por mecánico (para revisar reclamos, cobrado/pendiente)
    public bool   $modalHistorialMecanico = false;
    public ?int   $histMecanicoId         = null;
    public string $histDesde              = '';
    public string $histHasta              = '';

    // ── Historial de servicios a terceros
    public bool   $modalHistorialTerceros = false;
    public string $histTercDesde          = '';
    public string $histTercHasta          = '';

    // ── Préstamos a mecánicos
    public bool   $modalPrestamo      = false;
    public ?int   $prestamoMecanicoId = null;
    public string $prestamoMonto      = '';
    public string $prestamoNota       = '';

    // ── Cierre de Caja de Mecánicos (servicios propios + terceros, aparte del POS)
    public bool   $modalCajaMecanicos = false;
    public string $cajaMecDesde       = '';
    public string $cajaMecHasta       = '';
    public string $cajaMecMontoCierre = '';

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
            $m->total_pendiente     = $pendiente['total_servicios'];
            $m->monto_pendiente     = $pendiente['monto_mecanico'];
            $m->porcentaje_prom     = $pendiente['porcentaje'];
            $m->servicios_pending   = $pendiente['count'];
            $m->prestamos_pendientes = $this->prestamosPendientesMecanico($m->id);
            $m->monto_neto          = $m->monto_pendiente - $m->prestamos_pendientes;
            return $m;
        });
    }

    public function getResumenMecanicosProperty(): array
    {
        $empresaId = $this->empresaId();

        $pendiente = DB::table('factura_detalles as fd')
            ->join('facturas as f', 'f.id', '=', 'fd.factura_id')
            ->leftJoin('liquidacion_mecanico_detalles as lmd', 'lmd.factura_detalle_id', '=', 'fd.id')
            ->where('f.empresa_id', $empresaId)
            ->where('fd.tipo_servicio', 'propio')
            ->whereNotNull('fd.mecanico_id')
            ->whereNull('lmd.id')
            ->select(DB::raw('
                COALESCE(SUM(fd.subtotal), 0) as total_servicios,
                COALESCE(SUM(fd.subtotal * (100 - COALESCE(fd.porcentaje_empresa, 0)) / 100), 0) as monto_mecanicos
            '))
            ->first();

        $totalPendiente    = (float) ($pendiente->total_servicios ?? 0);
        $aLiquidar         = (float) ($pendiente->monto_mecanicos ?? 0);
        $gananciaPendiente = $totalPendiente - $aLiquidar;

        $liquidadoHistorico       = (float) LiquidacionMecanico::where('empresa_id', $empresaId)->sum('monto_mecanico');
        $totalServiciosLiquidados = (float) LiquidacionMecanico::where('empresa_id', $empresaId)->sum('total_servicios');
        $gananciaLiquidada        = $totalServiciosLiquidados - $liquidadoHistorico;

        $prestamosPendientes = (float) MecanicoPrestamo::whereHas('mecanico', fn ($q) => $q->where('empresa_id', $empresaId))
            ->where('estado', 'pendiente')
            ->sum('monto');

        return [
            'total_pendiente'      => $totalPendiente,
            'a_liquidar'           => $aLiquidar,
            'ganancia_pendiente'   => $gananciaPendiente,
            'liquidado_historico'  => $liquidadoHistorico,
            'ganancia_liquidada'   => $gananciaLiquidada,
            'prestamos_pendientes' => $prestamosPendientes,
            'a_liquidar_neto'      => $aLiquidar - $prestamosPendientes,
        ];
    }

    // ── Cierre de Caja de Mecánicos: servicios propios + terceros cobrados en
    // el POS y lo pagado a mecánicos (liquidaciones + préstamos) en un rango
    // de fechas. Es aparte del cierre de caja de productos del POS, porque el
    // dinero de servicios va a un cajón/caja fisica distinta.
    public function abrirCajaMecanicos(): void
    {
        $this->cajaMecDesde       = now()->startOfDay()->toDateString();
        $this->cajaMecHasta       = now()->toDateString();
        $this->cajaMecMontoCierre = '';
        $this->modalCajaMecanicos = true;
    }

    public function calcularCajaMecanicos(): array
    {
        $empresaId = $this->empresaId();
        $desde = $this->cajaMecDesde ?: now()->startOfDay()->toDateString();
        $hasta = $this->cajaMecHasta ?: now()->toDateString();

        // Para servicios a terceros solo cuenta la ganancia de la empresa
        // (venta - costo): el resto es plata que pasa directo al tercero y
        // nunca entra a esta caja. Para servicios propios cuenta el valor
        // completo cobrado (la parte del mecánico se descuenta aparte, como
        // "pagado a mecánicos", cuando se liquida).
        $montoCobradoSql = "SUM(CASE WHEN fd.tipo_servicio = 'tercero' THEN fd.subtotal * COALESCE(fd.porcentaje_empresa, 0) / 100 ELSE fd.subtotal END)";

        $qServicios = DB::table('factura_detalles as fd')
            ->join('facturas as f', 'f.id', '=', 'fd.factura_id')
            ->join('products as p', function ($j) use ($empresaId) {
                $j->on('p.id_producto', '=', 'fd.producto_id')->where('p.empresa_id', '=', $empresaId);
            })
            ->where('f.empresa_id', $empresaId)
            ->where('p.tipo_producto', 'servicio')
            ->whereDate('f.fecha', '>=', $desde)
            ->whereDate('f.fecha', '<=', $hasta);

        $serviciosEfectivo      = (float) ((clone $qServicios)->where('f.tipo_pago', 'contado')->where('f.medio_pago', 'efectivo')->rawValue($montoCobradoSql) ?? 0);
        $serviciosTransferencia = (float) ((clone $qServicios)->where('f.tipo_pago', 'contado')->where('f.medio_pago', 'transferencia')->rawValue($montoCobradoSql) ?? 0);
        $serviciosCredito       = (float) ((clone $qServicios)->where('f.tipo_pago', 'credito')->rawValue($montoCobradoSql) ?? 0);
        $serviciosTotal         = $serviciosEfectivo + $serviciosTransferencia + $serviciosCredito;

        $qPagos = Gasto::where('empresa_id', $empresaId)
            ->whereIn('categoria', ['liquidacion_mecanico', 'prestamo_mecanico'])
            ->whereDate('fecha', '>=', $desde)
            ->whereDate('fecha', '<=', $hasta);

        $pagadoEfectivo      = (float) (clone $qPagos)->where('metodo_pago', 'Efectivo')->sum('monto');
        $pagadoTransferencia = (float) (clone $qPagos)->whereIn('metodo_pago', ['Transferencia', 'Nequi'])->sum('monto');
        $pagadoTotal         = $pagadoEfectivo + $pagadoTransferencia;

        $efectivo = (float) $serviciosEfectivo - $pagadoEfectivo;
        $transferencia = (float) $serviciosTransferencia - $pagadoTransferencia;

        // Desglose por mecánico propio: solo servicios AÚN NO liquidados
        // (no queremos que se acumule la ganancia de servicios que ya se
        // le pagaron al mecánico en una liquidación anterior).
        $porMecanico = DB::table('factura_detalles as fd')
            ->join('facturas as f', 'f.id', '=', 'fd.factura_id')
            ->join('mecanicos as m', 'm.id', '=', 'fd.mecanico_id')
            ->leftJoin('liquidacion_mecanico_detalles as lmd', 'lmd.factura_detalle_id', '=', 'fd.id')
            ->where('f.empresa_id', $empresaId)
            ->where('m.empresa_id', $empresaId)
            ->where('fd.tipo_servicio', 'propio')
            ->whereNull('lmd.id')
            ->whereDate('f.fecha', '>=', $desde)
            ->whereDate('f.fecha', '<=', $hasta)
            ->groupBy('fd.mecanico_id', 'm.nombre')
            ->orderByDesc(DB::raw('SUM(fd.subtotal)'))
            ->get(['fd.mecanico_id', 'm.nombre', DB::raw('SUM(fd.subtotal) as monto')])
            ->map(fn ($r) => ['mecanico_id' => $r->mecanico_id, 'nombre' => $r->nombre, 'monto' => (float) $r->monto]);

        // Ganancia de la empresa en el rango: para propio, el % de empresa
        // sobre lo cobrado SOLO de servicios aún no liquidados; para
        // tercero, el "cobrado" ya es solo la ganancia (ver $montoCobradoSql
        // arriba) y no tiene liquidación formal.
        $propioGanancia = (float) (DB::table('factura_detalles as fd')
            ->join('facturas as f', 'f.id', '=', 'fd.factura_id')
            ->leftJoin('liquidacion_mecanico_detalles as lmd', 'lmd.factura_detalle_id', '=', 'fd.id')
            ->where('f.empresa_id', $empresaId)
            ->where('fd.tipo_servicio', 'propio')
            ->whereNull('lmd.id')
            ->whereDate('f.fecha', '>=', $desde)
            ->whereDate('f.fecha', '<=', $hasta)
            ->rawValue('SUM(fd.subtotal * COALESCE(fd.porcentaje_empresa, 0) / 100)') ?? 0);

        $tercerosMonto = (float) ((clone $qServicios)
            ->where('fd.tipo_servicio', 'tercero')
            ->rawValue($montoCobradoSql) ?? 0);

        // Servicios por liquidar: saldo pendiente actual (no depende del
        // rango de fechas seleccionado, es un saldo acumulado). El detalle
        // de qué ya se liquidó se consulta en el Historial de cada mecánico.
        $pendienteLiquidar = (float) $this->getResumenMecanicosProperty()['a_liquidar_neto'];

        return [
            'servicios_efectivo'      => (float) $serviciosEfectivo,
            'servicios_transferencia' => (float) $serviciosTransferencia,
            'servicios_credito'       => (float) $serviciosCredito,
            'servicios_total'         => (float) $serviciosTotal,
            'pagado_efectivo'         => $pagadoEfectivo,
            'pagado_transferencia'    => $pagadoTransferencia,
            'pagado_total'            => $pagadoTotal,
            'efectivo'                => $efectivo,
            'transferencia'           => $transferencia,
            'efectivo_esperado'       => $efectivo,
            'queda_empresa'           => $serviciosTotal - $pagadoTotal,
            'por_mecanico'            => $porMecanico,
            'terceros_monto'          => $tercerosMonto,
            'ganancia_total'          => $propioGanancia + $tercerosMonto,
            'pendiente_liquidar'      => $pendienteLiquidar,
        ];
    }

    private function prestamosPendientesMecanico(int $mecanicoId): float
    {
        return (float) MecanicoPrestamo::where('mecanico_id', $mecanicoId)
            ->where('estado', 'pendiente')
            ->sum('monto');
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

    // Historial de servicios de un mecanico (liquidados y pendientes) por rango
    // de fechas, para revisar reclamos: que se cobro (ya liquidado) y que no.
    public function abrirHistorialMecanico(int $mecanicoId): void
    {
        $this->histMecanicoId          = $mecanicoId;
        $this->histDesde               = now()->startOfMonth()->toDateString();
        $this->histHasta               = now()->toDateString();
        $this->modalHistorialMecanico  = true;
    }

    public function historialMecanico(): \Illuminate\Support\Collection
    {
        if (! $this->histMecanicoId) {
            return collect();
        }

        $empresaId = $this->empresaId();

        $q = DB::table('factura_detalles as fd')
            ->join('facturas as f', 'f.id', '=', 'fd.factura_id')
            ->leftJoin('liquidacion_mecanico_detalles as lmd', 'lmd.factura_detalle_id', '=', 'fd.id')
            ->leftJoin('liquidaciones_mecanico as lm', 'lm.id', '=', 'lmd.liquidacion_id')
            ->where('f.empresa_id', $empresaId)
            ->where('fd.mecanico_id', $this->histMecanicoId)
            ->where('fd.tipo_servicio', 'propio');

        if ($this->histDesde) $q->whereDate('f.fecha', '>=', $this->histDesde);
        if ($this->histHasta) $q->whereDate('f.fecha', '<=', $this->histHasta);

        return $q->select([
                'fd.id',
                'f.id as factura_id',
                'f.tipo_factura',
                'f.factus_number',
                DB::raw("DATE_FORMAT(f.fecha,'%d/%m/%Y %H:%i') as fecha_fmt"),
                'fd.descripcion_larga',
                'fd.subtotal',
                'fd.porcentaje_empresa',
                'lm.fecha_pago',
                'lm.estado as liquidacion_estado',
            ])
            ->orderByDesc('f.fecha')
            ->get()
            ->map(function ($r) {
                $numeroVisual = ($r->tipo_factura === 'electronica' && filled($r->factus_number))
                    ? (string) $r->factus_number
                    : (($r->tipo_factura === 'salida' ? 'SAL-' : 'FAC-') . str_pad((string) $r->factura_id, 6, '0', STR_PAD_LEFT));

                return (object) [
                    'id'               => $r->id,
                    'factura_id'       => $r->factura_id,
                    'numero_visual'    => $numeroVisual,
                    'fecha'            => $r->fecha_fmt,
                    'descripcion'      => $r->descripcion_larga,
                    'subtotal'         => (float) $r->subtotal,
                    'monto_mecanico'   => round((float) $r->subtotal * (100 - (float) ($r->porcentaje_empresa ?? 0)) / 100, 2),
                    'liquidado'        => (bool) $r->fecha_pago,
                    'fecha_pago'       => $r->fecha_pago,
                ];
            });
    }

    // Historial de servicios a terceros (no atados a un mecánico propio) por
    // rango de fechas, para revisar reclamos: qué se cobró y a qué tercero.
    public function abrirHistorialTerceros(): void
    {
        $this->histTercDesde          = now()->startOfMonth()->toDateString();
        $this->histTercHasta          = now()->toDateString();
        $this->modalHistorialTerceros = true;
    }

    public function historialTerceros(): \Illuminate\Support\Collection
    {
        $empresaId = $this->empresaId();

        $q = DB::table('factura_detalles as fd')
            ->join('facturas as f', 'f.id', '=', 'fd.factura_id')
            ->leftJoin('products as p', function ($j) use ($empresaId) {
                $j->on('p.id_producto', '=', 'fd.producto_id')->where('p.empresa_id', '=', $empresaId);
            })
            ->where('f.empresa_id', $empresaId)
            ->where('fd.tipo_servicio', 'tercero');

        if ($this->histTercDesde) $q->whereDate('f.fecha', '>=', $this->histTercDesde);
        if ($this->histTercHasta) $q->whereDate('f.fecha', '<=', $this->histTercHasta);

        return $q->select([
                'fd.id',
                'f.id as factura_id',
                'f.tipo_factura',
                'f.factus_number',
                DB::raw("DATE_FORMAT(f.fecha,'%d/%m/%Y %H:%i') as fecha_fmt"),
                'fd.descripcion_larga',
                'fd.subtotal',
                'fd.porcentaje_empresa',
                'p.tercero_nombre',
            ])
            ->orderByDesc('f.fecha')
            ->get()
            ->map(function ($r) {
                $numeroVisual = ($r->tipo_factura === 'electronica' && filled($r->factus_number))
                    ? (string) $r->factus_number
                    : (($r->tipo_factura === 'salida' ? 'SAL-' : 'FAC-') . str_pad((string) $r->factura_id, 6, '0', STR_PAD_LEFT));

                $montoEmpresa = round((float) $r->subtotal * (float) ($r->porcentaje_empresa ?? 0) / 100, 2);

                return (object) [
                    'id'             => $r->id,
                    'factura_id'     => $r->factura_id,
                    'numero_visual'  => $numeroVisual,
                    'fecha'          => $r->fecha_fmt,
                    'descripcion'    => $r->descripcion_larga,
                    'tercero_nombre' => $r->tercero_nombre,
                    'subtotal'       => (float) $r->subtotal,
                    'monto_empresa'  => $montoEmpresa,
                    'monto_tercero'  => round((float) $r->subtotal - $montoEmpresa, 2),
                ];
            });
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

        $this->liqPrestamosPendientes = $this->prestamosPendientesMecanico($this->liquidarMecanicoId);
        $this->liqMontoNeto            = $this->liqMontoMecanico - $this->liqPrestamosPendientes;
    }

    public function confirmarLiquidacion(): void
    {
        if (! $this->liquidarMecanicoId || empty($this->liqServicios)) {
            $this->dispatch('notify', type: 'error', message: 'No hay servicios para liquidar en el período seleccionado.');
            return;
        }

        DB::transaction(function () {
            // Préstamos pendientes del mecánico: se descuentan de esta liquidación
            $prestamosPendientes = MecanicoPrestamo::where('mecanico_id', $this->liquidarMecanicoId)
                ->where('estado', 'pendiente')
                ->get();
            $prestamosDescontados = (float) $prestamosPendientes->sum('monto');
            $montoNeto = max(0, $this->liqMontoMecanico - $prestamosDescontados);

            $liquidacion = LiquidacionMecanico::create([
                'empresa_id'          => $this->empresaId(),
                'mecanico_id'         => $this->liquidarMecanicoId,
                'fecha_desde'         => $this->liqFechaDesde ?: now()->startOfMonth()->toDateString(),
                'fecha_hasta'         => $this->liqFechaHasta ?: now()->toDateString(),
                'total_servicios'     => $this->liqTotalServicios,
                'porcentaje_mecanico' => $this->liqPorcentajeMecanico,
                'monto_mecanico'      => $this->liqMontoMecanico,
                'prestamos_descontados' => $prestamosDescontados,
                'monto_neto'          => $montoNeto,
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

            // Marcar préstamos pendientes como descontados en esta liquidación
            MecanicoPrestamo::whereIn('id', $prestamosPendientes->pluck('id'))
                ->update(['estado' => 'descontado', 'liquidacion_id' => $liquidacion->id]);

            // Registrar salida de caja automática (neta, después de descontar préstamos)
            $mecanicoNombre = Mecanico::find($this->liquidarMecanicoId)?->nombre ?? 'Mecánico';
            $cajaActiva = Caja::where('empresa_id', $this->empresaId())
                ->where('estado', 'abierta')
                ->latest('opened_at')
                ->first();

            $medioPagoGasto = match(strtolower($this->liqMedioPago ?? '')) {
                'transferencia', 'nequi' => 'Transferencia',
                default => 'Efectivo',
            };

            if ($montoNeto > 0) {
                Gasto::create([
                    'id_gasto'    => Gasto::where('empresa_id', $this->empresaId())->max('id_gasto') + 1,
                    'empresa_id'  => $this->empresaId(),
                    'tipo'        => 'salida',
                    'categoria'   => 'liquidacion_mecanico',
                    'descripcion' => 'Liquidación mecánico: ' . $mecanicoNombre
                        . ($prestamosDescontados > 0 ? ' (descontado préstamo de $' . number_format($prestamosDescontados, 0, ',', '.') . ')' : ''),
                    'monto'       => $montoNeto,
                    'fecha'       => today()->toDateString(),
                    'metodo_pago' => $medioPagoGasto,
                    'observacion' => $this->liqNotas ?: null,
                    'created_by'  => auth()->id(),
                    'caja_id'     => $cajaActiva?->id,
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

    // ── Préstamos a mecánicos ────────────────────────────────────────────────

    public function abrirPrestamo(int $mecanicoId): void
    {
        $this->prestamoMecanicoId = $mecanicoId;
        $this->prestamoMonto      = '';
        $this->prestamoNota       = '';
        $this->modalPrestamo      = true;
    }

    public function guardarPrestamo(): void
    {
        $this->validate([
            'prestamoMonto' => 'required|numeric|min:0.01',
        ], [
            'prestamoMonto.required' => 'El monto del préstamo es obligatorio.',
            'prestamoMonto.min'      => 'El monto debe ser mayor a 0.',
        ]);

        if (! $this->prestamoMecanicoId) return;

        DB::transaction(function () {
            $mecanico = Mecanico::find($this->prestamoMecanicoId);
            $monto    = (float) $this->prestamoMonto;

            MecanicoPrestamo::create([
                'empresa_id'  => $this->empresaId(),
                'mecanico_id' => $this->prestamoMecanicoId,
                'monto'       => $monto,
                'fecha'       => today()->toDateString(),
                'nota'        => $this->prestamoNota ?: null,
                'estado'      => 'pendiente',
                'user_id'     => auth()->id(),
            ]);

            // Registrar salida de caja automática
            $cajaActiva = Caja::where('empresa_id', $this->empresaId())
                ->where('estado', 'abierta')
                ->latest('opened_at')
                ->first();

            Gasto::create([
                'id_gasto'    => Gasto::where('empresa_id', $this->empresaId())->max('id_gasto') + 1,
                'empresa_id'  => $this->empresaId(),
                'tipo'        => 'salida',
                'categoria'   => 'prestamo_mecanico',
                'descripcion' => 'Préstamo a mecánico: ' . ($mecanico?->nombre ?? ''),
                'monto'       => $monto,
                'fecha'       => today()->toDateString(),
                'metodo_pago' => 'Efectivo',
                'observacion' => $this->prestamoNota ?: null,
                'created_by'  => auth()->id(),
                'caja_id'     => $cajaActiva?->id,
            ]);
        });

        $this->modalPrestamo = false;
        $this->prestamoMecanicoId = null;
        $this->dispatch('notify', type: 'success', message: 'Préstamo registrado. Se descontará al liquidar.');
    }

    // ── Gestión de Servicios ─────────────────────────────────────────────────

    public function toggleServicios(int $mecanicoId): void
    {
        $this->svcExpandMecanico = $this->svcExpandMecanico === $mecanicoId ? null : $mecanicoId;
    }

    public function serviciosDelMecanico(int $mecanicoId): \Illuminate\Support\Collection
    {
        return Product::where('empresa_id', $this->empresaId())
            ->where('tipo_producto', 'servicio')
            ->where('mecanico_id', $mecanicoId)
            ->get(['id_producto', 'descripcion_larga as nombre', 'precio_venta1', 'tipo_servicio', 'porcentaje_empresa', 'tercero_nombre']);
    }

    // Servicios a terceros: no estan atados a ningun mecanico propio, se
    // gestionan aparte (no aparecen dentro de serviciosDelMecanico porque
    // su mecanico_id siempre es null).
    public function serviciosTerceros(): \Illuminate\Support\Collection
    {
        return Product::where('empresa_id', $this->empresaId())
            ->where('tipo_producto', 'servicio')
            ->where('tipo_servicio', 'tercero')
            ->get(['id_producto', 'descripcion_larga as nombre', 'precio_venta1', 'tipo_servicio', 'porcentaje_empresa', 'tercero_nombre']);
    }

    public function abrirNuevoServicio(int $mecanicoId): void
    {
        $this->servicioId       = null;
        $this->svcMecanicoId    = $mecanicoId;
        $this->svcNombre        = '';
        $this->svcPrecio        = '';
        $this->svcCosto         = '';
        $this->svcPctEmpresa    = '0';
        $this->svcTipoServicio  = 'propio';
        $this->svcTerceroNombre = '';
        $this->svcBloquearTipo  = true;
        $this->modalServicio    = true;
    }

    public function abrirNuevoServicioTercero(): void
    {
        $this->servicioId       = null;
        $this->svcMecanicoId    = null;
        $this->svcNombre        = '';
        $this->svcPrecio        = '';
        $this->svcCosto         = '';
        $this->svcPctEmpresa    = '0';
        $this->svcTipoServicio  = 'tercero';
        $this->svcTerceroNombre = '';
        $this->svcBloquearTipo  = true;
        $this->modalServicio    = true;
    }

    public function abrirEditarServicio(int $productoId): void
    {
        $p = Product::where('empresa_id', $this->empresaId())->where('id_producto', $productoId)->firstOrFail();
        $this->svcBloquearTipo  = false;
        $this->servicioId       = $productoId;
        $this->svcMecanicoId    = $p->mecanico_id;
        $this->svcNombre        = $p->descripcion_larga;
        $this->svcPrecio        = (string) $p->precio_venta1;
        $this->svcCosto         = (string) ($p->precio_costo ?? 0);
        $this->svcPctEmpresa    = (string) ($p->porcentaje_empresa ?? 0);
        $this->svcTipoServicio  = $p->tipo_servicio ?? 'propio';
        $this->svcTerceroNombre = $p->tercero_nombre ?? '';
        $this->modalServicio    = true;
    }

    // Para servicios a terceros: el % de la empresa se calcula solo a partir
    // de precio de costo (lo que cobra el tercero) y precio de venta (lo que
    // se le cobra al cliente), en vez de moverse a mano con la barra.
    public function recalcularPctTercero(): void
    {
        if ($this->svcTipoServicio !== 'tercero') {
            return;
        }

        $venta = (float) $this->svcPrecio;
        $costo = (float) $this->svcCosto;

        if ($venta <= 0) {
            $this->svcPctEmpresa = '0';
            return;
        }

        $pct = (($venta - $costo) / $venta) * 100;
        $this->svcPctEmpresa = (string) round(max(0, min(100, $pct)), 2);
    }

    public function updatedSvcCosto(): void
    {
        $this->recalcularPctTercero();
    }

    public function updatedSvcPrecio(): void
    {
        $this->recalcularPctTercero();
    }

    public function guardarServicio(): void
    {
        $this->validate([
            'svcNombre'  => 'required|min:2',
            'svcPrecio'  => 'required|numeric|min:0',
            'svcPctEmpresa' => 'required|numeric|min:0|max:100',
        ], [
            'svcNombre.required'  => 'El nombre es obligatorio.',
            'svcPrecio.required'  => 'El precio es obligatorio.',
            'svcPrecio.numeric'   => 'El precio debe ser un número.',
            'svcPctEmpresa.max'   => 'El porcentaje no puede superar 100%.',
        ]);

        $empresaId = $this->empresaId();

        $data = [
            'empresa_id'        => $empresaId,
            'descripcion_larga' => trim($this->svcNombre),
            'id_familia1'       => 0,
            'id_familia2'       => 0,
            'tipo_producto'     => 'servicio',
            'tipo_servicio'     => $this->svcTipoServicio,
            'precio_venta1'     => (float) $this->svcPrecio,
            'precio_costo'      => $this->svcTipoServicio === 'tercero' ? (float) $this->svcCosto : 0,
            'porcentaje_empresa'=> (float) $this->svcPctEmpresa,
            'mecanico_id'       => $this->svcTipoServicio === 'propio' ? $this->svcMecanicoId : null,
            'tercero_nombre'    => $this->svcTipoServicio === 'tercero' ? trim($this->svcTerceroNombre) : null,
            'maneja_inventario' => false,
            'iva_venta'         => 0,
            'iva_compra'        => 0,
        ];

        try {
            if ($this->servicioId) {
                Product::where('empresa_id', $empresaId)->where('id_producto', $this->servicioId)->update($data);
            } else {
                $maxId = Product::where('empresa_id', $empresaId)->max('id_producto') ?? 0;
                $data['id_producto'] = $maxId + 1;
                Product::create($data);
            }
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            $this->addError('svcNombre', 'Ya existe un servicio con ese nombre en esta empresa.');
            return;
        }

        $this->modalServicio     = false;
        $this->svcExpandMecanico = $this->svcMecanicoId;
        $this->dispatch('notify', type: 'success', message: $this->servicioId ? 'Servicio actualizado.' : 'Servicio creado y asignado al mecánico.');
    }

    public function eliminarServicio(int $productoId): void
    {
        Product::where('empresa_id', $this->empresaId())->where('id_producto', $productoId)->delete();
        $this->dispatch('notify', type: 'success', message: 'Servicio eliminado.');
    }

    public function render()
    {
        return view('livewire.taller-panel', [
            'ordenes'            => $this->ordenes,
            'productosSugeridos' => $this->productosSugeridos,
            'mecanicos'          => $this->mecanicos,
            'resumenMecanicos'   => $this->resumenMecanicos,
        ]);
    }
}
