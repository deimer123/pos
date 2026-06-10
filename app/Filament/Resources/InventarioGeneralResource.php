<?php

namespace App\Filament\Resources;

use App\Filament\Clusters\Contabilidad;
use App\Filament\Resources\InventarioGeneralResource\Pages;
use App\Exports\InventarioGeneralExport;
use App\Models\Actor;
use App\Models\ConfiguracionEmpresa;
use App\Models\Familia;
use App\Models\Product;
use App\Models\Subfamilia;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Maatwebsite\Excel\Facades\Excel;

class InventarioGeneralResource extends Resource
{
    protected static ?string $model = Product::class;
    protected static ?string $cluster = Contabilidad::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    protected static ?string $navigationLabel = 'Inventario';

    protected static ?string $modelLabel = 'Inventario de Producto';

    protected static ?string $pluralModelLabel = 'Inventario de Productos';

    protected static ?string $navigationGroup = 'Reportes';

    protected static ?int $navigationSort = 1;

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

    public static function table(Table $table): Table
    {
        $empresaId = auth()->user()->getEmpresaActualId();
        $money = fn ($state): string => '$ ' . number_format((float) $state, 2, ',', '.');
        $nombreAlmacen = ConfiguracionEmpresa::query()
            ->where('empresa_id', $empresaId)
            ->value('nombre_empresa') ?? auth()->user()->name ?? 'Almacen';
        $financials = function (Product $record): array {
            $stock = (float) $record->existencias;
            $positive = max($stock, 0);
            $negative = abs(min($stock, 0));
            $costUnit = (float) $record->precio_costo;
            $ivaCompraPct = (float) ($record->iva_compra ?? 0);
            $ivaVentaPct = (float) ($record->iva_venta ?? 0);
            $ventaUnit = (float) $record->precio_venta1;

            $valorCosto = round($stock * $costUnit, 2);
            $valorIvaCompra = round($valorCosto * ($ivaCompraPct / 100), 2);
            $totalCosto = round($valorCosto + $valorIvaCompra, 2);
            $valorIvaVentaUnit = round($ventaUnit * ($ivaVentaPct / 100), 2);
            $totalVentas = round($stock * $ventaUnit, 2);
            $valorVentasConIva = round($totalVentas + ($stock * $valorIvaVentaUnit), 2);

            return compact(
                'stock',
                'positive',
                'negative',
                'costUnit',
                'ivaCompraPct',
                'valorCosto',
                'valorIvaCompra',
                'totalCosto',
                'ivaVentaPct',
                'ventaUnit',
                'totalVentas',
                'valorVentasConIva',
            );
        };
        $cellClass = 'border-r border-gray-200 dark:border-gray-700 px-2 py-1 text-xs';
        $nowrapCellClass = $cellClass . ' whitespace-nowrap';

        return $table
            ->query(
                Product::query()
                    ->with(['cuentaContable'])
                    ->where('empresa_id', $empresaId)
            )
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->defaultSort('existencias', 'asc')
            ->filtersLayout(FiltersLayout::AboveContent)
            ->headerActions([
                Action::make('descargar_excel')
                    ->label('Descargar Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->action(fn () => Excel::download(
                        new InventarioGeneralExport($empresaId, $nombreAlmacen),
                        'inventario-general-' . now()->format('Y-m-d') . '.xlsx'
                    )),
            ])
            ->filters([
                SelectFilter::make('id_proveedor')
                    ->label('Proveedor')
                    ->options(fn () =>
                        Actor::query()
                            ->where('empresa_id', $empresaId)
                            ->whereIn('clasificacion', ['proveedor', 'cliente_proveedor'])
                            ->orderBy('nombre')
                            ->pluck('nombre', 'id_clip_pro')
                    )
                    ->searchable()
                    ->preload()
                    ->placeholder('Todos los proveedores'),

                SelectFilter::make('id_familia1')
                    ->label('Familia')
                    ->options(fn () =>
                        Familia::query()
                            ->where('empresa_id', $empresaId)
                            ->orderBy('nombre')
                            ->pluck('nombre', 'id')
                    )
                    ->searchable()
                    ->preload()
                    ->placeholder('Todas las familias'),

                SelectFilter::make('id_familia2')
                    ->label('Subfamilia')
                    ->options(fn () =>
                        Subfamilia::query()
                            ->where('empresa_id', $empresaId)
                            ->orderBy('nombre')
                            ->pluck('nombre', 'id_familia2')
                    )
                    ->searchable()
                    ->preload()
                    ->placeholder('Todas las subfamilias'),

                SelectFilter::make('estado_stock')
                    ->label('Estado de stock')
                    ->options([
                        'negativos' => 'Negativos',
                        'cero' => 'En cero',
                        'positivos' => 'Positivos',
                    ])
                    ->query(fn ($query, array $data) => match ($data['value'] ?? null) {
                        'negativos' => $query->where('existencias', '<', 0),
                        'cero' => $query->where('existencias', 0),
                        'positivos' => $query->where('existencias', '>', 0),
                        default => $query,
                    })
                    ->placeholder('Todos los estados'),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('id_producto')
                    ->label('Codigo')
                    ->searchable()
                    ->sortable()
                    ->size('xs')
                    ->extraCellAttributes(['class' => $nowrapCellClass]),

                Tables\Columns\TextColumn::make('descripcion_larga')
                    ->label('Producto')
                    ->searchable()
                    ->limit(32)
                    ->wrap()
                    ->size('xs')
                    ->extraCellAttributes(['class' => $cellClass . ' leading-tight']),

                Tables\Columns\TextColumn::make('cuenta_contable')
                    ->label('Cuenta contable')
                    ->getStateUsing(fn (Product $record) => $record->cuentaContable?->codigo ?? '-')
                    ->size('xs')
                    ->extraCellAttributes(['class' => $nowrapCellClass . ' text-center']),

                Tables\Columns\TextColumn::make('existencias_positivas')
                    ->label('Positivas')
                    ->getStateUsing(fn (Product $record) => max((float) $record->existencias, 0))
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 2, ',', '.'))
                    ->size('xs')
                    ->extraCellAttributes(['class' => $nowrapCellClass . ' text-right']),

