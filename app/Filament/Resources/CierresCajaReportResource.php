<?php

namespace App\Filament\Resources;

use App\Filament\Clusters\Contabilidad;
use App\Filament\Resources\CierresCajaReportResource\Pages;
use App\Models\Caja;
use App\Models\User;
use App\Support\CajaReportMetrics;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;

class CierresCajaReportResource extends Resource
{
    protected static ?string $model = Caja::class;
    protected static ?string $cluster = Contabilidad::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Cierres de caja';

    protected static ?string $modelLabel = 'Cierre de Caja';

    protected static ?string $pluralModelLabel = 'Cierres de Caja';

    protected static ?string $navigationGroup = 'Reportes';

    protected static ?int $navigationSort = 4;

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
        $percent = fn ($state): string => number_format((float) $state, 2, ',', '.') . ' %';
        $metricas = function (Caja $record, $livewire): array {
            $desde = data_get($livewire, 'tableFilters.fecha.desde');
            $hasta = data_get($livewire, 'tableFilters.fecha.hasta');

            return CajaReportMetrics::forCajaPeriod(
                $record,
                $desde ? Carbon::parse($desde)->startOfDay() : null,
                $hasta ? Carbon::parse($hasta)->endOfDay() : null,
            );
        };

        return $table
            ->query(
                Caja::query()
                    ->with('usuario')
                    ->where(fn ($query) =>
                        $query->where('empresa_id', $empresaId)
                            ->orWhere(fn ($legacyQuery) =>
                                $legacyQuery->whereNull('empresa_id')
                                    ->where('user_id', $empresaId)
                            )
                    )
            )
            ->defaultSort('opened_at', 'desc')
            ->paginated([10, 25, 50, 100])
            ->filtersLayout(FiltersLayout::AboveContent)
            ->filters([
                Filter::make('fecha')
                    ->form([
                        DatePicker::make('desde')
                            ->label('Desde')
                            ->default(today()),

                        DatePicker::make('hasta')
                            ->label('Hasta')
                            ->default(today()),
                    ])
                    ->query(fn ($query, array $data) => $query
                        ->when($data['desde'] ?? null, fn ($q, $date) =>
                            $q->where(fn ($subQuery) =>
                                $subQuery->whereNull('closed_at')
                                    ->orWhereDate('closed_at', '>=', $date)
                            )
                        )
                        ->when($data['hasta'] ?? null, fn ($q, $date) =>
                            $q->whereDate('opened_at', '<=', $date)
                        )),

                Filter::make('mostrar_detalle_cards')
                    ->label('Cards')
                    ->form([
                        Toggle::make('activo')
                            ->label('Mostrar detalle de cards')
                            ->default(false),
                    ])
                    ->query(fn ($query) => $query),
                SelectFilter::make('user_id')
                    ->label('Responsable')
                    ->options(fn () =>
                        User::query()
                            ->where(fn ($query) =>
                                $query->where('id', $empresaId)
                                    ->orWhere('empresa_id', $empresaId)
                            )
                            ->role(['admin_empresa', 'cajero'])
                            ->orderBy('name')
                            ->pluck('name', 'id')
                    )
                    ->searchable()
                    ->preload()
                    ->placeholder('Todos'),

                SelectFilter::make('estado')
                    ->label('Estado')
                    ->options([
                        'abierta' => 'Abierta',
                        'cerrada' => 'Cerrada',
                    ])
                    ->placeholder('Todos'),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('Turno')
                    ->sortable(),

                Tables\Columns\TextColumn::make('usuario.name')
                    ->label('Responsable')
                    ->searchable()
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('opened_at')
                    ->label('Apertura')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('closed_at')
                    ->label('Cierre')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('Abierta')
                    ->sortable(),

                Tables\Columns\TextColumn::make('ventas')
                    ->label('Ventas')
                    ->getStateUsing(fn (Caja $record, $livewire) => $metricas($record, $livewire)['ventas'])
                    ->formatStateUsing($money)
                    ->alignRight(),

                Tables\Columns\TextColumn::make('costo')
                    ->label('Costo venta')
                    ->getStateUsing(fn (Caja $record, $livewire) => $metricas($record, $livewire)['costo'])
                    ->formatStateUsing($money)
                    ->alignRight(),

                Tables\Columns\TextColumn::make('utilidad')
                    ->label('Utilidad')
                    ->getStateUsing(fn (Caja $record, $livewire) => $metricas($record, $livewire)['utilidad'])
                    ->formatStateUsing($money)
                    ->color(fn ($state) => ((float) $state) < 0 ? 'danger' : 'success')
                    ->alignRight(),

                Tables\Columns\TextColumn::make('utilidad_pct')
                    ->label('Utilidad %')
                    ->getStateUsing(fn (Caja $record, $livewire) => $metricas($record, $livewire)['utilidad_pct'])
                    ->formatStateUsing($percent)
                    ->badge()
                    ->color(fn ($state) => ((float) $state) < 0 ? 'danger' : 'success')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('recaudos')
                    ->label('Recaudos')
                    ->getStateUsing(fn (Caja $record, $livewire) => $metricas($record, $livewire)['recaudos'])
                    ->formatStateUsing($money)
                    ->alignRight(),

                Tables\Columns\TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->color(fn ($state) => $state === 'abierta' ? 'warning' : 'success'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCierresCajaReports::route('/'),
        ];
    }
}
