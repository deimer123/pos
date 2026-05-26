<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VentasReportResource\Pages;
use App\Models\Factura;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;

class VentasReportResource extends Resource
{
    protected static ?string $model = Factura::class;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationLabel = 'Ventas';

    protected static ?string $modelLabel = 'Venta';

    protected static ?string $pluralModelLabel = 'Ventas';

    protected static ?string $navigationGroup = 'Reportes';

    protected static ?int $navigationSort = 2;

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

        return $table
            ->query(
                Factura::query()
                    ->with(['cliente', 'vendedorAsignado', 'cajero'])
                    ->where('empresa_id', $empresaId)
            )
            ->defaultSort('fecha', 'desc')
            ->paginated([10, 25, 50, 100])
            ->filtersLayout(FiltersLayout::AboveContent)
            ->recordAction('detalle')
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
                        ->when($data['desde'] ?? null, fn ($q, $date) => $q->whereDate('fecha', '>=', $date))
                        ->when($data['hasta'] ?? null, fn ($q, $date) => $q->whereDate('fecha', '<=', $date))),

                SelectFilter::make('tipo_pago')
                    ->label('Tipo de pago')
                    ->options([
                        'contado' => 'Contado',
                        'credito' => 'Credito',
                    ])
                    ->placeholder('Todos'),

                SelectFilter::make('estado_pago')
                    ->label('Estado')
                    ->options([
                        'pendiente' => 'Pendiente',
                        'parcial' => 'Parcial',
                        'pagada' => 'Pagada',
                        'vencida' => 'Vencida',
                    ])
                    ->placeholder('Todos'),
            ])
            ->actions([
                Action::make('detalle')
                    ->label('Detalle')
                    ->hiddenLabel()
                    ->icon('heroicon-o-eye')
                    ->iconButton()
                    ->tooltip('Ver detalle')
                    ->modalHeading(fn (Factura $record): string => $record->numero_visual)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->modalWidth('5xl')
                    ->modalContent(fn (Factura $record): View => view(
                        'filament.resources.ventas-report.detalle-factura',
                        [
                            'factura' => $record->load(['cliente', 'vendedorAsignado', 'cajero', 'detalles']),
                        ],
                    )),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('Factura')
                    ->formatStateUsing(fn (Factura $record): string => $record->numero_visual)
                    ->width('70px')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('fecha')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->size('xs')
                    ->width('120px')
                    ->sortable(),

                Tables\Columns\TextColumn::make('cliente.nombre')
                    ->label('Cliente')
                    ->placeholder('Consumidor final')
                    ->searchable()
                    ->limit(24)
                    ->size('xs'),

                Tables\Columns\TextColumn::make('cajero.name')
                    ->label('Cajero')
                    ->placeholder('-')
                    ->searchable()
                    ->limit(18)
                    ->size('xs'),

                Tables\Columns\TextColumn::make('tipo_pago')
                    ->label('Tipo')
                    ->size('xs')
                    ->badge(),

                Tables\Columns\TextColumn::make('medio_pago')
                    ->label('Medio')
                    ->placeholder('-')
                    ->size('xs')
                    ->badge(),

                Tables\Columns\TextColumn::make('estado_pago')
                    ->label('Estado')
                    ->size('xs')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'pagada' => 'success',
                        'parcial' => 'warning',
                        'vencida' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->formatStateUsing($money)
                    ->size('xs')
                    ->sortable()
                    ->alignRight(),

                Tables\Columns\TextColumn::make('saldo')
                    ->label('Saldo')
                    ->formatStateUsing($money)
                    ->size('xs')
                    ->sortable()
                    ->alignRight(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVentasReports::route('/'),
        ];
    }
}
