<?php

namespace App\Filament\Resources;

use App\Exports\Contabilidad\CarteraContableExport;
use App\Filament\Clusters\Contabilidad;
use App\Filament\Resources\CarteraContableResource\Pages;
use App\Models\Factura;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Maatwebsite\Excel\Facades\Excel;

class CarteraContableResource extends Resource
{
    protected static ?string $model = Factura::class;

    protected static ?string $cluster = Contabilidad::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationLabel = 'Cartera contable';

    protected static ?string $modelLabel = 'Cartera contable';

    protected static ?string $pluralModelLabel = 'Cartera contable';

    protected static ?int $navigationSort = 5;

    public static function canViewAny(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin_empresa');
    }

    public static function table(Table $table): Table
    {
        $empresaId = auth()->user()->getEmpresaActualId();
        $money = fn ($state): string => '$ ' . number_format((float) $state, 0, ',', '.');

        return $table
            ->query(Factura::query()->with(['cliente', 'pagos'])->where('empresa_id', $empresaId)->where('tipo_pago', 'credito'))
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
                SelectFilter::make('estado_pago')
                    ->label('Estado')
                    ->options([
                        'pendiente' => 'Pendiente',
                        'parcial' => 'Parcial',
                        'vencida' => 'Vencida',
                        'pagada' => 'Pagada',
                    ])
                    ->placeholder('Todos'),
            ])
            ->headerActions([
                Action::make('excel')
                    ->label('Descargar Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->action(fn () => Excel::download(
                        new CarteraContableExport($empresaId),
                        'cartera-contable-' . now()->format('Y-m-d') . '.xlsx'
                    )),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('Factura')
                    ->formatStateUsing(fn (Factura $record): string => $record->numero_visual)
                    ->sortable(),
                Tables\Columns\TextColumn::make('fecha')->label('Fecha')->dateTime('d/m/Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('cliente.nombre')->label('Cliente')->placeholder('Consumidor final')->searchable()->limit(28),
                Tables\Columns\TextColumn::make('fecha_vencimiento')->label('Vence')->date('d/m/Y')->placeholder('-'),
                Tables\Columns\TextColumn::make('total')->label('Total')->formatStateUsing($money)->alignRight(),
                Tables\Columns\TextColumn::make('pagado')
                    ->label('Pagado')
                    ->getStateUsing(fn (Factura $record): float => (float) $record->pagos->sum('monto'))
                    ->formatStateUsing($money)
                    ->alignRight(),
                Tables\Columns\TextColumn::make('saldo')->label('Saldo')->formatStateUsing($money)->alignRight(),
                Tables\Columns\TextColumn::make('estado_pago')->label('Estado')->badge(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCarteraContables::route('/'),
        ];
    }
}
