<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReporteServiciosResource\Pages;
use App\Models\FacturaDetalle;
use App\Models\Mecanico;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ReporteServiciosResource extends Resource
{
    protected static ?string $model = FacturaDetalle::class;

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?string $navigationLabel = 'Servicios';

    protected static ?string $modelLabel = 'Servicio facturado';

    protected static ?string $pluralModelLabel = 'Servicios facturados';

    protected static ?string $navigationGroup = 'Reportes';

    protected static ?int $navigationSort = 5;

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
        $config = \App\Models\ConfiguracionEmpresa::where('empresa_id', $empresaId)->first();

        return (bool) ($config?->usa_taller || $config?->usa_servicio_tecnico);
    }

    // "Servicios" (Product tipo_producto=servicio) es generico entre
    // taller y servicio tecnico -- ambos reutilizan Mecanico/comisiones,
    // solo cambia que "rol" corresponde al tipo de negocio de la empresa
    // actual. Una empresa nunca tiene los dos flags a la vez.
    protected static function rolMecanicoActual(int $empresaId): string
    {
        $usaServicioTecnico = (bool) \App\Models\ConfiguracionEmpresa::where('empresa_id', $empresaId)->value('usa_servicio_tecnico');

        return $usaServicioTecnico ? Mecanico::ROL_TECNICO : Mecanico::ROL_MECANICO;
    }

    public static function table(Table $table): Table
    {
        $empresaId = auth()->user()->getEmpresaActualId();
        $rolActual = static::rolMecanicoActual($empresaId);
        $etiquetaColaborador = $rolActual === Mecanico::ROL_TECNICO ? 'Técnico' : 'Mecánico';
        $money = fn ($state): string => '$ ' . number_format((float) $state, 0, ',', '.');
        $percent = fn ($state): string => $state === null ? '-' : number_format((float) $state, 2, ',', '.') . ' %';

        return $table
            ->query(
                FacturaDetalle::query()
                    ->whereNotNull('tipo_servicio')
                    ->whereHas('factura', fn ($q) => $q->where('empresa_id', $empresaId))
                    ->with(['factura.cliente', 'mecanico'])
            )
            ->defaultSort('id', 'desc')
            ->paginated([10, 25, 50, 100])
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
                        ->when($data['desde'] ?? null, fn ($q, $date) => $q->whereHas('factura', fn ($f) => $f->whereDate('fecha', '>=', $date)))
                        ->when($data['hasta'] ?? null, fn ($q, $date) => $q->whereHas('factura', fn ($f) => $f->whereDate('fecha', '<=', $date)))),

                SelectFilter::make('tipo_servicio')
                    ->label('Tipo')
                    ->options([
                        'propio'  => 'Propio',
                        'tercero' => 'Tercero',
                    ])
                    ->placeholder('Todos'),
            ])
            ->actions([
                Tables\Actions\Action::make('asignar_mecanico')
                    ->label('Asignar ' . mb_strtolower($etiquetaColaborador))
                    ->icon('heroicon-o-wrench-screwdriver')
                    ->color('warning')
                    ->visible(fn (FacturaDetalle $record) => $record->tipo_servicio === 'propio' && $record->mecanico_id === null)
                    ->form([
                        Select::make('mecanico_id')
                            ->label($etiquetaColaborador)
                            ->options(fn () => Mecanico::where('empresa_id', $empresaId)
                                ->where('rol', $rolActual)
                                ->where('activo', true)
                                ->orderBy('nombre')
                                ->pluck('nombre', 'id'))
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (FacturaDetalle $record, array $data) {
                        $record->update(['mecanico_id' => $data['mecanico_id']]);
                    })
                    ->successNotification(
                        Notification::make()
                            ->title($etiquetaColaborador . ' asignado')
                            ->body($rolActual === Mecanico::ROL_TECNICO
                                ? 'Ya puedes liquidar este servicio desde Servicio Técnico → Técnicos.'
                                : 'Ya puedes liquidar este servicio desde Taller → Mecánicos.')
                            ->success()
                    ),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('factura.fecha')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->size('xs')
                    ->sortable(),

                Tables\Columns\TextColumn::make('descripcion_larga')
                    ->label('Servicio')
                    ->searchable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('factura.cliente.nombre')
                    ->label('Cliente')
                    ->placeholder('Consumidor final')
                    ->limit(24)
                    ->size('xs'),

                Tables\Columns\TextColumn::make('cantidad')
                    ->label('Cant.')
                    ->size('xs')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('subtotal')
                    ->label('Total cobrado')
                    ->formatStateUsing($money)
                    ->alignRight(),

                Tables\Columns\TextColumn::make('tipo_servicio')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state === 'propio' ? 'Propio' : 'Tercero')
                    ->color(fn ($state) => $state === 'propio' ? 'success' : 'warning'),

                Tables\Columns\TextColumn::make('mecanico.nombre')
                    ->label($etiquetaColaborador)
                    ->size('xs')
                    ->formatStateUsing(fn ($state, FacturaDetalle $record) => $record->tipo_servicio === 'propio' ? ($state ?? 'Sin asignar') : '-')
                    ->badge()
                    ->color(fn ($state, FacturaDetalle $record) => $record->tipo_servicio === 'propio' && ! $record->mecanico_id ? 'danger' : 'gray'),

                Tables\Columns\TextColumn::make('porcentaje_empresa')
                    ->label('% Empresa')
                    ->formatStateUsing($percent)
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('valor_empresa')
                    ->label('Para la empresa')
                    ->formatStateUsing($money)
                    ->color('success')
                    ->alignRight(),

                Tables\Columns\TextColumn::make('valor_tercero')
                    ->label('Para tercero/mecánico')
                    ->formatStateUsing($money)
                    ->color('warning')
                    ->alignRight(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReporteServicios::route('/'),
        ];
    }
}
