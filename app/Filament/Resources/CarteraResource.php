<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CarteraResource\Pages;
use App\Models\Actor;
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
use Illuminate\Database\Eloquent\Builder;

class CarteraResource extends Resource
{
    protected static ?string $model = Factura::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationLabel = 'Cartera';

    protected static ?string $modelLabel = 'Cuenta por cobrar';

    protected static ?string $pluralModelLabel = 'Cartera';

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

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function table(Table $table): Table
    {
        $empresaId = auth()->user()->getEmpresaActualId();
        $money = fn ($state): string => '$ ' . number_format((float) $state, 0, ',', '.');

        return $table
            ->query(
                Factura::query()
                    ->with(['cliente', 'pagos', 'detalles'])
                    ->where('empresa_id', $empresaId)
                    ->where('tipo_pago', 'credito')
            )
            ->recordClasses(fn () => 'cartera-row')
            ->defaultSort('fecha_vencimiento', 'asc')
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
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['desde'] ?? null, fn ($q, $date) => $q->whereDate('fecha', '>=', $date))
                        ->when($data['hasta'] ?? null, fn ($q, $date) => $q->whereDate('fecha', '<=', $date))),

                SelectFilter::make('cliente_id')
                    ->label('Cliente')
                    ->options(fn (): array =>
                        Actor::query()
                            ->where('empresa_id', $empresaId)
                            ->whereIn('clasificacion', ['cliente', 'cliente_proveedor'])
                            ->whereNotNull('nombre')
                            ->orderBy('nombre')
                            ->get()
                            ->mapWithKeys(fn (Actor $actor): array => [
                                $actor->getKey() => (string) ($actor->nombre ?: $actor->identificacion ?: 'Cliente #' . $actor->getKey()),
                            ])
                            ->all()
                    )
                    ->searchable()
                    ->preload()
                    ->placeholder('Todos'),

                SelectFilter::make('estado_cartera')
                    ->label('Estado cartera')
                    ->options([
                        'vigente' => 'Vigente',
                        'vencida' => 'Vencida',
                        'pagada' => 'Pagada',
                        'pendiente' => 'Pendiente con saldo',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'vigente' => $query->where('saldo', '>', 0)
                            ->where(function ($q) {
                                $q->whereNull('fecha_vencimiento')
                                    ->orWhereDate('fecha_vencimiento', '>=', now()->toDateString());
                            }),
                        'vencida' => $query->where('saldo', '>', 0)
                            ->whereNotNull('fecha_vencimiento')
                            ->whereDate('fecha_vencimiento', '<', now()->toDateString()),
                        'pagada' => $query->where(function ($q) {
                            $q->where('estado_pago', 'pagada')->orWhere('saldo', '<=', 0);
                        }),
                        'pendiente' => $query->where('saldo', '>', 0),
                        default => $query,
                    })
                    ->placeholder('Todos'),
            ])
            ->actions([
                Action::make('detalle')
                    ->label('Detalle')
                    ->hiddenLabel()
                    ->icon('heroicon-o-eye')
                    ->iconButton()
                    ->tooltip('Ver factura')
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
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('fecha')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('fecha_vencimiento')
                    ->label('Vence')
                    ->date('d/m/Y')
                    ->sortable()
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('cliente.nombre')
                    ->label('Cliente')
                    ->placeholder('Consumidor final')
                    ->searchable()
                    ->limit(32),

                Tables\Columns\TextColumn::make('total')
                    ->label('Valor factura')
                    ->formatStateUsing($money)
                    ->sortable()
                    ->alignRight(),

                Tables\Columns\TextColumn::make('pagado')
                    ->label('Pagado')
                    ->getStateUsing(fn (Factura $record): float => (float) $record->pagos->sum('monto'))
                    ->formatStateUsing($money)
                    ->alignRight(),

                Tables\Columns\TextColumn::make('saldo')
                    ->label('Saldo pendiente')
                    ->formatStateUsing($money)
                    ->sortable()
                    ->alignRight(),

                Tables\Columns\TextColumn::make('estado_cartera')
                    ->label('Estado')
                    ->badge()
                    ->getStateUsing(function (Factura $record): string {
                        if ((float) $record->saldo <= 0 || $record->estado_pago === 'pagada') {
                            return 'Pagada';
                        }

                        if ($record->fecha_vencimiento && $record->fecha_vencimiento->toDateString() < now()->toDateString()) {
                            return 'Vencida';
                        }

                        return 'Vigente';
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'Pagada' => 'success',
                        'Vencida' => 'danger',
                        default => 'warning',
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCarteras::route('/'),
        ];
    }
}
