<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReporteDescuentosResource\Pages;
use App\Models\FacturaDetalle;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;

class ReporteDescuentosResource extends Resource
{
    protected static ?string $model = FacturaDetalle::class;

    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';

    protected static ?string $navigationLabel = 'Descuentos';

    protected static ?string $modelLabel = 'Descuento';

    protected static ?string $pluralModelLabel = 'Descuentos';

    protected static ?string $navigationGroup = 'Reportes';

    protected static ?int $navigationSort = 6;

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

    public static function baseQuery(int $empresaId)
    {
        return FacturaDetalle::query()
            ->selectRaw('MIN(factura_detalles.id) as id')
            ->selectRaw('factura_detalles.producto_id')
            ->selectRaw('MAX(factura_detalles.descripcion_larga) as descripcion_larga')
            ->selectRaw('SUM(factura_detalles.cantidad) as cantidad_total')
            ->selectRaw('AVG(factura_detalles.descuento) as descuento_promedio')
            ->selectRaw('SUM(factura_detalles.subtotal) as valor_con_descuento')
            ->selectRaw('SUM(factura_detalles.cantidad * (factura_detalles.precio / NULLIF(1 + factura_detalles.descuento / 100, 0)) - factura_detalles.subtotal) as valor_descontado')
            ->whereHas('factura', fn ($q) => $q->where('empresa_id', $empresaId))
            ->whereHas('producto', fn ($q) => $q->where('tipo_producto', 'producto')->where('empresa_id', $empresaId))
            ->where('factura_detalles.producto_id', '!=', '10001')
            ->where('factura_detalles.descuento', '<', 0)
            ->groupBy('factura_detalles.producto_id');
    }

    public static function table(Table $table): Table
    {
        $empresaId = auth()->user()->getEmpresaActualId();
        $money = fn ($state): string => '$ ' . number_format((float) $state, 0, ',', '.');
        $percent = fn ($state): string => $state === null ? '-' : number_format(abs((float) $state), 2, ',', '.') . ' %';

        return $table
            ->query(static::baseQuery($empresaId))
            ->defaultSort('valor_descontado', 'desc')
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
                        $query->having('descripcion_larga', 'like', "%{$search}%");
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('cantidad_total')
                    ->label('Cantidad vendida')
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\TextColumn::make('descuento_promedio')
                    ->label('% Descuento')
                    ->formatStateUsing($percent)
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\TextColumn::make('valor_descontado')
                    ->label('Valor descontado')
                    ->formatStateUsing($money)
                    ->color('danger')
                    ->alignRight()
                    ->sortable(),

                Tables\Columns\TextColumn::make('valor_con_descuento')
                    ->label('Valor con descuento')
                    ->formatStateUsing($money)
                    ->color('success')
                    ->alignRight()
                    ->sortable(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReporteDescuentos::route('/'),
        ];
    }
}
