<?php

namespace App\Filament\Resources;

use App\Filament\Clusters\Contabilidad;
use App\Filament\Resources\CuentasPorPagarResource\Pages;
use App\Models\Compra;
use App\Models\Actor;
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

class CuentasPorPagarResource extends Resource
{
    protected static ?string $model = Compra::class;

    protected static ?string $cluster = Contabilidad::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Cuentas por pagar';

    protected static ?string $modelLabel = 'Cuenta por pagar';

    protected static ?string $pluralModelLabel = 'Cuentas por pagar';

    public static function canViewAny(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin_empresa');
    }

    public static function canCreate(): bool { return false; }
    public static function canEdit($record): bool { return false; }
    public static function canDelete($record): bool { return false; }

    public static function table(Table $table): Table
    {
        $empresaId = auth()->user()->getEmpresaActualId();
        $money = fn ($state): string => '$ ' . number_format((float) $state, 0, ',', '.');

        return $table
            ->query(
                Compra::query()
                    ->with(['proveedor', 'pagos', 'detalles'])
                    ->where('empresa_id', $empresaId)
                    ->where('tipo_pago', 'credito')
            )
            ->defaultSort('fecha_vencimiento', 'asc')
            ->paginated([10, 25, 50, 100])
            ->filtersLayout(FiltersLayout::AboveContent)
            ->filters([
                Filter::make('fecha')
                    ->form([
                        DatePicker::make('desde')->label('Desde')->default(now()->startOfMonth()),
                        DatePicker::make('hasta')->label('Hasta')->default(now()),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['desde'] ?? null, fn ($q, $date) => $q->whereDate('fecha', '>=', $date))
                        ->when($data['hasta'] ?? null, fn ($q, $date) => $q->whereDate('fecha', '<=', $date))),

                SelectFilter::make('proveedor_id')
                    ->label('Proveedor')
                    ->options(fn (): array =>
                        Actor::query()
                            ->where('empresa_id', $empresaId)
                            ->whereIn('clasificacion', ['proveedor', 'cliente_proveedor'])
                            ->whereNotNull('nombre')
                            ->orderBy('nombre')
                            ->get()
                            ->mapWithKeys(fn (Actor $actor): array => [
                                $actor->getKey() => (string) ($actor->nombre ?: $actor->identificacion ?: 'Proveedor #' . $actor->getKey()),
                            ])
                            ->all()
                    )
                    ->searchable()
                    ->preload()
                    ->placeholder('Todos'),
            ])
            ->actions([
                Action::make('detalle')
                    ->label('Detalle')
                    ->hiddenLabel()
                    ->icon('heroicon-o-eye')
                    ->iconButton()
                    ->tooltip('Ver detalle')
                    ->modalHeading(fn (Compra $record): string => $record->numero_factura ?? ('Compra #' . $record->id))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->modalWidth('5xl')
                    ->modalContent(fn (Compra $record): View => view(
                        'filament.resources.compras.detalle-compra',
                        ['compra' => $record->load(['proveedor', 'detalles', 'pagos'])],
                    )),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('Compra')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('fecha')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('fecha_vencimiento')
                    ->label('Vence')
                    ->date('d/m/Y')
                    ->placeholder('-')
                    ->sortable(),

                Tables\Columns\TextColumn::make('proveedor.nombre')
                    ->label('Proveedor')
                    ->searchable()
                    ->limit(32)
                    ->placeholder('Sin proveedor'),

                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->formatStateUsing($money)
                    ->sortable()
                    ->alignRight(),

                Tables\Columns\TextColumn::make('pagado')
                    ->label('Pagado')
                    ->getStateUsing(fn (Compra $record): float => (float) $record->pagos->sum('monto'))
                    ->formatStateUsing($money)
                    ->alignRight(),

                Tables\Columns\TextColumn::make('saldo')
                    ->label('Saldo')
                    ->formatStateUsing($money)
                    ->sortable()
                    ->alignRight(),

                Tables\Columns\TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'confirmada' => 'warning',
                        'anulada' => 'danger',
                        default => 'gray',
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCuentasPorPagar::route('/'),
        ];
    }
}
