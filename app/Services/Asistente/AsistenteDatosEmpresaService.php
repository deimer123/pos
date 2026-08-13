<?php

namespace App\Services\Asistente;

use App\Models\ConfiguracionEmpresa;
use App\Models\Factura;
use App\Models\FacturaDetalle;
use App\Models\Gasto;
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

        $total = (float) (clone $query)->sum('total');
        $totalContado = (float) (clone $query)->where('tipo_pago', 'contado')->sum('total');
        $totalCredito = (float) (clone $query)->where('tipo_pago', 'credito')->sum('total');
        $cantidad = (clone $query)->count();

        return [
            'desde' => $desde,
            'hasta' => $hasta,
            'total_vendido' => $total,
            'total_contado' => $totalContado,
            'total_credito' => $totalCredito,
            'cantidad_facturas' => $cantidad,
        ];
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
