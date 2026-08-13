<?php

namespace App\Services\Asistente;

use App\Models\Compra;
use App\Models\ConfiguracionEmpresa;
use App\Models\Factura;
use App\Models\FacturaDetalle;
use App\Models\Gasto;
use App\Models\HotelHabitacion;
use App\Models\HotelReserva;
use App\Models\LiquidacionMecanico;
use App\Models\Mecanico;
use App\Models\ServicioTecnicoOrden;
use App\Models\TallerOrden;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Consultas de solo lectura para el asistente de IA del admin_empresa
 * (ver App\Services\Asistente\AsistenteClaudeService). Cada metodo exige
 * $empresaId como primer parametro y lo aplica siempre como filtro -- este
 * valor SIEMPRE lo inyecta el backend desde auth()->user()->getEmpresaActualId(),
 * nunca el modelo de IA, precisamente para que sea imposible que una
 * respuesta mezcle datos de otra empresa.
 */
class AsistenteDatosEmpresaService
{
    public function infoEmpresa(int $empresaId): array
    {
        $config = ConfiguracionEmpresa::where('empresa_id', $empresaId)->first();

        if (! $config) {
            return ['mensaje' => 'La empresa no tiene configuracion registrada.'];
        }

        return [
            'nombre_empresa' => $config->nombre_empresa,
            'nit' => $config->nit,
            'tipo_negocio' => $config->tipo_negocio,
            'telefono' => $config->telefono,
            'direccion' => $config->direccion,
        ];
    }

    public function resumenVentas(int $empresaId, string $periodo): array
    {
        [$desde, $hasta] = $this->rangoPeriodo($periodo);

        return $this->ventasEnRango($empresaId, $desde->toDateString(), $hasta->toDateString());
    }

    public function ventasEnRango(int $empresaId, string $desde, string $hasta): array
    {
        $query = Factura::query()
            ->where('empresa_id', $empresaId)
            ->whereBetween('fecha', [Carbon::parse($desde)->startOfDay(), Carbon::parse($hasta)->endOfDay()]);

        $idsTodos = (clone $query)->pluck('id')->toArray();
        $idsContado = Factura::whereIn('id', $idsTodos)->where('tipo_pago', 'contado')->pluck('id')->toArray();
        $idsCredito = Factura::whereIn('id', $idsTodos)->where('tipo_pago', 'credito')->pluck('id')->toArray();

        return [
            'desde' => $desde,
            'hasta' => $hasta,
            'total_vendido' => $this->ventasNetas($idsTodos),
            'total_contado' => $this->ventasNetas($idsContado),
            'total_credito' => $this->ventasNetas($idsCredito),
            'cantidad_facturas' => count($idsTodos),
        ];
    }

    /**
     * Total neto de un conjunto de facturas, igual al que muestran las
     * tarjetas "Ventas del dia/mes" del dashboard (App\Filament\Widgets\StatsOverview):
     * el total bruto de las facturas SIN los servicios (no son venta de
     * producto) ni el item pasarela "10001" (reembolso a terceros sin
     * margen). Se replica aqui a proposito para que el asistente nunca
     * diga una cifra distinta a la que ya ve el usuario en el dashboard.
     */
    private function ventasNetas(array $facturaIds): float
    {
        if (empty($facturaIds)) {
            return 0.0;
        }

        $bruto = (float) Factura::whereIn('id', $facturaIds)->sum('total');

        $servicios = (float) DB::table('factura_detalles as fd')
            ->join('facturas as f', 'f.id', '=', 'fd.factura_id')
            ->join('products as p', function ($join) {
                $join->on('p.id_producto', '=', 'fd.producto_id')
                    ->on('p.empresa_id', '=', 'f.empresa_id');
            })
            ->whereIn('fd.factura_id', $facturaIds)
            ->where('p.tipo_producto', 'servicio')
            ->sum('fd.subtotal');

        $passthrough = (float) DB::table('factura_detalles as fd')
            ->whereIn('fd.factura_id', $facturaIds)
            ->where('fd.producto_id', '10001')
            ->sum('fd.subtotal');

        return $bruto - $servicios - $passthrough;
    }

    public function productoMasVendido(int $empresaId, string $periodo, int $limite = 5): array
    {
        [$desde, $hasta] = $this->rangoPeriodo($periodo);

        $filas = FacturaDetalle::query()
            ->select(['producto_id', 'descripcion_larga', DB::raw('SUM(cantidad) as total_vendidos')])
            ->whereHas('factura', function ($q) use ($empresaId, $desde, $hasta) {
                $q->where('empresa_id', $empresaId)
                    ->whereBetween('fecha', [$desde->startOfDay(), $hasta->endOfDay()]);
            })
            ->where('producto_id', '!=', '10001')
            ->groupBy('producto_id', 'descripcion_larga')
            ->orderByDesc('total_vendidos')
            ->limit($limite)
            ->get();

        return [
            'desde' => $desde->toDateString(),
            'hasta' => $hasta->toDateString(),
            'productos' => $filas->map(fn ($f) => [
                'producto_id' => $f->producto_id,
                'descripcion' => $f->descripcion_larga,
                'cantidad_vendida' => (float) $f->total_vendidos,
            ])->all(),
        ];
    }

