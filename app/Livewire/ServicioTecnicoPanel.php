<?php

namespace App\Livewire;

use App\Models\Caja;
use App\Models\Gasto;
use App\Models\LiquidacionMecanico;
use App\Models\LiquidacionMecanicoDetalle;
use App\Models\Mecanico;
use App\Models\MecanicoPrestamo;
use App\Models\Product;
use App\Models\ServicioTecnicoItem;
use App\Models\ServicioTecnicoOrden;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Calco de App\Livewire\TallerPanel para el modulo de Servicio Tecnico de
 * Celulares -- ver ese archivo para el detalle de cada bloque. La unica
 * diferencia real (aparte de los campos de la orden: marca/modelo/imei en
 * vez de placa/km) es que aqui todo el bloque de tecnicos/liquidacion/
 * prestamos/caja filtra por Mecanico::ROL_TECNICO y usa categorias de Gasto
 * "_tecnico" en vez de "_mecanico", para no mezclar los reportes de caja
 * entre los dos modulos -- la tabla `mecanicos` y el motor de comisiones
 * son los MISMOS, a proposito (ver decision en el plan).
 */
class ServicioTecnicoPanel extends Component
{
    // Lista
    public string $filtroEstado = 'activas';
    public string $busqueda     = '';
    public string $fechaDesde   = '';
    public string $fechaHasta   = '';

    // Modal nueva/editar orden
    public bool $modalOrden = false;
    public ?int $ordenId    = null;

    public string $clienteNombre    = '';
    public string $clienteTelefono  = '';
    public string $marca            = '';
    public string $modelo           = '';
    public string $imeiSerial       = '';
    public string $color            = '';
    public string $claveDesbloqueo  = '';
    public string $diagnostico      = '';
    public string $observaciones    = '';
    public string $estado           = 'pendiente';

    // Repuestos/servicios dentro del modal de orden
    public array $repuestos = [];

    // Búsqueda de productos para repuestos
    public string $buscarProducto = '';

    // ── Vista activa: 'ordenes' | 'tecnicos'
    public string $vistaActiva = 'ordenes';

    // ── Modal Servicio (crear/editar servicio de técnico)
    public bool   $modalServicio      = false;
    public ?int   $servicioId         = null;   // null = crear, int = editar
    public ?int   $svcMecanicoId      = null;
    public string $svcNombre          = '';
    public string $svcPrecio          = '';
    public string $svcCosto           = '';   // solo para servicios a terceros
    public string $svcPctEmpresa      = '0';
    public string $svcTipoServicio    = 'propio';
    public string $svcTerceroNombre   = '';
    public bool   $svcBloquearTipo    = false;
    public ?int   $svcExpandMecanico  = null;

    // ── Liquidación
    public bool  $modalLiquidacion    = false;
    public ?int  $liquidarMecanicoId  = null;
    public string $liqFechaDesde      = '';
    public string $liqFechaHasta      = '';
    public string $liqMedioPago       = 'efectivo';
    public string $liqNotas           = '';
    public array  $liqServicios       = [];
    public float  $liqTotalServicios  = 0;
    public float  $liqMontoMecanico   = 0;
    public float  $liqPorcentajeMecanico = 0;
    public float  $liqPrestamosPendientes = 0;
    public float  $liqMontoNeto          = 0;

    // ── Historial de servicios por técnico
    public bool   $modalHistorialMecanico = false;
    public ?int   $histMecanicoId         = null;
    public string $histDesde              = '';
    public string $histHasta              = '';

    // ── Historial de servicios a terceros
    public bool   $modalHistorialTerceros = false;
    public string $histTercDesde          = '';
    public string $histTercHasta          = '';

    // ── Préstamos a técnicos
    public bool   $modalPrestamo      = false;
    public ?int   $prestamoMecanicoId = null;
    public string $prestamoMonto      = '';
    public string $prestamoNota       = '';

    // ── Cierre de Caja de Técnicos
    public bool   $modalCajaMecanicos = false;
    public string $cajaMecDesde       = '';
    public string $cajaMecHasta       = '';
    public string $cajaMecMontoCierre = '';

