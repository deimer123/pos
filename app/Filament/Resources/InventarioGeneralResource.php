<?php

namespace App\Filament\Resources;

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
        $unitCostWithTax = function (Product $record): float {
            $cost = (float) $record->precio_costo;
            $discount = (float) ($record->descuento_comercial ?? 0);
            $tax = (float) ($record->iva_venta ?? 0);
            $discountedCost = $cost * (1 - ($discount / 100));

            return round($discountedCost * (1 + ($tax / 100)), 2);
        };
        $cellClass = 'border-r border-gray-200 dark:border-gray-700 px-2 py-1 text-xs';
        $nowrapCellClass = $cellClass . ' whitespace-nowrap';

        return $table
            ->query(
                Product::query()
                    ->where('empresa_id', $empresaId)
                    ->with('variantes')
            )
            ->striped()
            ->recordClasses(fn () => 'inventario-general-row')
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
                    ->extraCellAttributes(['class' => $nowrapCellClass])
                    ->extraHeaderAttributes(['class' => 'inventario-general-marker']),

                Tables\Columns\TextColumn::make('descripcion_larga')
                    ->label('Producto')
                    ->searchable()
                    ->limit(32)
                    ->wrap()
                    ->size('xs')
                    ->extraCellAttributes(['class' => $cellClass . ' leading-tight']),

                Tables\Columns\TextColumn::make('existencias')
                    ->label('Stock')
                    ->badge()
                    ->sortable()
                    // Para productos con variantes (talla/color), el stock
                    // real vive en cada variante, no en "existencias" del
                    // producto (que puede quedar desactualizado) -- se
                    // muestra la suma de todas las variantes activas, igual
                    // que en la tarjeta del POS.
                    ->getStateUsing(fn (Product $record) => $record->variantes->isNotEmpty()
                        ? $record->variantes->where('activo', true)->sum('stock')
                        : $record->existencias)
                    ->color(fn ($state) => match (true) {
                        $state < 0 => 'danger',
                        $state == 0 => 'warning',
                        default => 'success',
                    })
                    ->size('xs')
                    ->extraCellAttributes(['class' => $nowrapCellClass . ' text-center']),

                Tables\Columns\TextColumn::make('variantes_detalle')
                    ->label('Variantes')
                    ->getStateUsing(fn (Product $record) => $record->variantes->isEmpty()
                        ? null
                        : $record->variantes
                            ->map(fn ($v) => trim(($v->atributos['talla'] ?? '') . ' ' . ($v->atributos['color'] ?? '')) . ': ' . (float) $v->stock)
                            ->implode(' · '))
                    ->placeholder('—')
                    ->wrap()
                    ->size('xs')
                    ->extraCellAttributes(['class' => $cellClass . ' leading-tight']),

                Tables\Columns\TextColumn::make('precio_costo')
                    ->label('Costo')
                    ->formatStateUsing($money)
                    ->size('xs')
                    ->extraCellAttributes(['class' => $nowrapCellClass]),

                Tables\Columns\TextColumn::make('precio_venta1')
                    ->label('Venta')
                    ->formatStateUsing($money)
                    ->size('xs')
                    ->extraCellAttributes(['class' => $nowrapCellClass]),

                Tables\Columns\TextColumn::make('iva_venta')
                    ->label('IVA %')
                    ->size('xs')
                    ->extraCellAttributes(['class' => $nowrapCellClass . ' text-center']),

                Tables\Columns\TextColumn::make('costo_total')
                    ->label('Costo Total')
                    ->getStateUsing(fn (Product $record) => $unitCostWithTax($record))
                    ->formatStateUsing($money)
                    ->size('xs')
                    ->extraCellAttributes(['class' => $nowrapCellClass]),

                Tables\Columns\TextColumn::make('utilidad1')
                    ->label('Utilidad %')
                    ->getStateUsing(function (Product $record) use ($unitCostWithTax): float {
                        $salePrice = (float) $record->precio_venta1;

                        if ($salePrice <= 0) {
                            return 0;
                        }

                        return round((($salePrice - $unitCostWithTax($record)) / $salePrice) * 100, 2);
                    })
                    ->badge()
                    ->color(fn ($state) => ((float) $state) < 0 ? 'danger' : 'success')
                    ->size('xs')
                    ->extraCellAttributes(['class' => $nowrapCellClass . ' text-center']),

                Tables\Columns\TextColumn::make('utilidad_total')
                    ->label('Utilidad $')
                    ->getStateUsing(fn (Product $record) =>
                        (float) $record->precio_venta1 - $unitCostWithTax($record)
                    )
                    ->formatStateUsing($money)
                    ->color(fn ($state) => ((float) $state) < 0 ? 'danger' : 'success')
                    ->size('xs')
                    ->extraCellAttributes(['class' => 'px-2 py-1 text-xs whitespace-nowrap']),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInventarioGenerals::route('/'),
        ];
    }
}
