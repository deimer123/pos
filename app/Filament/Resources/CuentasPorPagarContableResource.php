<?php

namespace App\Filament\Resources;

use App\Exports\Contabilidad\CuentasPorPagarContableExport;
use App\Filament\Clusters\Contabilidad;
use App\Filament\Resources\CuentasPorPagarContableResource\Pages;
use App\Models\Compra;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Maatwebsite\Excel\Facades\Excel;

class CuentasPorPagarContableResource extends Resource
{
    protected static ?string $model = Compra::class;

    protected static ?string $cluster = Contabilidad::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Cuentas por pagar';

    protected static ?string $modelLabel = 'Cuenta por pagar';

    protected static ?string $pluralModelLabel = 'Cuentas por pagar';

    protected static ?int $navigationSort = 6;

    public static function canViewAny(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin_empresa');
    }

    public static function table(Table $table): Table
    {
        $empresaId = auth()->user()->getEmpresaActualId();
        $money = fn ($state): string => '$ ' . number_format((float) $state, 0, ',', '.');

        return $table
            ->query(Compra::query()->with(['proveedor', 'pagos'])->where('empresa_id', $empresaId)->where('tipo_pago', 'credito'))
            ->defaultSort('fecha_vencimiento', 'asc')
            ->filters([
                Filter::make('fecha')
                    ->form([
                        DatePicker::make('desde')->label('Desde')->default(now()->startOfMonth()),
                        DatePicker::make('hasta')->label('Hasta')->default(now()),
                    ])
                    ->query(fn ($query, array $data) => $query
                        ->when($data['desde'] ?? null, fn ($q, $date) => $q->whereDate('fecha', '>=', $date))
                        ->when($data['hasta'] ?? null, fn ($q, $date) => $q->whereDate('fecha', '<=', $date))),
                SelectFilter::make('estado')
                    ->label('Estado')
                    ->options([
                        'confirmada' => 'Confirmada',
                        'anulada' => 'Anulada',
                    ])
                    ->placeholder('Todos'),
            ])
            ->headerActions([
                Action::make('excel')
                    ->label('Descargar Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->action(fn () => Excel::download(
                        new CuentasPorPagarContableExport($empresaId),
                        'cuentas-por-pagar-' . now()->format('Y-m-d') . '.xlsx'
                    )),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('numero_factura')->label('Compra')->sortable()->searchable()->placeholder('-'),
                Tables\Columns\TextColumn::make('fecha')->label('Fecha')->date('d/m/Y')->sortable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('proveedor.nombre')->label('Proveedor')->searchable()->limit(30),
                Tables\Columns\TextColumn::make('fecha_vencimiento')->label('Vence')->date('d/m/Y')->placeholder('-'),
                Tables\Columns\TextColumn::make('total')->label('Total')->formatStateUsing($money)->alignRight()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('pagado')
                    ->label('Pagado')
                    ->getStateUsing(fn (Compra $record): float => (float) $record->pagos->sum('monto'))
                    ->formatStateUsing($money)
                    ->alignRight()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('saldo')->label('Saldo')->formatStateUsing($money)->alignRight(),
                Tables\Columns\TextColumn::make('estado')->label('Estado')->badge(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCuentasPorPagarContables::route('/'),
        ];
    }
}
