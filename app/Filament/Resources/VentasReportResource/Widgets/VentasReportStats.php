<?php

namespace App\Filament\Resources\VentasReportResource\Widgets;

use App\Filament\Resources\VentasReportResource\Pages\ListVentasReports;
use App\Models\FacturaPago;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

class VentasReportStats extends BaseWidget
{
    use InteractsWithPageTable;

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getTablePage(): string
    {
        return ListVentasReports::class;
    }

    protected function getStats(): array
    {
        $query = $this->getPageTableQuery();
        $facturas = (clone $query)->count();

        $facturaIds = (clone $query)->pluck('id');

        // Total servicios (separado de productos)
        $totalServicios = $facturaIds->isEmpty() ? 0.0 : (float) DB::table('factura_detalles as fd')
            ->join('facturas as f', 'f.id', '=', 'fd.factura_id')
            ->join('products as p', function ($join) {
                $join->on('p.id_producto', '=', 'fd.producto_id')
                    ->on('p.empresa_id', '=', 'f.empresa_id');
            })
            ->whereIn('fd.factura_id', $facturaIds)
            ->where('p.tipo_producto', 'servicio')
            ->sum('fd.subtotal');

        $totalGeneral = (float) (clone $query)->sum('total');
        $totalPassthrough = $this->passthroughTotal($facturaIds);
        $ventas    = $totalGeneral - $totalServicios - $totalPassthrough;

        // Contado y crédito solo de productos
        $contadoTotal = (float) (clone $query)->where('tipo_pago', 'contado')->sum('total');
        $creditoTotal = (float) (clone $query)->where('tipo_pago', 'credito')->sum('total');

        $serviciosContado = $facturaIds->isEmpty() ? 0.0 : (float) DB::table('factura_detalles as fd')
            ->join('facturas as f', 'f.id', '=', 'fd.factura_id')
            ->join('products as p', function ($join) {
                $join->on('p.id_producto', '=', 'fd.producto_id')
                    ->on('p.empresa_id', '=', 'f.empresa_id');
            })
            ->whereIn('fd.factura_id', $facturaIds)
            ->where('f.tipo_pago', 'contado')
            ->where('p.tipo_producto', 'servicio')
            ->sum('fd.subtotal');

        $serviciosCredito = $totalServicios - $serviciosContado;

        $passthroughContado = $this->passthroughTotal($facturaIds, 'contado');
        $passthroughCredito = $totalPassthrough - $passthroughContado;

        $contado = $contadoTotal - $serviciosContado - $passthroughContado;
        $credito = $creditoTotal - $serviciosCredito - $passthroughCredito;

        $porCobrar = (float) (clone $query)
            ->where('tipo_pago', 'credito')
            ->sum('saldo');
        $efectivo      = $this->recibidoPorMedio($query, 'efectivo');
        $transferencia = $this->recibidoPorMedio($query, 'transferencia');
        $utilidad = $this->utilidad($query);
        $utilidadPorcentaje = $ventas > 0
            ? ($utilidad / $ventas) * 100
            : 0;

        return [
            Stat::make('Facturas', number_format($facturas, 0, ',', '.'))
                ->icon('heroicon-o-document-text')
                ->extraAttributes(['class' => 'ventas-report-compact-stat'])
                ->color('primary'),

            Stat::make('Ventas', $this->money($ventas))
                ->description(new HtmlString(
                    '<div style="display:grid;gap:4px;font-size:0.74rem;line-height:1.15;">'
                    . '<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;color:#2563eb;white-space:nowrap;">'
                    . '<span>Contado ' . e($this->money($contado)) . '</span>'
                    . '<span>Credito ' . e($this->money($credito)) . '</span>'
                    . '</div>'
                    . '<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;color:#059669;white-space:nowrap;">'
                    . '<span>Efectivo ' . e($this->money($efectivo)) . '</span>'
                    . '<span>Transferencia ' . e($this->money($transferencia)) . '</span>'
                    . '</div>'
                    . '</div>'
                ))
                ->icon('heroicon-o-banknotes')
                ->extraAttributes(['class' => 'ventas-report-wide-stat'])
                ->color('success'),

            Stat::make('Por cobrar', $this->money($porCobrar))
                ->description('Saldo pendiente en cartera')
                ->icon('heroicon-o-clock')
                ->extraAttributes(['class' => 'ventas-report-big-stat'])
                ->color($porCobrar > 0 ? 'danger' : 'success'),

            Stat::make('Utilidad', $this->money($utilidad))
                ->description(number_format($utilidadPorcentaje, 2, ',', '.') . ' % sobre la venta')
                ->icon('heroicon-o-chart-bar-square')
                ->extraAttributes(['class' => 'ventas-report-big-stat'])
                ->color($utilidad < 0 ? 'danger' : 'success'),
        ];
    }

