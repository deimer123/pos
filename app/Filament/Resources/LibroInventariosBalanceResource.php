<?php

namespace App\Filament\Resources;

use App\Exports\Contabilidad\LibroInventariosBalanceExport;
use App\Filament\Clusters\Contabilidad;
use App\Filament\Resources\LibroInventariosBalanceResource\Pages;
use App\Models\Compra;
use App\Models\CuentaContable;
use App\Models\Factura;
use App\Models\Product;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;
use Maatwebsite\Excel\Facades\Excel;

class LibroInventariosBalanceResource extends Resource
{
    protected static ?string $model = CuentaContable::class;

    protected static ?string $cluster = Contabilidad::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static ?string $navigationLabel = 'Libro inventarios y balance';

    protected static ?string $modelLabel = 'Libro inventarios y balance';

    protected static ?string $pluralModelLabel = 'Libro inventarios y balance';

    protected static ?int $navigationSort = 9;

    public static function canViewAny(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin_empresa');
    }

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

    public static function table(Table $table): Table
    {
        $empresaId = auth()->user()->getEmpresaActualId();
        $money = fn ($state): string => '$ ' . number_format((float) $state, 2, ',', '.');

        $inventario = (float) Product::query()
            ->where('empresa_id', $empresaId)
            ->selectRaw('COALESCE(SUM(COALESCE(existencias, 0) * COALESCE(precio_costo, 0)), 0) as total')
            ->value('total');

        $cartera = (float) Factura::query()
            ->where('empresa_id', $empresaId)
            ->creditoPendiente()
            ->sum('saldo');

        $proveedores = (float) Compra::query()
            ->where('empresa_id', $empresaId)
            ->where('tipo_pago', 'credito')
            ->sum('saldo');

        $ventasContado = (float) Factura::query()
            ->where('empresa_id', $empresaId)
            ->where('tipo_pago', 'contado')
            ->sum('total');

        $ivaVentas = (float) Factura::query()
            ->where('empresa_id', $empresaId)
            ->with('detalles.producto')
            ->get()
            ->sum(function (Factura $factura): float {
                return (float) $factura->detalles->sum(function ($detalle): float {
                    $ivaPct = (float) ($detalle->producto?->iva_venta ?? 0);

                    return round(((float) $detalle->subtotal) * ($ivaPct / 100), 2);
                });
            });

        $balanceParaCuenta = function (CuentaContable $cuenta) use (
            $inventario,
            $cartera,
            $proveedores,
            $ventasContado,
            $ivaVentas
        ): float {
            $codigo = (string) $cuenta->codigo;
            $nombre = mb_strtolower((string) $cuenta->nombre);

            return match (true) {
                str_starts_with($codigo, '14') || str_contains($nombre, 'inventario') => $inventario,
                str_starts_with($codigo, '13') || str_contains($nombre, 'cliente') || str_contains($nombre, 'deudor') => $cartera,
                str_starts_with($codigo, '22') || str_starts_with($codigo, '23') || str_contains($nombre, 'proveedor') || str_contains($nombre, 'acreed') => -$proveedores,
                str_starts_with($codigo, '11') || str_contains($nombre, 'caja') || str_contains($nombre, 'banco') => $ventasContado,
                str_contains($nombre, 'iva') => $ivaVentas,
                str_contains($nombre, 'venta') || str_contains($nombre, 'ingreso') => $ventasContado,
                default => 0.0,
            };
        };

        return $table
            ->query(
                CuentaContable::query()
                    ->where('empresa_id', $empresaId)
                    ->orderBy('codigo')
            )
            ->emptyStateHeading('No hay cuentas contables para generar el balance')
            ->emptyStateDescription('Primero crea el plan contable de la empresa y luego descarga el libro en Excel.')
            ->headerActions([
                Action::make('excel')
                    ->label('Descargar Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->form([
                        DatePicker::make('desde')
                            ->label('Desde')
                            ->default(now()->startOfMonth()),
                        DatePicker::make('hasta')
                            ->label('Hasta')
                            ->default(now()),
                    ])
                    ->action(function (array $data) use ($empresaId) {
                        $desde = $data['desde'] ?? null;
                        $hasta = $data['hasta'] ?? null;

                        $archivo = 'libro-inventarios-balance';

                        if ($desde || $hasta) {
                            $archivo .= '-' . now()->format('Y-m-d');
                        }

                        return Excel::download(
                            new LibroInventariosBalanceExport($empresaId, $desde, $hasta),
                            $archivo . '.xlsx'
                        );
                    }),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('codigo')
                    ->label('Código cuenta contable')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('nombre')
                    ->label('Nombre')
                    ->searchable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('parcial')
                    ->label('Parcial')
                    ->getStateUsing(function (CuentaContable $record) use ($balanceParaCuenta): float {
                        $balance = $balanceParaCuenta($record);

                        return mb_strlen((string) $record->codigo) > 4 || str_ends_with((string) $record->codigo, '01')
                            ? $balance
                            : 0;
                    })
                    ->formatStateUsing($money)
                    ->alignRight(),
                Tables\Columns\TextColumn::make('saldo')
                    ->label('Saldo')
                    ->getStateUsing(function (CuentaContable $record) use ($balanceParaCuenta): float {
                        $balance = $balanceParaCuenta($record);

                        return mb_strlen((string) $record->codigo) > 4 || str_ends_with((string) $record->codigo, '01')
                            ? 0
                            : $balance;
                    })
                    ->formatStateUsing($money)
                    ->alignRight(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLibroInventariosBalances::route('/'),
        ];
    }
}
