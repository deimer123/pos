<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ConflictoOfflineResource\Pages;
use App\Models\ConflictoOffline;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Ventas/pedidos guardados offline que fallaron al sincronizar (ej. stock
 * insuficiente porque otra caja vendio lo mismo mientras ambas estaban
 * sin internet). El resto de la cola de ese dispositivo sigue
 * sincronizando normal; solo la operacion en conflicto queda pendiente
 * de revisar a mano aqui.
 */
class ConflictoOfflineResource extends Resource
{
    protected static ?string $model = ConflictoOffline::class;

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?string $navigationLabel = 'Cola Offline';

    protected static ?string $modelLabel = 'Conflicto offline';

    protected static ?string $pluralModelLabel = 'Conflictos offline';

    protected static ?string $navigationGroup = 'Reportes';

    protected static ?int $navigationSort = 7;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canViewAny(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin_empresa');
    }

    public static function getNavigationBadge(): ?string
    {
        $empresaId = auth()->user()?->getEmpresaActualId();
        if (! $empresaId) {
            return null;
        }

        $total = ConflictoOffline::where('empresa_id', $empresaId)->where('estado', 'pendiente')->count();

        return $total > 0 ? (string) $total : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function table(Table $table): Table
    {
        $empresaId = auth()->user()->getEmpresaActualId();

        return $table
            ->query(ConflictoOffline::query()->where('empresa_id', $empresaId))
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\BadgeColumn::make('estado')
                    ->label('Estado')
                    ->colors([
                        'danger' => 'pendiente',
                        'success' => 'resuelto',
                    ]),

                Tables\Columns\TextColumn::make('tipo')
                    ->label('Tipo')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'venta' => 'Venta',
                        'mesa_item' => 'Mesa: item',
                        'mesa_facturar' => 'Mesa: cerrar',
                        'taller_item' => 'Taller: repuesto',
                        'taller_facturar' => 'Taller: cerrar',
                        'hotel_item' => 'Hotel: consumo',
                        'hotel_facturar' => 'Hotel: check-out',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('error')
                    ->label('Motivo')
                    ->limit(80)
                    ->wrap(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('estado')
                    ->options(['pendiente' => 'Pendiente', 'resuelto' => 'Resuelto'])
                    ->default('pendiente'),
            ])
            ->actions([
                Tables\Actions\Action::make('ver_detalle')
                    ->label('Ver datos')
                    ->icon('heroicon-o-eye')
                    ->modalHeading('Datos de la operacion')
                    ->modalContent(fn (ConflictoOffline $record) => view('filament.conflicto-offline-detalle', ['record' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar'),

                Tables\Actions\Action::make('marcar_resuelto')
                    ->label('Marcar resuelto')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (ConflictoOffline $record) => $record->estado === 'pendiente')
                    ->requiresConfirmation()
                    ->action(function (ConflictoOffline $record) {
                        $record->update([
                            'estado' => 'resuelto',
                            'resuelto_por' => auth()->id(),
                            'resuelto_en' => now(),
                        ]);
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListConflictoOfflines::route('/'),
        ];
    }
}