                Tables\Columns\TextColumn::make('existencias_negativas')
                    ->label('Negativas')
                    ->getStateUsing(fn (Product $record) => abs(min((float) $record->existencias, 0)))
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 2, ',', '.'))
                    ->size('xs')
                    ->extraCellAttributes(['class' => $nowrapCellClass . ' text-right']),

                Tables\Columns\TextColumn::make('costo_unitario')
                    ->label('Costo unitario')
                    ->getStateUsing(fn (Product $record) => (float) $record->precio_costo)
                    ->formatStateUsing($money)
                    ->size('xs')
                    ->extraCellAttributes(['class' => $nowrapCellClass . ' text-right']),

                Tables\Columns\TextColumn::make('iva_compra')
                    ->label('IVA compra %')
                    ->getStateUsing(fn (Product $record) => (float) ($record->iva_compra ?? 0))
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 2, ',', '.'))
                    ->size('xs')
                    ->extraCellAttributes(['class' => $nowrapCellClass . ' text-right']),

                Tables\Columns\TextColumn::make('valor_costo')
                    ->label('Valor costo')
                    ->getStateUsing(fn (Product $record) => $financials($record)['valorCosto'])
                    ->formatStateUsing($money)
                    ->size('xs')
                    ->extraCellAttributes(['class' => $nowrapCellClass . ' text-right']),

                Tables\Columns\TextColumn::make('valor_iva_compra')
                    ->label('Valor IVA compra')
                    ->getStateUsing(fn (Product $record) => $financials($record)['valorIvaCompra'])
                    ->formatStateUsing($money)
                    ->size('xs')
                    ->extraCellAttributes(['class' => $nowrapCellClass . ' text-right']),

                Tables\Columns\TextColumn::make('total_costo')
                    ->label('Total costo')
                    ->getStateUsing(fn (Product $record) => $financials($record)['totalCosto'])
                    ->formatStateUsing($money)
                    ->size('xs')
                    ->extraCellAttributes(['class' => $nowrapCellClass . ' text-right']),

                Tables\Columns\TextColumn::make('iva_venta')
                    ->label('IVA ventas %')
                    ->getStateUsing(fn (Product $record) => (float) ($record->iva_venta ?? 0))
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 2, ',', '.'))
                    ->size('xs')
                    ->extraCellAttributes(['class' => $nowrapCellClass . ' text-right']),

                Tables\Columns\TextColumn::make('venta_unitaria')
                    ->label('Valor venta unitario')
                    ->getStateUsing(fn (Product $record) => $financials($record)['ventaUnit'])
                    ->formatStateUsing($money)
                    ->size('xs')
                    ->extraCellAttributes(['class' => $nowrapCellClass . ' text-right']),

                Tables\Columns\TextColumn::make('total_ventas')
                    ->label('Total ventas')
                    ->getStateUsing(fn (Product $record) => $financials($record)['totalVentas'])
                    ->formatStateUsing($money)
                    ->size('xs')
                    ->extraCellAttributes(['class' => $nowrapCellClass . ' text-right']),

                Tables\Columns\TextColumn::make('valor_ventas_existencias')
                    ->label('Valor ventas por existencias')
                    ->getStateUsing(fn (Product $record) => $financials($record)['valorVentasConIva'])
                    ->formatStateUsing($money)
                    ->size('xs')
                    ->extraCellAttributes(['class' => 'px-2 py-1 text-xs whitespace-nowrap text-right']),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInventarioGenerals::route('/'),
        ];
    }
}
