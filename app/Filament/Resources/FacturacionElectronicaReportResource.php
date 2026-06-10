<?php

namespace App\Filament\Resources;

use App\Filament\Clusters\Contabilidad;
use App\Filament\Resources\FacturacionElectronicaReportResource\Pages;
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

class FacturacionElectronicaReportResource extends Resource
{
    protected static ?string $model = Factura::class;

    protected static ?string $cluster = Contabilidad::class;

    protected static ?string $navigationIcon = 'heroicon-o-receipt-refund';

    protected static ?string $navigationLabel = 'Facturación electrónica';

    protected static ?string $modelLabel = 'Factura electrónica';

    protected static ?string $pluralModelLabel = 'Facturación electrónica';

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
                Factura::query()
                    ->with(['cliente', 'vendedorAsignado', 'cajero'])
                    ->where('empresa_id', $empresaId)
                    ->where('tipo_factura', 'electronica')
            )
            ->defaultSort('fecha', 'desc')
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

                SelectFilter::make('factus_status')
                    ->label('Estado Factus')
                    ->options([
                        'pending' => 'Pendiente',
                        'validated' => 'Validada',
                        'rejected' => 'Rechazada',
                        'error' => 'Error',
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
                Tables\Columns\TextColumn::make('factus_number')
                    ->label('Número Factus')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('fecha')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('cliente.nombre')
                    ->label('Cliente')
                    ->placeholder('Consumidor final')
                    ->searchable()
                    ->limit(24),

                Tables\Columns\TextColumn::make('factus_cufe')
                    ->label('CUFE')
                    ->searchable()
                    ->limit(18),

                Tables\Columns\TextColumn::make('factus_status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'validated' => 'success',
                        'pending' => 'warning',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->formatStateUsing($money)
                    ->sortable()
                    ->alignRight(),

                Tables\Columns\TextColumn::make('saldo')
                    ->label('Saldo')
                    ->formatStateUsing($money)
                    ->sortable()
                    ->alignRight(),

                Tables\Columns\TextColumn::make('factus_validated_at')
                    ->label('Validada')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('-'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFacturacionElectronicaReports::route('/'),
        ];
    }
}