    public bool $esTurion = false;

    public function mount(): void
    {
        $this->esTurion = \App\Support\PosEdition::esHibrida();
    }

    private function empresaId(): int
    {
        return auth()->user()->getEmpresaActualId();
    }

    public function getOrdenesProperty()
    {
        return ServicioTecnicoOrden::where('empresa_id', $this->empresaId())
            ->when($this->filtroEstado === 'entregado' && !$this->fechaDesde && !$this->fechaHasta,
                fn($q) => $q->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
            )
            ->when($this->fechaDesde, fn($q) => $q->whereDate('created_at', '>=', $this->fechaDesde))
            ->when($this->fechaHasta, fn($q) => $q->whereDate('created_at', '<=', $this->fechaHasta))
            ->when($this->filtroEstado === 'activas', fn($q) => $q->where('estado', '!=', 'entregado'))
            ->when($this->filtroEstado && $this->filtroEstado !== 'activas', fn($q) => $q->where('estado', $this->filtroEstado))
            ->when($this->busqueda, fn($q) => $q->where(function($q2) {
                $q2->where('imei_serial', 'like', '%'.$this->busqueda.'%')
                   ->orWhere('cliente_nombre', 'like', '%'.$this->busqueda.'%')
                   ->orWhere('marca', 'like', '%'.$this->busqueda.'%');
            }))
            ->with('items')
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
        $this->reset(['ordenId','clienteNombre','clienteTelefono','marca','modelo','imeiSerial',
                      'color','claveDesbloqueo','diagnostico','observaciones','repuestos','buscarProducto']);
        $this->estado     = 'pendiente';
        $this->modalOrden = true;
    }