    public function productosStockBajo(int $empresaId, float $limite = 5): array
    {
        $productos = DB::table('products')
            ->where('empresa_id', $empresaId)
            ->where('maneja_inventario', true)
            ->where('existencias', '<=', $limite)
            ->orderBy('existencias')
            ->limit(30)
            ->get(['id_producto', 'descripcion_larga', 'existencias']);

        return [
            'limite_usado' => $limite,
            'productos' => $productos->map(fn ($p) => [
                'producto_id' => $p->id_producto,
                'descripcion' => $p->descripcion_larga,
                'existencias' => (float) $p->existencias,
            ])->all(),
        ];
    }

    public function facturasPendientesPago(int $empresaId, int $limite = 20): array
    {
        $facturas = Factura::query()
            ->where('empresa_id', $empresaId)
            ->where('tipo_pago', 'credito')
            ->whereIn('estado_pago', ['pendiente', 'parcial', 'vencida'])
            ->with('cliente')
            ->orderBy('fecha_vencimiento')
            ->limit($limite)
            ->get();

        return [
            'cantidad' => $facturas->count(),
            'total_pendiente' => (float) $facturas->sum('saldo'),
            'facturas' => $facturas->map(fn (Factura $f) => [
                'numero' => $f->numero_visual,
                'cliente' => optional($f->cliente)->nombre,
                'saldo' => (float) $f->saldo,
                'estado_pago' => $f->estado_pago,
                'fecha_vencimiento' => optional($f->fecha_vencimiento)->toDateString(),
            ])->all(),
        ];
    }

    public function mejoresClientes(int $empresaId, string $periodo = 'mes', int $limite = 5): array
    {
        [$desde, $hasta] = $this->rangoPeriodo($periodo);

        $filas = Factura::query()
            ->select(['cliente_id', DB::raw('SUM(total) as total_comprado'), DB::raw('COUNT(*) as cantidad_compras')])
            ->where('empresa_id', $empresaId)
            ->whereBetween('fecha', [$desde->startOfDay(), $hasta->endOfDay()])
            ->whereNotNull('cliente_id')
            ->groupBy('cliente_id')
            ->orderByDesc('total_comprado')
            ->limit($limite)
            ->with('cliente')
            ->get();

        return [
            'desde' => $desde->toDateString(),
            'hasta' => $hasta->toDateString(),
            'clientes' => $filas->map(fn ($f) => [
                'cliente' => optional($f->cliente)->nombre ?? 'Sin nombre',
                'total_comprado' => (float) $f->total_comprado,
                'cantidad_compras' => (int) $f->cantidad_compras,
            ])->all(),
        ];
    }

    public function gastosPeriodo(int $empresaId, string $periodo = 'mes'): array
    {
        [$desde, $hasta] = $this->rangoPeriodo($periodo);

        $total = (float) Gasto::query()
            ->where('empresa_id', $empresaId)
            ->where('tipo', 'salida')
            ->whereBetween('fecha', [$desde->startOfDay(), $hasta->endOfDay()])
            ->sum('monto');

        return [
            'desde' => $desde->toDateString(),
            'hasta' => $hasta->toDateString(),
            'total_gastos' => $total,
        ];
    }

    /**
     * Servicios vendidos (tipo_servicio no nulo en factura_detalles -- mismo
     * marcador que usa App\Filament\Resources\ReporteServiciosResource),
     * separados en propios vs de terceros.
     */
    public function resumenServicios(int $empresaId, string $periodo): array
    {
        [$desde, $hasta] = $this->rangoPeriodo($periodo);

        $filas = FacturaDetalle::query()
            ->whereNotNull('tipo_servicio')
            ->whereHas('factura', fn ($q) => $q->where('empresa_id', $empresaId)
                ->whereBetween('fecha', [$desde->startOfDay(), $hasta->endOfDay()]))
            ->get(['subtotal', 'tipo_servicio', 'porcentaje_empresa']);

        $propios = $filas->where('tipo_servicio', 'propio');
        $terceros = $filas->where('tipo_servicio', 'tercero');
        $gananciaEmpresa = $propios->sum(fn ($f) => (float) $f->subtotal * (float) $f->porcentaje_empresa / 100);

        return [
            'desde' => $desde->toDateString(),
            'hasta' => $hasta->toDateString(),
            'total_servicios' => (float) $filas->sum('subtotal'),
            'total_servicios_propios' => (float) $propios->sum('subtotal'),
            'total_servicios_terceros' => (float) $terceros->sum('subtotal'),
            'ganancia_empresa_en_servicios_propios' => (float) $gananciaEmpresa,
            'cantidad_servicios' => $filas->count(),
        ];
    }

