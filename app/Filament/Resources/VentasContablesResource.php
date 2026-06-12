<?php

namespace App\Filament\Resources;

use App\Exports\Contabilidad\VentasContablesExport;
use App\Filament\Clusters\Contabilidad;
use App\Filament\Resources\VentasContablesResource\Pages;
use App\Models\Factura;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Maatwebsite\Excel\Facades\Excel;

class VentasContablesResource extends Resource
{
    protected static ?string $model = Factura::class;

    protected static ?string $cluster = Contabilidad::class;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Ventas contables';

    protected static ?string $modelLabel = 'Venta contable';

    protected static ?string $pluralModelLabel = 'Ventas contables';

    protected static ?int $navigationSort = 4;

    public static function canViewAny(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin_empresa');
    }

    public static function table(Table $table): Table
    {
        $empresaId = auth()->user()->getEmpresaActualId();
        $money = fn ($state): string => '$ ' . number_format((float) $state, 0, ',', '.');

        return $table
            ->query(Factura::query()->with(['cliente', 'detalles.producto'])->where('empresa_id', $empresaId))
            ->defaultSort('fecha', 'desc')
            ->filters([
                Filter::make('fecha')
                    ->form([
                        DatePicker::make('desde')->label('Desde')->default(now()->startOfMonth()),
                        DatePicker::make('hasta')->label('Hasta')->default(now()),
                    ])
                    ->query(fn ($query, array $data) => $query
                        ->when($data['desde'] ?? null, fn ($q, $date) => $q->whereDate('fecha', '>=', $date))
                        ->when($data['hasta'] ?? null, fn ($q, $date) => $q->whereDate('fecha', '<=', $date))),
                SelectFilter::make('tipo_factura')
                    ->label('Tipo')
                    ->options([
                        'salida' => 'Salida',
                        'electronica' => 'Electrónica',
                    ])
                    ->placeholder('Todos'),
            ])
            ->headerActions([
                Action::make('excel')
                    ->label('Descargar Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->action(fn () => Excel::download(
                        new VentasContablesExport($empresaId),
                        'ventas-contables-' . now()->format('Y-m-d') . '.xlsx'
                    )),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('Factura')
                    ->formatStateUsing(fn (Factura $record): string => $record->numero_visual)
                    ->sortable(),
                Tables\Columns\TextColumn::make('fecha')->label('Fecha')->dateTime('d/m/Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('cliente.nombre')->label('Cliente')->placeholder('Consumidor final')->searchable()->limit(28),
                Tables\Columns\TextColumn::make('tipo_factura')->label('Tipo')->badge(),
                Tables\Columns\TextColumn::make('ventas_con_iva')
                    ->label('Ventas con IVA')
                    ->getStateUsing(function (Factura $record): float {
                        return (float) $record->detalles
                            ->filter(fn ($detalle) => (float) ($detalle->producto?->iva_venta ?? 0) > 0)
                            ->sum('subtotal');
                    })
                    ->formatStateUsing($money)
                    ->alignRight(),
                Tables\Columns\TextColumn::make('ventas_sin_iva')
                    ->label('Ventas sin IVA')
                    ->getStateUsing(function (Factura $record): float {
                        return (float) $record->detalles
                            ->filter(fn ($detalle) => (float) ($detalle->producto?->iva_venta ?? 0) <= 0)
                            ->sum('subtotal');
                    })
                    ->formatStateUsing($money)
                    ->alignRight(),
                Tables\Columns\TextColumn::make('iva_total')
                    ->label('IVA')
                    ->getStateUsing(function (Factura $record): float {
                        return (float) $record->detalles->sum(function ($detalle) {
                            $ivaPct = (float) ($detalle->producto?->iva_venta ?? 0);
                            return round(((float) $detalle->subtotal) * ($ivaPct / 100), 2);
                        });
                    })
                    ->formatStateUsing($money)
                    ->alignRight(),
                Tables\Columns\TextColumn::make('total')->label('Total')->formatStateUsing($money)->alignRight(),
                Tables\Columns\TextColumn::make('saldo')->label('Saldo')->formatStateUsing($money)->alignRight(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVentasContables::route('/'),
        ];
    }
}
