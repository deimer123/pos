<?php

namespace App\Filament\Resources;

use App\Exports\Contabilidad\SalidaMercanciaContableExport;
use App\Filament\Clusters\Contabilidad;
use App\Filament\Resources\SalidaMercanciaContableResource\Pages;
use App\Models\FacturaDetalle;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Maatwebsite\Excel\Facades\Excel;

class SalidaMercanciaContableResource extends Resource
{
    protected static ?string $model = FacturaDetalle::class;

    protected static ?string $cluster = Contabilidad::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-right-on-rectangle';

    protected static ?string $navigationLabel = 'Salida de mercancía';

    protected static ?string $modelLabel = 'Salida de mercancía';

    protected static ?string $pluralModelLabel = 'Salida de mercancía';

    protected static ?int $navigationSort = 8;

    public static function canViewAny(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin_empresa');
    }

    public static function table(Table $table): Table
    {
        $empresaId = auth()->user()->getEmpresaActualId();
        $money = fn ($state): string => '$ ' . number_format((float) $state, 0, ',', '.');

        return $table
            ->query(FacturaDetalle::query()->with(['factura.cliente', 'producto.cuentaContable'])->whereHas('factura', fn ($q) => $q->where('empresa_id', $empresaId)->where('tipo_factura', 'salida')))
            ->defaultSort('id', 'desc')
            ->filters([
                Filter::make('fecha')
                    ->form([
                        DatePicker::make('desde')->label('Desde')->default(now()->startOfMonth()),
                        DatePicker::make('hasta')->label('Hasta')->default(now()),
                    ])
                    ->query(fn ($query, array $data) => $query
                        ->whereHas('factura', fn ($q) => $q
                            ->when($data['desde'] ?? null, fn ($qq, $date) => $qq->whereDate('fecha', '>=', $date))
                            ->when($data['hasta'] ?? null, fn ($qq, $date) => $qq->whereDate('fecha', '<=', $date)))),
            ])
            ->headerActions([
                Action::make('excel')
                    ->label('Descargar Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->action(fn () => Excel::download(
                        new SalidaMercanciaContableExport($empresaId),
                        'salida-mercancia-contable-' . now()->format('Y-m-d') . '.xlsx'
                    )),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('factura.id')->label('Factura')->formatStateUsing(fn (FacturaDetalle $record): string => $record->factura?->numero_visual ?? '-')->sortable(),
                Tables\Columns\TextColumn::make('factura.fecha')->label('Fecha')->dateTime('d/m/Y H:i')->sortable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('factura.cliente.nombre')->label('Cliente')->placeholder('Consumidor final')->limit(24),
                Tables\Columns\TextColumn::make('producto.id_producto')->label('Código')->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('descripcion_larga')->label('Producto')->formatStateUsing(fn (FacturaDetalle $record): string => $record->descripcion_larga ?? $record->producto?->descripcion_larga ?? '-')->wrap(),
                Tables\Columns\TextColumn::make('producto.cuentaContable.codigo')->label('Cuenta contable')->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('cantidad')->label('Cantidad')->alignRight(),
                Tables\Columns\TextColumn::make('precio')->label('Precio')->formatStateUsing($money)->alignRight(),
                Tables\Columns\TextColumn::make('subtotal')->label('Subtotal')->formatStateUsing($money)->alignRight(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSalidaMercanciaContables::route('/'),
        ];
    }
}