    /**
     * Cuanto ha generado cada mecanico/tecnico/vendedor/mesero (segun rol,
     * ver App\Models\Mecanico::ROL_*) en servicios/ventas asignadas en el
     * periodo. Refleja lo mismo que App\Filament\Resources\ReporteMecanicosResource.
     */
    public function comisionesPorColaborador(int $empresaId, string $periodo, ?string $rol = null, int $limite = 15): array
    {
        [$desde, $hasta] = $this->rangoPeriodo($periodo);

        $query = FacturaDetalle::query()
            ->select(['mecanico_id', DB::raw('SUM(subtotal) as total_generado'), DB::raw('COUNT(*) as cantidad')])
            ->whereNotNull('mecanico_id')
            ->whereHas('factura', fn ($q) => $q->where('empresa_id', $empresaId)
                ->whereBetween('fecha', [$desde->startOfDay(), $hasta->endOfDay()]))
            ->whereHas('mecanico', function ($q) use ($empresaId, $rol) {
                $q->where('empresa_id', $empresaId);
                if ($rol) {
                    $q->where('rol', $rol);
                }
            });

        $filas = $query->groupBy('mecanico_id')
            ->orderByDesc('total_generado')
            ->limit($limite)
            ->with('mecanico')
            ->get();

        return [
            'desde' => $desde->toDateString(),
            'hasta' => $hasta->toDateString(),
            'colaboradores' => $filas->map(fn ($f) => [
                'nombre' => optional($f->mecanico)->nombre ?? 'Sin nombre',
                'rol' => optional($f->mecanico)->rol,
                'total_generado' => (float) $f->total_generado,
                'cantidad' => (int) $f->cantidad,
            ])->all(),
        ];
    }

    public function liquidacionesPendientes(int $empresaId, int $limite = 15): array
    {
        $liquidaciones = LiquidacionMecanico::query()
            ->where('empresa_id', $empresaId)
            ->where('estado', 'pendiente')
            ->with('mecanico')
            ->orderBy('fecha_hasta')
            ->limit($limite)
            ->get();

        return [
            'cantidad' => $liquidaciones->count(),
            'total_pendiente' => (float) $liquidaciones->sum('monto_neto'),
            'liquidaciones' => $liquidaciones->map(fn (LiquidacionMecanico $l) => [
                'colaborador' => optional($l->mecanico)->nombre,
                'desde' => $l->fecha_desde?->toDateString(),
                'hasta' => $l->fecha_hasta?->toDateString(),
                'monto_neto' => (float) $l->monto_neto,
            ])->all(),
        ];
    }

    public function resumenOrdenesTaller(int $empresaId): array
    {
        return [
            'por_estado' => TallerOrden::where('empresa_id', $empresaId)
                ->selectRaw('estado, COUNT(*) as cantidad')
                ->groupBy('estado')
                ->pluck('cantidad', 'estado')
                ->all(),
        ];
    }

    public function resumenOrdenesServicioTecnico(int $empresaId): array
    {
        return [
            'por_estado' => ServicioTecnicoOrden::where('empresa_id', $empresaId)
                ->selectRaw('estado, COUNT(*) as cantidad')
                ->groupBy('estado')
                ->pluck('cantidad', 'estado')
                ->all(),
        ];
    }

    public function resumenHotel(int $empresaId): array
    {
        return [
            'reservas_por_estado' => HotelReserva::where('empresa_id', $empresaId)
                ->selectRaw('estado, COUNT(*) as cantidad')
                ->groupBy('estado')
                ->pluck('cantidad', 'estado')
                ->all(),
            'habitaciones_activas' => HotelHabitacion::where('empresa_id', $empresaId)->where('activa', true)->count(),
        ];
    }

    public function resumenCompras(int $empresaId, string $periodo): array
    {
        [$desde, $hasta] = $this->rangoPeriodo($periodo);

        $query = Compra::where('empresa_id', $empresaId)
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->where('estado', '!=', 'anulada');

        return [
            'desde' => $desde->toDateString(),
            'hasta' => $hasta->toDateString(),
            'total_comprado' => (float) (clone $query)->sum('total'),
            'saldo_pendiente_a_proveedores' => (float) (clone $query)->sum('saldo'),
            'cantidad_compras' => (clone $query)->count(),
        ];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function rangoPeriodo(string $periodo): array
    {
        return match ($periodo) {
            'hoy' => [now()->startOfDay(), now()->endOfDay()],
            'ayer' => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()],
            'semana' => [now()->startOfWeek(), now()->endOfWeek()],
            'mes_pasado' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()],
            default => [now()->startOfMonth(), now()->endOfMonth()],
        };
    }
}
