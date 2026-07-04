<?php

namespace App\Filament\Widgets;

use App\Models\Factura;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class VentasUltimos7Dias extends ChartWidget
{
    public static function canView(): bool
    {
        return auth()->user()?->hasRole('super_admin') !== true;
    }

    protected static ?string $heading = 'Ventas por periodo';

    protected int|string|array $columnSpan = 1;

    protected static ?int $sort = 3;

    protected static ?string $maxHeight = '250px';

    protected function getFilters(): ?array
    {
        return [
            'ultimos_7' => 'Ultimos 7 dias',
            'semana_actual' => 'Semana actual',
            'semana_anterior' => 'Semana anterior',
            'mes_actual' => 'Mes actual por semanas',
            'mes_anterior' => 'Mes anterior por semanas',
            'dos_meses_atras' => 'Hace 2 meses por semanas',
        ];
    }

    protected function getData(): array
    {
        $empresaId = auth()->user()->getEmpresaActualId();
        $filtro = $this->filter ?? 'ultimos_7';

        if (in_array($filtro, ['mes_actual', 'mes_anterior', 'dos_meses_atras'], true)) {
            return $this->getMonthlyData($empresaId, $filtro);
        }

        $inicio = match ($filtro) {
            'semana_actual' => now()->startOfWeek(),
            'semana_anterior' => now()->subWeek()->startOfWeek(),
            default => now()->subDays(6)->startOfDay(),
        };

        $labels = [];
        $data = [];

        for ($i = 0; $i < 7; $i++) {
            $fecha = $inicio->copy()->addDays($i);
            $labels[] = $fecha->translatedFormat('D');

            $total = Factura::query()
                ->where('empresa_id', $empresaId)
                ->whereDate('fecha', $fecha)
                ->where('total', '>', 0)
                ->sum('total');

            $total -= $this->passthroughEnRango($empresaId, $fecha->copy()->startOfDay(), $fecha->copy()->endOfDay());

            $data[] = round(max(0, $total));
        }

        return $this->chartData($labels, $data, 'Ventas');
    }

    protected function getMonthlyData(int $empresaId, string $filtro): array
    {
        $mes = match ($filtro) {
            'mes_anterior' => now()->subMonthNoOverflow(),
            'dos_meses_atras' => now()->subMonthsNoOverflow(2),
            default => now(),
        };

        $inicioMes = $mes->copy()->startOfMonth();
        $finMes = $mes->copy()->endOfMonth();
        $inicio = $inicioMes->copy();
        $labels = [];
        $data = [];
        $semana = 1;

        while ($inicio->lte($finMes)) {
            $fin = $inicio->copy()->endOfWeek()->min($finMes);
            $labels[] = 'Sem. ' . $semana;

            $total = Factura::query()
                ->where('empresa_id', $empresaId)
                ->whereBetween('fecha', [$inicio->copy()->startOfDay(), $fin->copy()->endOfDay()])
                ->where('total', '>', 0)
                ->sum('total');

            $total -= $this->passthroughEnRango($empresaId, $inicio->copy()->startOfDay(), $fin->copy()->endOfDay());

            $data[] = round(max(0, $total));
            $inicio = $fin->copy()->addDay()->startOfDay();
            $semana++;
        }

        return $this->chartData($labels, $data, $mes->translatedFormat('F Y'));
    }

    // Item manual (codigo 10001): reembolso a terceros sin margen, no cuenta como venta propia.
    protected function passthroughEnRango(int $empresaId, $inicio, $fin): float
    {
        return (float) DB::table('factura_detalles as fd')
            ->join('facturas as f', 'f.id', '=', 'fd.factura_id')
            ->where('f.empresa_id', $empresaId)
            ->whereBetween('f.fecha', [$inicio, $fin])
            ->where('fd.producto_id', '10001')
            ->sum('fd.subtotal');
    }

    protected function chartData(array $labels, array $data, string $label): array
    {
        return [
            'datasets' => [
                [
                    'label' => $label,
                    'data' => $data,
                    'backgroundColor' => '#6366f1',
                    'borderColor' => '#4f46e5',
                    'borderRadius' => 8,
                    'maxBarThickness' => 52,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
