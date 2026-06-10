<?php

namespace App\Filament\Resources;

use App\Filament\Clusters\Contabilidad;
use App\Filament\Resources\SalidaMercanciaReportResource\Pages;
use App\Models\StockMovimiento;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SalidaMercanciaReportResource extends Resource
{
    protected static ?string $model = StockMovimiento::class;

    protected static ?string $cluster = Contabilidad::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-right-on-rectangle';

    protected static ?string $navigationLabel = 'Salida de mercancía';

    protected static ?string $modelLabel = 'Salida de mercancía';

    protected static ?string $pluralModelLabel = 'Salidas de mercancía';

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
                StockMovimiento::query()
                    ->with(['producto.cuentaContable'])
                    ->where('empresa_id', $empresaId)
                    ->where('tipo', 'salida')
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
            ])
            ->columns([
                Tables\Columns\TextColumn::make('fecha')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('producto.id_producto')
                    ->label('Código')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('producto.descripcion_larga')
                    ->label('Producto')
                    ->searchable()
                    ->limit(36),

                Tables\Columns\TextColumn::make('producto.cuentaContable.codigo')
                    ->label('Cuenta contable')
                    ->placeholder('-')
                    ->searchable(),

                Tables\Columns\TextColumn::make('cantidad')
                    ->label('Cantidad')
                    ->alignRight(),

                Tables\Columns\TextColumn::make('costo_unitario')
                    ->label('Costo unitario')
                    ->formatStateUsing($money)
                    ->alignRight(),

                Tables\Columns\TextColumn::make('costo_total')
                    ->label('Costo total')
                    ->formatStateUsing($money)
                    ->alignRight(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSalidaMercanciaReports::route('/'),
        ];
    }
}
