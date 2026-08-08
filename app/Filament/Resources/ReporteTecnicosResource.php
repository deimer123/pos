<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReporteTecnicosResource\Pages;
use App\Models\FacturaDetalle;
use App\Models\Mecanico;
use App\Models\ServicioTecnicoOrden;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ReporteTecnicosResource extends Resource
{
    protected static ?string $model = FacturaDetalle::class;

    protected static ?string $navigationIcon = 'heroicon-o-identification';

    protected static ?string $navigationLabel = 'Reporte por Técnico';

    protected static ?string $modelLabel = 'Servicio de técnico';

    protected static ?string $pluralModelLabel = 'Servicios por técnico';

    protected static ?string $navigationGroup = 'Servicio Técnico';

    protected static ?int $navigationSort = 2;

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
        if (! auth()->check() || ! auth()->user()->hasRole('admin_empresa')) {
            return false;
        }

        $empresaId = auth()->user()->getEmpresaActualId();

        return (bool) \App\Models\ConfiguracionEmpresa::where('empresa_id', $empresaId)->value('usa_servicio_tecnico');
    }

    public static function table(Table $table): Table
    {
        $empresaId = auth()->user()->getEmpresaActualId();
        $money = fn ($state): string => '$ ' . number_format((float) $state, 0, ',', '.');
        $percent = fn ($state): string => $state === null ? '-' : number_format((float) $state, 2, ',', '.') . ' %';

        return $table
            ->query(
                FacturaDetalle::query()
                    ->whereNotNull('mecanico_id')
                    // Este reporte es exclusivo de Servicio Tecnico: si la
                    // empresa tambien activo "Comision a vendedores"
                    // (universal), no deben mezclarse aqui las lineas de
                    // venta de productos comisionadas a un vendedor.
                    ->whereHas('mecanico', fn ($m) => $m->where('rol', Mecanico::ROL_TECNICO))
                    ->whereHas('factura', fn ($q) => $q->where('empresa_id', $empresaId))
                    ->with(['factura', 'mecanico'])
            )
            ->defaultSort('id', 'desc')
            ->paginated([10, 25, 50, 100])
            ->filtersLayout(FiltersLayout::AboveContent)
            ->filters([
                Filter::make('fecha')
                    ->form([
                        DatePicker::make('desde')
                            ->label('Desde')
                            ->default(now()->startOfWeek()),

                        DatePicker::make('hasta')
                            ->label('Hasta')
                            ->default(now()->endOfWeek()),
                    ])
                    ->query(fn ($query, array $data) => $query
                        ->when($data['desde'] ?? null, fn ($q, $date) => $q->whereHas('factura', fn ($f) => $f->whereDate('fecha', '>=', $date)))
                        ->when($data['hasta'] ?? null, fn ($q, $date) => $q->whereHas('factura', fn ($f) => $f->whereDate('fecha', '<=', $date)))),

                SelectFilter::make('mecanico_id')
                    ->label('Técnico')
                    ->options(fn () => Mecanico::where('empresa_id', $empresaId)->where('rol', Mecanico::ROL_TECNICO)->pluck('nombre', 'id'))
                    ->placeholder('Todos'),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('factura.fecha')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->size('xs')
                    ->sortable(),

                Tables\Columns\TextColumn::make('mecanico.nombre')
                    ->label('Técnico')
                    ->searchable()
                    ->size('xs'),

                Tables\Columns\TextColumn::make('equipo')
                    ->label('Equipo')
                    ->getStateUsing(function (FacturaDetalle $record) {
                        $orden = ServicioTecnicoOrden::where('factura_id', $record->factura_id)->first();
                        if (! $orden) return '—';
                        return trim(($orden->marca ?? '') . ' ' . ($orden->modelo ?? '') . ' · ' . ($orden->imei_serial ?? ''));
                    })
                    ->size('xs'),

                Tables\Columns\TextColumn::make('descripcion_larga')
                    ->label('Servicio')
                    ->limit(28)
                    ->size('xs'),

                Tables\Columns\TextColumn::make('subtotal')
                    ->label('Valor del servicio')
                    ->formatStateUsing($money)
                    ->alignRight(),

                Tables\Columns\TextColumn::make('porcentaje_empresa')
                    ->label('% Empresa')
                    ->formatStateUsing($percent)
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('valor_tercero')
                    ->label('Le queda al técnico')
                    ->formatStateUsing($money)
                    ->color('success')
                    ->alignRight(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReporteTecnicos::route('/'),
        ];
    }
}