    public function editarOrden(int $id): void
    {
        $orden = ServicioTecnicoOrden::where('empresa_id', $this->empresaId())->with('items')->findOrFail($id);

        $this->ordenId         = $orden->id;
        $this->clienteNombre   = $orden->cliente_nombre;
        $this->clienteTelefono = $orden->cliente_telefono ?? '';
        $this->marca            = $orden->marca ?? '';
        $this->modelo           = $orden->modelo ?? '';
        $this->imeiSerial       = $orden->imei_serial ?? '';
        $this->color            = $orden->color ?? '';
        $this->claveDesbloqueo  = $orden->clave_desbloqueo ?? '';
        $this->diagnostico     = $orden->diagnostico ?? '';
        $this->observaciones   = $orden->observaciones ?? '';
        $this->estado          = $orden->estado;

        $this->repuestos = $orden->items->map(fn($r) => [
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
        ], [
            'clienteNombre.required' => 'El nombre del cliente es obligatorio.',
        ]);

        $esOrdenNueva = ! $this->ordenId;

        $data = [
            'empresa_id'       => $this->empresaId(),
            'cliente_nombre'   => trim($this->clienteNombre),
            'cliente_telefono' => trim($this->clienteTelefono) ?: null,
            'marca'            => trim($this->marca) ?: null,
            'modelo'           => trim($this->modelo) ?: null,
            'imei_serial'      => trim($this->imeiSerial) ?: null,
            'color'            => trim($this->color) ?: null,
            'clave_desbloqueo' => trim($this->claveDesbloqueo) ?: null,
            'diagnostico'      => trim($this->diagnostico) ?: null,
            'observaciones'    => trim($this->observaciones) ?: null,
            'estado'           => $this->estado,
            'creado_por'       => auth()->id(),
        ];

        if ($this->ordenId) {
            $orden = ServicioTecnicoOrden::where('empresa_id', $this->empresaId())->findOrFail($this->ordenId);
            $orden->update($data);

            if ($this->esTurion && $orden->servidor_id) {
                \App\Services\Turion\ColaSincronizacion::encolar('servicio_tecnico_actualizar', array_merge(
                    collect($data)->except(['empresa_id', 'creado_por'])->all(),
                    ['servidor_id' => $orden->servidor_id]
                ));
            }
        } else {
            $orden = ServicioTecnicoOrden::create($data);
        }

        $idsExistentes = [];
        foreach ($this->repuestos as $r) {
            if (! empty($r['descripcion']) && (float)$r['precio_unitario'] > 0) {
                $subtotal = round((float)$r['cantidad'] * (float)$r['precio_unitario'], 2);
                if ($r['id']) {
                    ServicioTecnicoItem::where('id', $r['id'])
                        ->where('orden_id', $orden->id)
                        ->update([
                            'descripcion'     => $r['descripcion'],
                            'cantidad'        => $r['cantidad'],
                            'precio_unitario' => $r['precio_unitario'],
                            'subtotal'        => $subtotal,
                        ]);
                    $idsExistentes[] = $r['id'];
                } else {
                    $nuevo = ServicioTecnicoItem::create([
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

        ServicioTecnicoItem::where('orden_id', $orden->id)
            ->when($idsExistentes, fn($q) => $q->whereNotIn('id', $idsExistentes))
            ->delete();

        if ($this->esTurion) {
            \App\Services\Turion\ColaSincronizacion::encolar('servicio_tecnico_item', [
                'servicio_tecnico_orden_id' => $orden->id,
                'items' => ServicioTecnicoItem::where('orden_id', $orden->id)->get()->map(fn ($r) => [
                    'id_producto' => $r->producto_id ?? 0,
                    'nombre' => $r->descripcion,
                    'cantidad' => (float) $r->cantidad,
                    'precio' => (float) $r->precio_unitario,
                ])->values()->all(),
            ]);
        }

        $this->modalOrden = false;
        $this->dispatch('success', 'Orden #' . $orden->numero_orden . ' guardada.');

        // Ticket con codigo de barras: se imprime solo al crear la orden
        // (para pegar al equipo), no cuando solo se edita una ya existente.
        if ($esOrdenNueva) {
            $this->dispatch('open-print', ['url' => route('servicio-tecnico.orden.ticket', $orden->id)]);
        }
    }

    public function cambiarEstado(int $id, string $nuevoEstado): void
    {
        $orden = ServicioTecnicoOrden::where('empresa_id', $this->empresaId())->findOrFail($id);
        $update = ['estado' => $nuevoEstado];
        if ($nuevoEstado === 'entregado') {
            $update['entregado_at'] = now();
        }
        $orden->update($update);

        if ($this->esTurion && $orden->servidor_id) {
            $cambios = ['estado' => $nuevoEstado, 'servidor_id' => $orden->servidor_id];
            if (isset($update['entregado_at'])) {
                $cambios['entregado_at'] = $update['entregado_at']->toIso8601String();
            }
            \App\Services\Turion\ColaSincronizacion::encolar('servicio_tecnico_actualizar', $cambios);
        }
    }

    public function guardarNotaTrabajo(int $id, string $nota): void
    {
        $orden = ServicioTecnicoOrden::where('empresa_id', $this->empresaId())->findOrFail($id);
        $notaTrabajo = trim($nota) ?: null;
        $orden->update(['nota_trabajo' => $notaTrabajo]);

        if ($this->esTurion && $orden->servidor_id) {
            \App\Services\Turion\ColaSincronizacion::encolar('servicio_tecnico_actualizar', [
                'servidor_id' => $orden->servidor_id,
                'nota_trabajo' => $notaTrabajo,
            ]);
        }
    }

    public function abrirOrden(int $id): void
    {
        $this->redirect(route('servicio-tecnico.orden', $id));
    }

    public function reimprimirTicket(int $id): void
    {
        $orden = ServicioTecnicoOrden::where('empresa_id', $this->empresaId())->findOrFail($id);
        $this->dispatch('open-print', ['url' => route('servicio-tecnico.orden.ticket', $orden->id)]);
    }

    public function eliminarOrden(int $id): void
    {
        $orden = ServicioTecnicoOrden::where('empresa_id', $this->empresaId())->findOrFail($id);

        if (DB::getDriverName() === 'sqlite' && $orden->servidor_id) {
            \App\Services\Turion\ColaSincronizacion::encolarServicioTecnicoBorrado($orden->servidor_id);
        }

        $orden->delete();
    }

    // ── Técnicos / Liquidación ──────────────────────────────────────────────

    public function getMecanicosProperty()
    {
        $empresaId = $this->empresaId();
        $tecnicos = Mecanico::where('empresa_id', $empresaId)->where('rol', Mecanico::ROL_TECNICO)->where('activo', true)->get();

        return $tecnicos->map(function (Mecanico $m) use ($empresaId) {
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
            ->join('mecanicos as m', 'm.id', '=', 'fd.mecanico_id')
            ->where('f.empresa_id', $empresaId)
            ->where('m.empresa_id', $empresaId)
            ->where('m.rol', Mecanico::ROL_TECNICO)
            ->where('fd.tipo_servicio', 'propio')
            ->whereNull('lmd.id')
            ->select(DB::raw('
                COALESCE(SUM(fd.subtotal), 0) as total_servicios,
                COALESCE(SUM(fd.subtotal * (100 - COALESCE(fd.porcentaje_empresa, 0)) / 100), 0) as monto_mecanicos
            '))
            ->first();

        $totalPendiente    = (float) ($pendiente->total_servicios ?? 0);
        $aLiquidar         = (float) ($pendiente->monto_mecanicos ?? 0);
        $gananciaPendiente = $totalPendiente - $aLiquidar;

        $tecnicoIds = Mecanico::where('empresa_id', $empresaId)->where('rol', Mecanico::ROL_TECNICO)->pluck('id');

        $liquidadoHistorico       = (float) LiquidacionMecanico::where('empresa_id', $empresaId)->whereIn('mecanico_id', $tecnicoIds)->sum('monto_mecanico');
        $totalServiciosLiquidados = (float) LiquidacionMecanico::where('empresa_id', $empresaId)->whereIn('mecanico_id', $tecnicoIds)->sum('total_servicios');
        $gananciaLiquidada        = $totalServiciosLiquidados - $liquidadoHistorico;

        $prestamosPendientes = (float) MecanicoPrestamo::whereIn('mecanico_id', $tecnicoIds)
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

        $qPropioPendiente = DB::table('factura_detalles as fd')
            ->join('facturas as f', 'f.id', '=', 'fd.factura_id')
            ->join('mecanicos as m', 'm.id', '=', 'fd.mecanico_id')
            ->leftJoin('liquidacion_mecanico_detalles as lmd', 'lmd.factura_detalle_id', '=', 'fd.id')
            ->where('f.empresa_id', $empresaId)
            ->where('m.empresa_id', $empresaId)
            ->where('m.rol', Mecanico::ROL_TECNICO)
            ->where('fd.tipo_servicio', 'propio')
            ->whereNull('lmd.id');

        $propioEfectivo      = (float) ((clone $qPropioPendiente)->where('f.tipo_pago', 'contado')->where('f.medio_pago', 'efectivo')->rawValue('SUM(fd.subtotal)') ?? 0);
        $propioTransferencia = (float) ((clone $qPropioPendiente)->where('f.tipo_pago', 'contado')->where('f.medio_pago', 'transferencia')->rawValue('SUM(fd.subtotal)') ?? 0);
        $propioCredito       = (float) ((clone $qPropioPendiente)->where('f.tipo_pago', 'credito')->rawValue('SUM(fd.subtotal)') ?? 0);

        $propioGanancia = (float) ((clone $qPropioPendiente)->rawValue('SUM(fd.subtotal * COALESCE(fd.porcentaje_empresa, 0) / 100)') ?? 0);

        $porMecanico = (clone $qPropioPendiente)
            ->groupBy('fd.mecanico_id', 'm.nombre')
            ->orderByDesc(DB::raw('SUM(fd.subtotal)'))
            ->get(['fd.mecanico_id', 'm.nombre', DB::raw('SUM(fd.subtotal) as monto')])
            ->map(fn ($r) => ['mecanico_id' => $r->mecanico_id, 'nombre' => $r->nombre, 'monto' => (float) $r->monto]);

        $montoTerceroSql = 'SUM(fd.subtotal * COALESCE(fd.porcentaje_empresa, 0) / 100)';

        $qTerceros = DB::table('factura_detalles as fd')
            ->join('facturas as f', 'f.id', '=', 'fd.factura_id')
            ->leftJoin('products as p', function ($j) use ($empresaId) {
                $j->on('p.id_producto', '=', 'fd.producto_id')->where('p.empresa_id', '=', $empresaId);
            })
            ->where('f.empresa_id', $empresaId)
            ->where('fd.tipo_servicio', 'tercero')
            ->whereDate('f.fecha', '>=', $desde)
            ->whereDate('f.fecha', '<=', $hasta);

        $tercerosEfectivo      = (float) ((clone $qTerceros)->where('f.tipo_pago', 'contado')->where('f.medio_pago', 'efectivo')->rawValue($montoTerceroSql) ?? 0);
        $tercerosTransferencia = (float) ((clone $qTerceros)->where('f.tipo_pago', 'contado')->where('f.medio_pago', 'transferencia')->rawValue($montoTerceroSql) ?? 0);
        $tercerosCredito       = (float) ((clone $qTerceros)->where('f.tipo_pago', 'credito')->rawValue($montoTerceroSql) ?? 0);
        $tercerosMonto         = $tercerosEfectivo + $tercerosTransferencia + $tercerosCredito;

        $porTercero = (clone $qTerceros)
            ->groupBy('p.tercero_nombre')
            ->orderByDesc(DB::raw($montoTerceroSql))
            ->get(['p.tercero_nombre', DB::raw($montoTerceroSql . ' as monto')])
            ->map(fn ($r) => ['nombre' => $r->tercero_nombre ?: 'Sin nombre', 'monto' => (float) $r->monto]);

        $tecnicoIds = Mecanico::where('empresa_id', $empresaId)->where('rol', Mecanico::ROL_TECNICO)->pluck('id');
        $prestamosPendientes = (float) MecanicoPrestamo::whereIn('mecanico_id', $tecnicoIds)
            ->where('estado', 'pendiente')
            ->sum('monto');

        $serviciosEfectivo      = $propioEfectivo + $tercerosEfectivo;
        $serviciosTransferencia = $propioTransferencia + $tercerosTransferencia;
        $serviciosCredito       = $propioCredito + $tercerosCredito;

        $efectivo      = $serviciosEfectivo - $prestamosPendientes;
        $transferencia = $serviciosTransferencia;

        return [
            'servicios_efectivo'      => (float) $serviciosEfectivo,
            'servicios_transferencia' => (float) $serviciosTransferencia,
            'servicios_credito'       => (float) $serviciosCredito,
            'prestamos_pendientes'    => $prestamosPendientes,
            'efectivo'                => $efectivo,
            'transferencia'           => $transferencia,
            'efectivo_esperado'       => $efectivo,
            'queda_empresa'           => $propioGanancia + $tercerosMonto,
            'por_mecanico'            => $porMecanico,
            'por_tercero'             => $porTercero,
            'terceros_monto'          => $tercerosMonto,
            'ganancia_total'          => $propioGanancia + $tercerosMonto,
            'pendiente_liquidar'      => (float) $this->getResumenMecanicosProperty()['a_liquidar_neto'],
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

        $servicios = $q->select([
                'fd.id',
                'f.id as factura_id',
                'f.tipo_factura',
                'f.factus_number',
                'f.fecha as fecha_orden',
                'fd.descripcion_larga',
                'fd.subtotal',
                'fd.porcentaje_empresa',
                'lm.fecha_pago',
                'lm.estado as liquidacion_estado',
            ])
            ->get()
            ->map(function ($r) {
                $numeroVisual = ($r->tipo_factura === 'electronica' && filled($r->factus_number))
                    ? (string) $r->factus_number
                    : (($r->tipo_factura === 'salida' ? 'SAL-' : 'FAC-') . str_pad((string) $r->factura_id, 6, '0', STR_PAD_LEFT));

                return (object) [
                    'tipo'             => 'servicio',
                    'id'               => $r->id,
                    'factura_id'       => $r->factura_id,
                    'numero_visual'    => $numeroVisual,
                    'fecha'            => \Carbon\Carbon::parse($r->fecha_orden)->format('d/m/Y H:i'),
                    'fecha_orden'      => $r->fecha_orden,
                    'descripcion'      => $r->descripcion_larga,
                    'subtotal'         => (float) $r->subtotal,
                    'monto_mecanico'   => round((float) $r->subtotal * (100 - (float) ($r->porcentaje_empresa ?? 0)) / 100, 2),
                    'liquidado'        => (bool) $r->fecha_pago,
                    'fecha_pago'       => $r->fecha_pago,
                ];
            });

        $qPrestamos = MecanicoPrestamo::where('mecanico_id', $this->histMecanicoId);

        if ($this->histDesde) $qPrestamos->whereDate('fecha', '>=', $this->histDesde);
        if ($this->histHasta) $qPrestamos->whereDate('fecha', '<=', $this->histHasta);

        $prestamos = $qPrestamos->get()->map(function ($p) {
            return (object) [
                'tipo'             => 'prestamo',
                'id'               => $p->id,
                'factura_id'       => null,
                'numero_visual'    => null,
                'fecha'            => $p->fecha->format('d/m/Y'),
                'fecha_orden'      => $p->fecha->format('Y-m-d H:i:s'),
                'descripcion'      => 'Préstamo' . ($p->nota ? ': ' . $p->nota : ''),
                'subtotal'         => (float) $p->monto,
                'monto_mecanico'   => -1 * (float) $p->monto,
                'liquidado'        => $p->estado === 'descontado',
                'fecha_pago'       => null,
            ];
        });

        return $servicios->concat($prestamos)->sortByDesc('fecha_orden')->values();
    }

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
                'f.fecha as fecha_orden',
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
                    'fecha'          => \Carbon\Carbon::parse($r->fecha_orden)->format('d/m/Y H:i'),
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
            'f.fecha as fecha_orden',
            'fd.subtotal',
            'fd.porcentaje_empresa',
        ])->get();

        $this->liqServicios = $rows->map(fn($r) => [
            'detalle_id'        => $r->id,
            'factura_id'        => $r->factura_id,
            'fecha'             => \Carbon\Carbon::parse($r->fecha_orden)->format('d/m/Y'),
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

            MecanicoPrestamo::whereIn('id', $prestamosPendientes->pluck('id'))
                ->update(['estado' => 'descontado', 'liquidacion_id' => $liquidacion->id]);

            $tecnicoNombre = Mecanico::find($this->liquidarMecanicoId)?->nombre ?? 'Técnico';
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
                    'categoria'   => 'liquidacion_tecnico',
                    'descripcion' => 'Liquidación técnico: ' . $tecnicoNombre
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

    // ── Préstamos a técnicos ────────────────────────────────────────────────

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
            $tecnico = Mecanico::find($this->prestamoMecanicoId);
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

            $cajaActiva = Caja::where('empresa_id', $this->empresaId())
                ->where('estado', 'abierta')
                ->latest('opened_at')
                ->first();

            Gasto::create([
                'id_gasto'    => Gasto::where('empresa_id', $this->empresaId())->max('id_gasto') + 1,
                'empresa_id'  => $this->empresaId(),
                'tipo'        => 'salida',
                'categoria'   => 'prestamo_tecnico',
                'descripcion' => 'Préstamo a técnico: ' . ($tecnico?->nombre ?? ''),
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
        $this->dispatch('notify', type: 'success', message: $this->servicioId ? 'Servicio actualizado.' : 'Servicio creado y asignado al técnico.');
    }

    public function eliminarServicio(int $productoId): void
    {
        Product::where('empresa_id', $this->empresaId())->where('id_producto', $productoId)->delete();
        $this->dispatch('notify', type: 'success', message: 'Servicio eliminado.');
    }

    public function render()
    {
        return view('livewire.servicio-tecnico-panel', [
            'ordenes'            => $this->ordenes,
            'productosSugeridos' => $this->productosSugeridos,
            'mecanicos'          => $this->mecanicos,
            'resumenMecanicos'   => $this->resumenMecanicos,
        ]);
    }
}
