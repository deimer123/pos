<?php

namespace App\Filament\Widgets;

use App\Models\Factura;
use App\Models\Gasto;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Card;
use Illuminate\Support\Facades\DB;

class StatsOverview extends BaseWidget
{
    public static function canView(): bool
    {
        return auth()->user()?->hasRole('super_admin') !== true;
    }

    private function serviciosTotal(int $empresaId, array $facturaIds): float
    {
        if (empty($facturaIds)) {
            return 0.0;
        }

        return (float) DB::table('factura_detalles as fd')
            ->join('facturas as f', 'f.id', '=', 'fd.factura_id')
            ->join('products as p', function ($join) {
                $join->on('p.id_producto', '=', 'fd.producto_id')
                    ->on('p.empresa_id', '=', 'f.empresa_id');
            })
            ->whereIn('fd.factura_id', $facturaIds)
            ->where('p.tipo_producto', 'servicio')
            ->sum('fd.subtotal');
    }

    // Item manual (codigo 10001): reembolso a terceros sin margen, no cuenta como venta propia.
    private function passthroughTotal(array $facturaIds): float
    {
        if (empty($facturaIds)) {
            return 0.0;
        }

        return (float) DB::table('factura_detalles as fd')
            ->whereIn('fd.factura_id', $facturaIds)
            ->where('fd.producto_id', '10001')
            ->sum('fd.subtotal');
    }

    protected function getCards(): array
    {
        $empresaId = auth()->user()->getEmpresaActualId();

        // --- HOY ---
        $idsHoy = Factura::query()
            ->where('empresa_id', $empresaId)
            ->whereDate('fecha', today())
            ->pluck('id')->toArray();

        $serviciosHoy = $this->serviciosTotal($empresaId, $idsHoy);
        $passthroughHoy = $this->passthroughTotal($idsHoy);

        $idsHoyContado = Factura::whereIn('id', $idsHoy)->where('tipo_pago', 'contado')->pluck('id')->toArray();
        $idsHoyCredito = Factura::whereIn('id', $idsHoy)->where('tipo_pago', 'credito')->pluck('id')->toArray();

        $ventasHoy         = Factura::whereIn('id', $idsHoy)->sum('total') - $serviciosHoy - $passthroughHoy;
        $ventasContadoHoy  = Factura::whereIn('id', $idsHoyContado)->sum('total')
                           - $this->serviciosTotal($empresaId, $idsHoyContado) - $this->passthroughTotal($idsHoyContado);
        $ventasCreditoHoy  = Factura::whereIn('id', $idsHoyCredito)->sum('total')
                           - $this->serviciosTotal($empresaId, $idsHoyCredito) - $this->passthroughTotal($idsHoyCredito);

        // --- MES ---
        $idsMes = Factura::query()
            ->where('empresa_id', $empresaId)
            ->whereBetween('fecha', [now()->startOfMonth(), now()->endOfMonth()])
            ->pluck('id')->toArray();

        $serviciosMes = $this->serviciosTotal($empresaId, $idsMes);
        $passthroughMes = $this->passthroughTotal($idsMes);

        $idsMesContado = Factura::whereIn('id', $idsMes)->where('tipo_pago', 'contado')->pluck('id')->toArray();
        $idsMesCredito = Factura::whereIn('id', $idsMes)->where('tipo_pago', 'credito')->pluck('id')->toArray();

        $ventasMes         = Factura::whereIn('id', $idsMes)->sum('total') - $serviciosMes - $passthroughMes;
        $ventasContadoMes  = Factura::whereIn('id', $idsMesContado)->sum('total')
                           - $this->serviciosTotal($empresaId, $idsMesContado) - $this->passthroughTotal($idsMesContado);
        $ventasCreditoMes  = Factura::whereIn('id', $idsMesCredito)->sum('total')
                           - $this->serviciosTotal($empresaId, $idsMesCredito) - $this->passthroughTotal($idsMesCredito);

        $gastosMes = Gasto::query()
            ->where('empresa_id', $empresaId)
            ->where('tipo', 'salida')
            ->whereBetween('fecha', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('monto');

        return [
            Card::make('Ventas del dia', '$ ' . number_format((float) $ventasHoy, 0, ',', '.'))
                ->description(
                    'Contado $ ' . number_format((float) $ventasContadoHoy, 0, ',', '.') .
                    ' | Credito $ ' . number_format((float) $ventasCreditoHoy, 0, ',', '.')
                )
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),

            Card::make('Ventas del mes', '$ ' . number_format((float) $ventasMes, 0, ',', '.'))
                ->description(
                    'Contado $ ' . number_format((float) $ventasContadoMes, 0, ',', '.') .
                    ' | Credito $ ' . number_format((float) $ventasCreditoMes, 0, ',', '.')
                )
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color('primary'),

            Card::make('Gastos del mes', '$ ' . number_format((float) $gastosMes, 0, ',', '.'))
                ->description('Salidas de caja')
                ->descriptionIcon('heroicon-m-arrow-up-tray')
                ->color('danger'),
        ];
    }
}
