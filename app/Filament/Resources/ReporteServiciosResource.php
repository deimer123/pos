<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReporteServiciosResource\Pages;
use App\Models\FacturaDetalle;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ReporteServiciosResource extends Resource
{
    protected static ?string $model = FacturaDetalle::class;

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?string $navigationLabel = 'Servicios';

    protected static ?string $modelLabel = 'Servicio facturado';

    protected static ?string $pluralModelLabel = 'Servicios facturados';

    protected static ?string $navigationGroup = 'Reportes';

    protected static ?int $navigationSort = 5;

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
        $money = fn ($state): string => '$ ' . number_format((float) $state, 0, ',', '.');
        $percent = fn ($state): string => $state === null ? '-' : number_format((float) $state, 2, ',', '.') . ' %';

        return $table
            ->query(
                FacturaDetalle::query()
                    ->whereNotNull('tipo_servicio')
                    ->whereHas('factura', fn ($q) => $q->where('empresa_id', $empresaId))
                    ->with(['factura.cliente'])
            )
            ->defaultSort('id', 'desc')
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

                SelectFilter::make('tipo_servicio')
                    ->label('Tipo')
                    ->options([
                        'propio'  => 'Propio',
                        'tercero' => 'Tercero',
                    ])
                    ->placeholder('Todos'),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('factura.fecha')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->size('xs')
                    ->sortable(),

                Tables\Columns\TextColumn::make('descripcion_larga')
                    ->label('Servicio')
                    ->searchable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('factura.cliente.nombre')
                    ->label('Cliente')
                    ->placeholder('Consumidor final')
                    ->limit(24)
                    ->size('xs'),

                Tables\Columns\TextColumn::make('cantidad')
                    ->label('Cant.')
                    ->size('xs')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('subtotal')
                    ->label('Total cobrado')
                    ->formatStateUsing($money)
                    ->alignRight(),

                Tables\Columns\TextColumn::make('tipo_servicio')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state === 'propio' ? 'Propio' : 'Tercero')
                    ->color(fn ($state) => $state === 'propio' ? 'success' : 'warning'),

                Tables\Columns\TextColumn::make('porcentaje_empresa')
                    ->label('% Empresa')
                    ->formatStateUsing($percent)
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('valor_empresa')
                    ->label('Para la empresa')
                    ->formatStateUsing($money)
                    ->color('success')
                    ->alignRight(),

                Tables\Columns\TextColumn::make('valor_tercero')
                    ->label('Para tercero/mecánico')
                    ->formatStateUsing($money)
                    ->color('warning')
                    ->alignRight(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReporteServicios::route('/'),
        ];
    }
}
