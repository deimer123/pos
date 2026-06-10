<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VentasPorVendedorReportResource\Pages;
use App\Models\Factura;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class VentasPorVendedorReportResource extends Resource
{
    protected static ?string $model = Factura::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Ventas por vendedor';

    protected static ?string $modelLabel = 'Ventas por Vendedor';

    protected static ?string $pluralModelLabel = 'Ventas por Vendedor';

    protected static ?string $navigationGroup = 'Reportes';

    protected static ?int $navigationSort = 3;

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
        $responsableExpression = 'COALESCE(facturas.vendedor_id, facturas.user_id)';

        return $table
            ->query(
                Factura::query()
                    ->join('users', function ($join) use ($responsableExpression) {
                        $join->on('users.id', '=', DB::raw($responsableExpression));
                    })
                    ->select([
                        DB::raw('MIN(facturas.id) as id'),
                        DB::raw($responsableExpression . ' as vendedor_id'),
                        'users.name as vendedor_nombre',
                        DB::raw('COUNT(facturas.id) as total_facturas'),
                        DB::raw('SUM(facturas.total) as total_ventas'),
                        DB::raw('SUM(facturas.saldo) as total_saldo'),
                    ])
                    ->where('facturas.empresa_id', $empresaId)
                    ->whereNotNull(DB::raw($responsableExpression))
                    ->groupBy(DB::raw($responsableExpression), 'users.name')
                    ->orderByDesc('total_ventas')
            )
            ->defaultSort('total_ventas', 'desc')
            ->paginated(false)
            ->filtersLayout(FiltersLayout::AboveContent)
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
                        ->when($data['desde'] ?? null, fn ($q, $date) => $q->whereDate('facturas.fecha', '>=', $date))
                        ->when($data['hasta'] ?? null, fn ($q, $date) => $q->whereDate('facturas.fecha', '<=', $date))),

                SelectFilter::make('responsable_id')
                    ->label('Vendedor')
                    ->options(fn () =>
                        User::query()
                            ->where(fn ($query) =>
                                $query->where('id', $empresaId)
                                    ->orWhere('empresa_id', $empresaId)
                            )
                            ->role(['vendedor', 'cajero', 'admin_empresa'])
                            ->orderBy('name')
                            ->pluck('name', 'id')
                    )
                    ->query(fn ($query, array $data) =>
                        filled($data['value'] ?? null)
                            ? $query->whereRaw($responsableExpression . ' = ?', [$data['value']])
                            : $query
                    )
                    ->searchable()
                    ->preload()
                    ->placeholder('Todos'),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('vendedor_nombre')
                    ->label('Vendedor')
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('total_facturas')
                    ->label('Facturas')
                    ->badge()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('total_ventas')
                    ->label('Total ventas')
                    ->formatStateUsing($money)
                    ->alignRight()
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_saldo')
                    ->label('Saldo')
                    ->formatStateUsing($money)
                    ->alignRight()
                    ->sortable(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVentasPorVendedorReports::route('/'),
        ];
    }
}
