<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReporteMasVendidosResource\Pages;
use App\Models\ConfiguracionEmpresa;
use App\Models\FacturaDetalle;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;

// Reporte de productos más vendidos. Cada fila es un producto -- o una
// variante puntual (talla/color) si la empresa usa variantes -- con la
// cantidad total vendida y el valor vendido en el rango de fechas.
// Mismo patrón de agregación que ReporteDescuentosResource.
class ReporteMasVendidosResource extends Resource
{
    protected static ?string $model = FacturaDetalle::class;

    protected static ?string $navigationIcon = 'heroicon-o-trophy';

    protected static ?string $navigationLabel = 'Más vendidos';

    protected static ?string $modelLabel = 'Más vendido';

    protected static ?string $pluralModelLabel = 'Más vendidos';

    protected static ?string $navigationGroup = 'Reportes';

    protected static ?int $navigationSort = 3;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function canViewAny(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin_empresa');
    }

    protected static function empresaUsaVariantes(): bool
    {
        return (bool) ConfiguracionEmpresa::where('empresa_id', auth()->user()->getEmpresaActualId())
            ->value('usa_variantes');
    }

    public static function baseQuery(int $empresaId)
    {
        // Si la empresa usa variantes, cada talla/color es una fila propia
        // (asi se ve cual talla rota mas); si no, se agrupa solo por
        // producto y la columna de variante ni se muestra.
        $agruparPorVariante = static::empresaUsaVariantes();

        $query = FacturaDetalle::query()
            ->selectRaw('MIN(factura_detalles.id) as id')
            ->selectRaw('factura_detalles.producto_id')
            ->selectRaw('MAX(factura_detalles.descripcion_larga) as descripcion_larga')
            ->selectRaw('SUM(factura_detalles.cantidad) as cantidad_total')
            ->selectRaw('SUM(factura_detalles.devuelto_cantidad) as cantidad_devuelta')
            ->selectRaw('SUM(factura_detalles.subtotal) as total_vendido')
            ->whereHas('factura', fn ($q) => $q->where('empresa_id', $empresaId))
            ->whereHas('producto', fn ($q) => $q->where('empresa_id', $empresaId))
            ->where('factura_detalles.producto_id', '!=', '10001');

        if ($agruparPorVariante) {
            $query
                ->selectRaw('factura_detalles.producto_variante_id')
                ->selectRaw('MAX(pv.nombre) as variante_nombre')
                ->leftJoin('producto_variantes as pv', 'pv.id', '=', 'factura_detalles.producto_variante_id')
                ->groupBy('factura_detalles.producto_id', 'factura_detalles.producto_variante_id');
        } else {
            $query->groupBy('factura_detalles.producto_id');
        }

        return $query;
    }

    public static function table(Table $table): Table
    {
        $empresaId = auth()->user()->getEmpresaActualId();
        $money = fn ($state): string => '$ ' . number_format((float) $state, 0, ',', '.');

        return $table
            ->query(static::baseQuery($empresaId))
            ->defaultSort('cantidad_total', 'desc')
            ->paginated([10, 25, 50, 100])
            ->filtersLayout(FiltersLayout::AboveContent)
            ->filters([
                Filter::make('fecha')
                    ->form([
                        DatePicker::make('desde')
                            ->label('Desde')
                            ->default(now()->startOfMonth()),

                        DatePicker::make('hasta')
                            ->label('Hasta')
                            ->default(now()),
                    ])
                    ->query(fn ($query, array $data) => $query
                        ->when($data['desde'] ?? null, fn ($q, $date) => $q->whereHas('factura', fn ($f) => $f->whereDate('fecha', '>=', $date)))
                        ->when($data['hasta'] ?? null, fn ($q, $date) => $q->whereHas('factura', fn ($f) => $f->whereDate('fecha', '<=', $date)))),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('descripcion_larga')
                    ->label('Producto')
                    ->searchable(query: function ($query, string $search) {
                        $query->where('factura_detalles.descripcion_larga', 'like', "%{$search}%");
                    }),

                Tables\Columns\TextColumn::make('variante_nombre')
                    ->label('Variante (talla/color)')
                    ->placeholder('-')
                    ->badge()
                    ->color('info')
                    ->visible(fn () => static::empresaUsaVariantes()),

                Tables\Columns\TextColumn::make('cantidad_total')
                    ->label('Cantidad vendida')
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\TextColumn::make('cantidad_devuelta')
                    ->label('Devuelto')
                    ->alignCenter()
                    ->formatStateUsing(fn ($state) => (float) $state > 0 ? $state : '-')
                    ->color(fn ($state) => (float) $state > 0 ? 'danger' : 'gray')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_vendido')
                    ->label('Valor vendido')
                    ->formatStateUsing($money)
                    ->color('success')
                    ->alignRight()
                    ->sortable(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReporteMasVendidos::route('/'),
        ];
    }
}
