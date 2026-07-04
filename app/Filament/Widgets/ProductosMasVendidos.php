<?php

namespace App\Filament\Widgets;

use App\Models\FacturaDetalle;
use Filament\Tables;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ProductosMasVendidos extends BaseWidget
{
    public static function canView(): bool
    {
        return auth()->user()?->hasRole('super_admin') !== true;
    }


    public function getTableRecordKey($record): string
    {
    return (string) $record->producto_id;
    }

    protected static ?string $heading = '🏆 Productos Más Vendidos';

    protected static ?string $maxHeight = '320px';

    protected int|string|array $columnSpan = 1;

    public function table(Tables\Table $table): Tables\Table
    {
        $empresaId = auth()->user()->getEmpresaActualId();

        return $table
            ->query(

                FacturaDetalle::query()

                    ->select([
                        'producto_id',
                        'descripcion_larga',

                        DB::raw('SUM(cantidad) as total_vendidos'),
                    ])

                    ->whereHas('factura', function ($q) use ($empresaId) {

                        $q->where('empresa_id', $empresaId);
                    })

                    ->where('producto_id', '!=', '10001')

                    ->groupBy(
                        'producto_id',
                        'descripcion_larga'
                    )

                    ->orderByDesc('total_vendidos')

            )

            ->columns([

    Tables\Columns\TextColumn::make('producto_id')
        ->label('Código')
        ->size('sm'),
       

    Tables\Columns\TextColumn::make('descripcion_larga')
        ->label('Producto')
        ->size('sm')
        ->limit(30),

    Tables\Columns\TextColumn::make('total_vendidos')
        ->label('Vendidos')
        ->badge()
        ->size('sm')
        ->color('success'),

])
            ->paginated(false);

    }
}