    protected function money(float $value): string
    {
        return '$ ' . number_format($value, 0, ',', '.');
    }

    private function recibidoPorMedio($query, string $medioPago): float
    {
        $facturaIdsContadoMedio = (clone $query)
            ->where('tipo_pago', 'contado')
            ->where('medio_pago', $medioPago)
            ->pluck('id');

        $ventasContado = (float) (clone $query)
            ->where('tipo_pago', 'contado')
            ->where('medio_pago', $medioPago)
            ->sum('total');

        $passthroughContado = $this->passthroughTotal($facturaIdsContadoMedio);

        $facturaIdsCredito = (clone $query)
            ->where('tipo_pago', 'credito')
            ->pluck('id');

        $abonosCredito = (float) FacturaPago::query()
            ->where('medio_pago', $medioPago)
            ->whereIn('factura_id', $facturaIdsCredito)
            ->sum('monto');

        return $ventasContado - $passthroughContado + $abonosCredito;
    }

    // Item manual (codigo 10001): reembolso a terceros sin margen, no cuenta como venta propia.
    private function passthroughTotal($facturaIds, ?string $tipoPago = null): float
    {
        if ($facturaIds->isEmpty()) {
            return 0.0;
        }

        return (float) DB::table('factura_detalles as fd')
            ->join('facturas as f', 'f.id', '=', 'fd.factura_id')
            ->whereIn('fd.factura_id', $facturaIds)
            ->where('fd.producto_id', '10001')
            ->when($tipoPago, fn ($q) => $q->where('f.tipo_pago', $tipoPago))
            ->sum('fd.subtotal');
    }

    private function utilidad($query): float
    {
        $facturaIds = (clone $query)->pluck('id');

        if ($facturaIds->isEmpty()) {
            return 0;
        }

        // Solo productos (excluir servicios y el item manual 10001 — su costo real
        // no esta en precio_costo del producto y no debe contar como ganancia)
        return (float) DB::table('factura_detalles as fd')
            ->join('facturas as f', 'f.id', '=', 'fd.factura_id')
            ->join('products as p', function ($join) {
                $join->on('p.id_producto', '=', 'fd.producto_id')
                    ->on('p.empresa_id', '=', 'f.empresa_id');
            })
            ->whereIn('fd.factura_id', $facturaIds)
            ->where('p.tipo_producto', '!=', 'servicio')
            ->where('fd.producto_id', '!=', '10001')
            ->sum(DB::raw('
                ((fd.cantidad - COALESCE(fd.devuelto_cantidad, 0)) * fd.precio)
                -
                (
                    (fd.cantidad - COALESCE(fd.devuelto_cantidad, 0))
                    *
                    (
                        (COALESCE(p.precio_costo, 0) * (1 - (COALESCE(p.descuento_comercial, 0) / 100)))
                        *
                        (1 + (COALESCE(p.iva_venta, 0) / 100))
                    )
                )
            '));
    }
}
