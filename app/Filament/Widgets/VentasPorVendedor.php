<?php

namespace App\Filament\Widgets;

use App\Models\Factura;
use Filament\Tables;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\DB;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\DatePicker;

class VentasPorVendedor extends BaseWidget
{
    public static function canView(): bool
    {
        return auth()->user()?->hasRole('super_admin') !== true;
    }

    protected static ?string $heading = '👨‍💼 Ventas por Vendedor';

    protected static ?string $maxHeight = '320px';

    protected int|string|array $columnSpan = 1;

    protected int|string|array $defaultPaginationPageOption = 'all';

    public function getTableRecordKey($record): string
    {
        return (string) $record->vendedor_id;
    }

    public function table(Tables\Table $table): Tables\Table
    {
        $empresaId = auth()->user()->getEmpresaActualId();

       return $table
    ->query(

        Factura::query()

            ->join('users', 'facturas.vendedor_id', '=', 'users.id')

            ->select([
                'facturas.vendedor_id',

                'users.name as vendedor_nombre',

                DB::raw('COUNT(facturas.id) as total_facturas'),

                DB::raw('SUM(facturas.total) as total_ventas'),
            ])

            ->where('facturas.empresa_id', $empresaId)

            ->whereNotNull('facturas.vendedor_id')

            ->groupBy(
                'facturas.vendedor_id',
                'users.name'
            )

            ->orderByDesc('total_ventas')

    )

            ->columns([

                Tables\Columns\TextColumn::make('vendedor_nombre')
        ->label('Vendedor')
        ->weight('bold')
        ->size('sm'),

    Tables\Columns\TextColumn::make('total_facturas')
        ->label('Facturas')
        ->badge()
        ->color('warning')
        ->size('sm'),

    Tables\Columns\TextColumn::make('total_ventas')
        ->label('Ventas')
        ->formatStateUsing(fn ($state) =>
    '$' . number_format($state, 0, ',', '.')
)
        ->color('success')
        ->weight('bold')
        ->size('sm'),
            ])
            ->paginated(false)

    ->filters([

    Filter::make('fecha')

        ->form([

            DatePicker::make('desde'),

            DatePicker::make('hasta'),

        ])

        ->query(function ($query, array $data) {

            return $query

                ->when(
                    $data['desde'],
                    fn ($q) =>
                        $q->whereDate('facturas.fecha', '>=', $data['desde'])
                )

                ->when(
                    $data['hasta'],
                    fn ($q) =>
                        $q->whereDate('facturas.fecha', '<=', $data['hasta'])
                );
        }),

]);
        
    }
}
