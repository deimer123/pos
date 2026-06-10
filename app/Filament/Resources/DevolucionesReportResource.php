<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DevolucionesReportResource\Pages;
use App\Models\Devolucion;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;

class DevolucionesReportResource extends Resource
{
    protected static ?string $model = Devolucion::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-uturn-left';

    protected static ?string $navigationLabel = 'Devoluciones';

    protected static ?string $modelLabel = 'Devolucion';

    protected static ?string $pluralModelLabel = 'Devoluciones';

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

        return $table
            ->query(
                Devolucion::query()
                    ->with(['factura.cliente', 'cliente', 'user', 'detalles'])
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

                SelectFilter::make('user_id')
                    ->label('Usuario')
                    ->options(fn () => User::query()
                        ->where(fn ($query) => $query
                            ->where('id', $empresaId)
                            ->orWhere('empresa_id', $empresaId))
                        ->role(['admin_empresa', 'cajero', 'vendedor'])
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->toArray())
                    ->placeholder('Todos'),
            ])
            ->actions([
                Action::make('detalle')
                    ->label('Detalle')
                    ->hiddenLabel()
                    ->icon('heroicon-o-eye')
                    ->iconButton()
                    ->tooltip('Ver detalle')
                    ->modalHeading(fn (Devolucion $record): string => $record->numero_visual)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->modalWidth('5xl')
                    ->modalContent(fn (Devolucion $record): View => view(
                        'filament.resources.devoluciones-report.detalle-devolucion',
                        [
                            'devolucion' => $record->load(['factura.cliente', 'cliente', 'user', 'detalles']),
                        ],
                    )),

                Action::make('imprimir')
                    ->label('Imprimir')
                    ->hiddenLabel()
                    ->icon('heroicon-o-printer')
                    ->iconButton()
                    ->tooltip('Imprimir devolucion')
                    ->modalHeading(fn (Devolucion $record): string => 'Imprimir ' . $record->numero_visual)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->modalWidth('6xl')
                    ->modalContent(fn (Devolucion $record): View => view(
                        'filament.resources.devoluciones-report.imprimir-devolucion',
                        ['devolucion' => $record],
                    )),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('Devolucion')
                    ->formatStateUsing(fn (Devolucion $record): string => $record->numero_visual)
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('fecha')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('factura.id')
                    ->label('Factura')
                    ->formatStateUsing(fn (Devolucion $record): string => $record->factura?->numero_visual ?? '-')
                    ->sortable(),

                Tables\Columns\TextColumn::make('cliente.nombre')
                    ->label('Cliente')
                    ->placeholder('Consumidor final')
                    ->searchable()
                    ->limit(32),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Usuario')
                    ->placeholder('-')
                    ->searchable()
                    ->limit(24),

                Tables\Columns\TextColumn::make('detalles_count')
                    ->label('Items')
                    ->counts('detalles')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('total')
                    ->label('Total devuelto')
                    ->formatStateUsing($money)
                    ->sortable()
                    ->alignRight(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDevolucionesReports::route('/'),
        ];
    }
}
