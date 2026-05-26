<?php

namespace App\Support;

use App\Models\Caja;
use App\Models\Devolucion;
use App\Models\Factura;
use App\Models\FacturaPago;
use App\Models\Gasto;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CajaReportMetrics
{
    public static function forCaja(Caja $caja): array
    {
        return static::forCajaPeriod($caja, null, null);
    }

    public static function forCajaPeriod(Caja $caja, ?Carbon $desde, ?Carbon $hasta): array
    {
        $empresaId = static::empresaId($caja);
        $inicio = $caja->opened_at ?: $caja->created_at;
        $fin = $caja->closed_at ?: now();

        if (! $inicio) {
            return static::empty();
        }

        if ($desde && $desde->greaterThan($inicio)) {
            $inicio = $desde;
        }

        if ($hasta && $hasta->lessThan($fin)) {
            $fin = $hasta;
        }

        if ($inicio->greaterThan($fin)) {
            return static::empty();
        }

        $facturas = static::facturasQuery($empresaId, $caja->user_id, $inicio, $fin);

        $ventas = (float) (clone $facturas)->sum('total');
        $ventasContado = (float) (clone $facturas)->where('tipo_pago', 'contado')->sum('total');
        $ventasCredito = (float) (clone $facturas)->where('tipo_pago', 'credito')->sum('total');
        $ventasContadoEfectivo = (float) (clone $facturas)->where('tipo_pago', 'contado')->where('medio_pago', 'efectivo')->sum('total');
        $ventasContadoTransferencia = (float) (clone $facturas)->where('tipo_pago', 'contado')->where('medio_pago', 'transferencia')->sum('total');
        $cartera = static::recaudos($empresaId, $caja->user_id, $inicio, $fin);
        $carteraEfectivo = static::recaudosPorMedio($empresaId, $caja->user_id, $inicio, $fin, 'efectivo');
        $carteraTransferencia = static::recaudosPorMedio($empresaId, $caja->user_id, $inicio, $fin, 'transferencia');
        $movimientos = static::movimientosCaja($empresaId, $caja, $inicio, $fin);
        $devoluciones = static::devoluciones($empresaId, $caja->user_id, $inicio, $fin);
        $efectivo = $ventasContadoEfectivo + $carteraEfectivo + $movimientos['entradas_efectivo'] - $movimientos['salidas_efectivo'];
        $transferencia = $ventasContadoTransferencia + $carteraTransferencia + $movimientos['entradas_transferencia'] - $movimientos['salidas_transferencia'];
        $costo = static::costoVenta($empresaId, $caja->user_id, $inicio, $fin);
        $utilidad = $ventas - $costo;
        $montoCierre = $caja->closed_at ? (float) ($caja->monto_cierre ?? 0) : 0;
        $diferenciaEfectivo = $caja->closed_at ? $montoCierre - $efectivo : 0;

        return [
            'ventas' => $ventas,
            'ventas_contado' => $ventasContado,
            'ventas_credito' => $ventasCredito,
            'efectivo' => $efectivo,
            'transferencia' => $transferencia,
            'cartera' => $cartera,
            'cartera_efectivo' => $carteraEfectivo,
            'cartera_transferencia' => $carteraTransferencia,
            'costo' => $costo,
            'utilidad' => $utilidad,
            'utilidad_pct' => $ventas > 0 ? ($utilidad / $ventas) * 100 : 0,
            'recaudos' => $cartera,
            'movimientos_entrada' => $movimientos['entradas'],
            'movimientos_salida' => $movimientos['salidas'],
            'movimientos_neto' => $movimientos['entradas'] - $movimientos['salidas'],
            'monto_cierre' => $montoCierre,
            'diferencia_efectivo' => $diferenciaEfectivo,
            'devoluciones' => $devoluciones['total'],
            'devoluciones_con_pago' => $devoluciones['con_pago'],
            'devoluciones_sin_pago' => $devoluciones['sin_pago'],
            'devoluciones_count' => $devoluciones['count'],
        ];
    }

    public static function totalsForCajas(iterable $cajas): array
    {
        return static::totalsForCajasPeriod($cajas, null, null);
    }

    public static function totalsForCajasPeriod(iterable $cajas, ?Carbon $desde, ?Carbon $hasta): array
    {
        $totals = static::empty();

        foreach ($cajas as $caja) {
            $metrics = static::forCajaPeriod($caja, $desde, $hasta);

            foreach ([
                'ventas',
                'ventas_contado',
                'ventas_credito',
                'efectivo',
                'transferencia',
                'cartera',
                'cartera_efectivo',
                'cartera_transferencia',
                'costo',
                'utilidad',
                'recaudos',
                'movimientos_entrada',
                'movimientos_salida',
                'movimientos_neto',
                'monto_cierre',
                'diferencia_efectivo',
                'devoluciones',
                'devoluciones_con_pago',
                'devoluciones_sin_pago',
                'devoluciones_count',
            ] as $key) {
                $totals[$key] += $metrics[$key];
            }
        }

        $totals['utilidad_pct'] = $totals['ventas'] > 0
            ? ($totals['utilidad'] / $totals['ventas']) * 100
            : 0;

        return $totals;
    }

    protected static function devoluciones(?int $empresaId, ?int $userId, Carbon $inicio, Carbon $fin): array
    {
        $query = Devolucion::query()
            ->where('empresa_id', $empresaId)
            ->where('user_id', $userId)
            ->whereBetween('fecha', [$inicio, $fin]);

        $conPago = (float) (clone $query)
            ->whereHas('factura.pagos')
            ->sum('total');

        $sinPago = (float) (clone $query)
            ->whereDoesntHave('factura.pagos')
            ->sum('total');

        return [
            'total' => $conPago + $sinPago,
            'con_pago' => $conPago,
            'sin_pago' => $sinPago,
            'count' => (int) (clone $query)->count(),
        ];
    }

    protected static function empresaId(Caja $caja): ?int
    {
        return $caja->empresa_id ?: $caja->user_id;
    }

    protected static function facturasQuery(?int $empresaId, ?int $userId, Carbon $inicio, Carbon $fin): Builder
    {
        return Factura::query()
            ->where('empresa_id', $empresaId)
            ->where('user_id', $userId)
            ->whereBetween('fecha', [$inicio, $fin]);
    }

    protected static function costoVenta(?int $empresaId, ?int $userId, Carbon $inicio, Carbon $fin): float
    {
        return (float) DB::table('factura_detalles')
            ->join('facturas', 'factura_detalles.factura_id', '=', 'facturas.id')
            ->leftJoin('products', function ($join) {
                $join->on('factura_detalles.producto_id', '=', 'products.id_producto')
                    ->on('facturas.empresa_id', '=', 'products.empresa_id');
            })
            ->where('facturas.empresa_id', $empresaId)
            ->where('facturas.user_id', $userId)
            ->whereBetween('facturas.fecha', [$inicio, $fin])
            ->sum(DB::raw('
                (factura_detalles.cantidad - COALESCE(factura_detalles.devuelto_cantidad, 0))
                *
                (
                    (COALESCE(products.precio_costo, 0) * (1 - (COALESCE(products.descuento_comercial, 0) / 100)))
                    *
                    (1 + (COALESCE(products.iva_venta, 0) / 100))
                )
            '));
    }

    protected static function recaudos(?int $empresaId, ?int $userId, Carbon $inicio, Carbon $fin): float
    {
        return (float) FacturaPago::query()
            ->where('user_id', $userId)
            ->whereBetween('fecha', [$inicio, $fin])
            ->whereHas('factura', fn ($query) =>
                $query->where('empresa_id', $empresaId)
                    ->where('tipo_pago', 'credito')
            )
            ->sum('monto');
    }

    protected static function recaudosPorMedio(?int $empresaId, ?int $userId, Carbon $inicio, Carbon $fin, string $medio): float
    {
        return (float) FacturaPago::query()
            ->where('user_id', $userId)
            ->where('medio_pago', $medio)
            ->whereBetween('fecha', [$inicio, $fin])
            ->whereHas('factura', fn ($query) =>
                $query->where('empresa_id', $empresaId)
                    ->where('tipo_pago', 'credito')
            )
            ->sum('monto');
    }

    protected static function movimientosCaja(?int $empresaId, Caja $caja, Carbon $inicio, Carbon $fin): array
    {
        $query = Gasto::query()
            ->where('empresa_id', $empresaId)
            ->whereBetween('fecha', [$inicio->toDateString(), $fin->toDateString()])
            ->where(fn ($query) =>
                $query->where('caja_id', $caja->id)
                    ->orWhere(fn ($legacyQuery) =>
                        $legacyQuery->whereNull('caja_id')
                            ->where('created_by', $caja->user_id)
                    )
            );

        $entradas = (float) (clone $query)->where('tipo', 'entrada')->sum('monto');
        $salidas = (float) (clone $query)->where('tipo', 'salida')->sum('monto');
        $entradasEfectivo = (float) (clone $query)->where('tipo', 'entrada')->where('metodo_pago', 'efectivo')->sum('monto');
        $salidasEfectivo = (float) (clone $query)->where('tipo', 'salida')->where('metodo_pago', 'efectivo')->sum('monto');
        $entradasTransferencia = (float) (clone $query)->where('tipo', 'entrada')->where('metodo_pago', 'transferencia')->sum('monto');
        $salidasTransferencia = (float) (clone $query)->where('tipo', 'salida')->where('metodo_pago', 'transferencia')->sum('monto');

        return [
            'entradas' => $entradas,
            'salidas' => $salidas,
            'entradas_efectivo' => $entradasEfectivo,
            'salidas_efectivo' => $salidasEfectivo,
            'entradas_transferencia' => $entradasTransferencia,
            'salidas_transferencia' => $salidasTransferencia,
        ];
    }

    protected static function empty(): array
    {
        return [
            'ventas' => 0,
            'ventas_contado' => 0,
            'ventas_credito' => 0,
            'efectivo' => 0,
            'transferencia' => 0,
            'cartera' => 0,
            'cartera_efectivo' => 0,
            'cartera_transferencia' => 0,
            'costo' => 0,
            'utilidad' => 0,
            'utilidad_pct' => 0,
            'recaudos' => 0,
            'movimientos_entrada' => 0,
            'movimientos_salida' => 0,
            'movimientos_neto' => 0,
            'monto_cierre' => 0,
            'diferencia_efectivo' => 0,
            'devoluciones' => 0,
            'devoluciones_con_pago' => 0,
            'devoluciones_sin_pago' => 0,
            'devoluciones_count' => 0,
        ];
    }
}
