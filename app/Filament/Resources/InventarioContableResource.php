<?php

namespace App\Filament\Resources;

use App\Exports\Contabilidad\InventarioContableExport;
use App\Filament\Clusters\Contabilidad;
use App\Filament\Resources\InventarioContableResource\Pages;
use App\Models\Product;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Maatwebsite\Excel\Facades\Excel;

class InventarioContableResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $cluster = Contabilidad::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    protected static ?string $navigationLabel = 'Inventario contable';

    protected static ?string $modelLabel = 'Inventario contable';

    protected static ?string $pluralModelLabel = 'Inventario contable';

    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin_empresa');
    }

    public static function table(Table $table): Table
    {
        $empresaId = auth()->user()->getEmpresaActualId();
        $money = fn ($state): string => '$ ' . number_format((float) $state, 2, ',', '.');

        return $table
            ->query(Product::query()->with('cuentaContable')->where('empresa_id', $empresaId))
            ->defaultSort('existencias', 'asc')
            ->filters([
                SelectFilter::make('estado_stock')
                    ->label('Existencias')
                    ->multiple()
                    ->options([
                        'positivas' => 'Positivas',
                        'negativas' => 'Negativas',
                        'cero' => 'En cero',
                    ])
                    ->query(function ($query, array $data) {
                        $values = array_values(array_filter((array) ($data['values'] ?? $data['value'] ?? [])));

                        if (empty($values)) {
                            return $query;
                        }

                        return $query->where(function ($q) use ($values) {
                            if (in_array('positivas', $values, true)) {
                                $q->orWhere('existencias', '>', 0);
                            }

                            if (in_array('negativas', $values, true)) {
                                $q->orWhere('existencias', '<', 0);
                            }

                            if (in_array('cero', $values, true)) {
                                $q->orWhere('existencias', 0);
                            }
                        });
                    }),
            ])
            ->headerActions([
                Action::make('excel')
                    ->label('Descargar Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->action(fn () => Excel::download(
                        new InventarioContableExport($empresaId),
                        'inventario-contable-' . now()->format('Y-m-d') . '.xlsx'
                    )),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('id_producto')->label('Código')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('descripcion_larga')->label('Nombre')->searchable()->wrap(),
                Tables\Columns\TextColumn::make('cuentaContable.codigo')->label('Cuenta contable')->placeholder('-'),
                Tables\Columns\TextColumn::make('existencias')->label('Existencias')->sortable()->alignRight(),
                Tables\Columns\TextColumn::make('precio_costo')->label('Costo unitario')->formatStateUsing($money)->alignRight(),
                Tables\Columns\TextColumn::make('iva_compra')->label('IVA compra %')->alignRight(),
                Tables\Columns\TextColumn::make('costo_iva')
                    ->label('Costo + IVA')
                    ->getStateUsing(fn (Product $record) => round(((float) $record->precio_costo) * (1 + (((float) ($record->iva_compra ?? 0)) / 100)), 2))
                    ->formatStateUsing($money)
                    ->alignRight(),
                Tables\Columns\TextColumn::make('iva_venta')->label('IVA venta %')->alignRight(),
                Tables\Columns\TextColumn::make('precio_venta1')->label('Valor venta')->formatStateUsing($money)->alignRight(),
                Tables\Columns\TextColumn::make('total_venta')
                    ->label('Valor venta x existencias')
                    ->getStateUsing(fn (Product $record) => ((float) $record->precio_venta1) * ((float) $record->existencias))
                    ->formatStateUsing($money)
                    ->alignRight(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInventarioContables::route('/'),
        ];
    }
}
