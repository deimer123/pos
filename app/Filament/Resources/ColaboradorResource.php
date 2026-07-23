<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ColaboradorResource\Pages;
use App\Models\ConfiguracionEmpresa;
use App\Models\Mecanico;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Reusa el modelo/tabla de "Mecanico" (y todo su motor de liquidacion,
 * ver App\Livewire\TallerPanel) para Vendedores (tienda/ropa/carniceria)
 * y Meseros (bar y restaurante) -- ver Mecanico::ROL_* y
 * ConfiguracionEmpresa::rolColaboradorParaTipo(). A diferencia del
 * mecanico de taller (registro puramente administrativo, sin login), un
 * vendedor/mesero SI se vincula a un usuario real ya existente (creado
 * en el menu Empleados con su rol), para poder resolver automaticamente
 * quien esta facturando al calcular la comision en
 * FacturarVentaService::datosServicioFactura().
 */
class ColaboradorResource extends Resource
{
    protected static ?string $model = Mecanico::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Comisiones';

    protected static ?int $navigationSort = 10;

    protected static function empresaId(): ?int
    {
        return auth()->user()?->getEmpresaActualId();
    }

    protected static function rolActual(): ?string
    {
        $empresaId = static::empresaId();
        if (! $empresaId) {
            return null;
        }

        $tipo = (string) (ConfiguracionEmpresa::where('empresa_id', $empresaId)->value('tipo_negocio') ?? '');
        $rol = ConfiguracionEmpresa::rolColaboradorParaTipo($tipo);

        // El taller ya tiene su propio recurso "Mecánicos" (MecanicoResource).
        return $rol === Mecanico::ROL_MECANICO ? null : $rol;
    }

    public static function getNavigationLabel(): string
    {
        return static::etiqueta(true);
    }

    public static function getModelLabel(): string
    {
        return static::etiqueta(false);
    }

    public static function getPluralModelLabel(): string
    {
        return static::etiqueta(true);
    }

    protected static function etiqueta(bool $plural): string
    {
        $empresaId = static::empresaId();
        $tipo = (string) (ConfiguracionEmpresa::where('empresa_id', $empresaId)->value('tipo_negocio') ?? '');

        return ConfiguracionEmpresa::etiquetaColaboradorParaTipo($tipo, $plural);
    }

    public static function canViewAny(): bool
    {
        if (! auth()->check() || ! auth()->user()->hasRole('admin_empresa')) {
            return false;
        }

        return static::rolActual() !== null;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('empresa_id', static::empresaId())
            ->where('rol', static::rolActual());
    }

    public static function form(Form $form): Form
    {
        $rol = static::rolActual();
        $empresaId = static::empresaId();

        return $form->schema([
            Forms\Components\Hidden::make('empresa_id')->default($empresaId)->dehydrated(),
            Forms\Components\Hidden::make('rol')->default($rol)->dehydrated(),
            Forms\Components\Hidden::make('nombre')->dehydrated(),

            Forms\Components\Section::make('Datos')
                ->extraAttributes(['class' => 'combo-franja-azul'])
                ->schema([
                    Forms\Components\Grid::make(2)
                        ->extraAttributes(['class' => 'producto-linea-1'])
                        ->schema([
                            Forms\Components\Select::make('user_id')
                                ->label('Usuario')
                                ->options(fn () => User::where('empresa_id', $empresaId)
                                    ->where('tipo_usuario', 'empleado')
                                    ->role($rol)
                                    ->pluck('name', 'id'))
                                ->searchable()
                                ->required()
                                ->live()
                                ->afterStateUpdated(function ($state, Forms\Set $set) {
                                    $set('nombre', $state ? User::find($state)?->name : null);
                                })
                                ->helperText('Solo aparecen usuarios ya creados con este rol (menú Empleados).'),

                            Forms\Components\TextInput::make('porcentaje_comision')
                                ->label('% de comisión')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(100)
                                ->suffix('%')
                                ->required()
                                ->helperText('Se calcula sobre el subtotal de cada venta que registre.'),
                        ]),

                    Forms\Components\Grid::make(1)
                        ->extraAttributes(['class' => 'producto-linea-2'])
                        ->schema([
                            Forms\Components\Toggle::make('activo')
                                ->label('Activo')
                                ->default(true),
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nombre')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('porcentaje_comision')
                    ->label('% comisión')
                    ->formatStateUsing(fn ($state) => $state === null ? '-' : number_format((float) $state, 2) . '%')
                    ->alignCenter(),

                Tables\Columns\IconColumn::make('activo')
                    ->label('Activo')
                    ->boolean(),

                Tables\Columns\TextColumn::make('pendiente')
                    ->label('Pendiente por liquidar')
                    ->getStateUsing(fn (Mecanico $record) => static::pendiente($record)['monto'])
                    ->formatStateUsing(fn ($state) => '$ ' . number_format((float) $state, 0, ',', '.'))
                    ->badge()
                    ->color(fn ($state) => (float) $state > 0 ? 'warning' : 'gray'),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('activo')->label('Estado'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),

                Tables\Actions\Action::make('liquidar')
                    ->label('Liquidar')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->form([
                        Forms\Components\DatePicker::make('fecha_desde')->label('Desde')->required(),
                        Forms\Components\DatePicker::make('fecha_hasta')->label('Hasta')->required()->default(now()),
                        Forms\Components\Select::make('medio_pago')
                            ->label('Medio de pago')
                            ->options(['Efectivo' => 'Efectivo', 'Transferencia' => 'Transferencia'])
                            ->default('Efectivo')
                            ->required(),
                    ])
                    ->action(function (Mecanico $record, array $data) {
                        static::liquidar($record, $data['fecha_desde'], $data['fecha_hasta'], $data['medio_pago']);
                    })
                    ->modalDescription(fn (Mecanico $record) => 'Pendiente actual: $' . number_format(static::pendiente($record)['monto'], 0, ',', '.'))
                    ->requiresConfirmation(),
            ]);
    }

    // Mismo criterio que TallerPanel: pendiente = factura_detalles propios
    // de este colaborador que aun no tienen un LiquidacionMecanicoDetalle.
    protected static function pendiente(Mecanico $record): array
    {
        $rows = \Illuminate\Support\Facades\DB::table('factura_detalles as fd')
            ->join('facturas as f', 'f.id', '=', 'fd.factura_id')
            ->leftJoin('liquidacion_mecanico_detalles as lmd', 'lmd.factura_detalle_id', '=', 'fd.id')
            ->where('f.empresa_id', $record->empresa_id)
            ->where('fd.mecanico_id', $record->id)
            ->where('fd.tipo_servicio', 'propio')
            ->whereNull('lmd.id')
            ->select(\Illuminate\Support\Facades\DB::raw('COALESCE(SUM(fd.subtotal), 0) as total, COALESCE(SUM(fd.subtotal * (100 - COALESCE(fd.porcentaje_empresa, 0)) / 100), 0) as monto'))
            ->first();

        return ['total' => (float) $rows->total, 'monto' => (float) $rows->monto];
    }

    protected static function liquidar(Mecanico $record, string $desde, string $hasta, string $medioPago = 'Efectivo'): void
    {
        \Illuminate\Support\Facades\DB::transaction(function () use ($record, $desde, $hasta, $medioPago) {
            $detalles = \Illuminate\Support\Facades\DB::table('factura_detalles as fd')
                ->join('facturas as f', 'f.id', '=', 'fd.factura_id')
                ->leftJoin('liquidacion_mecanico_detalles as lmd', 'lmd.factura_detalle_id', '=', 'fd.id')
                ->where('f.empresa_id', $record->empresa_id)
                ->where('fd.mecanico_id', $record->id)
                ->where('fd.tipo_servicio', 'propio')
                ->whereNull('lmd.id')
                ->whereDate('f.fecha', '>=', $desde)
                ->whereDate('f.fecha', '<=', $hasta)
                ->select('fd.id', 'fd.subtotal', 'fd.porcentaje_empresa')
                ->get();

            if ($detalles->isEmpty()) {
                \Filament\Notifications\Notification::make()->title('No hay nada pendiente en ese rango')->warning()->send();
                return;
            }

            $totalServicios = (float) $detalles->sum('subtotal');
            $montoColaborador = round($detalles->sum(fn ($d) => (float) $d->subtotal * (100 - (float) ($d->porcentaje_empresa ?? 0)) / 100), 2);

            $prestamosPendientes = \App\Models\MecanicoPrestamo::where('mecanico_id', $record->id)
                ->where('estado', 'pendiente')
                ->get();
            $prestamosMonto = (float) $prestamosPendientes->sum('monto');
            $montoNeto = max(0, $montoColaborador - $prestamosMonto);

            $liquidacion = \App\Models\LiquidacionMecanico::create([
                'empresa_id' => $record->empresa_id,
                'mecanico_id' => $record->id,
                'fecha_desde' => $desde,
                'fecha_hasta' => $hasta,
                'total_servicios' => $totalServicios,
                'porcentaje_mecanico' => $totalServicios > 0 ? round($montoColaborador / $totalServicios * 100, 2) : 0,
                'monto_mecanico' => $montoColaborador,
                'prestamos_descontados' => $prestamosMonto,
                'monto_neto' => $montoNeto,
                'estado' => 'pagado',
                'fecha_pago' => now()->toDateString(),
                'user_id' => auth()->id(),
            ]);

            foreach ($detalles as $d) {
                \App\Models\LiquidacionMecanicoDetalle::create([
                    'liquidacion_id' => $liquidacion->id,
                    'factura_detalle_id' => $d->id,
                    'subtotal_servicio' => $d->subtotal,
                    'monto_mecanico' => round((float) $d->subtotal * (100 - (float) ($d->porcentaje_empresa ?? 0)) / 100, 2),
                ]);
            }

            foreach ($prestamosPendientes as $p) {
                $p->update(['estado' => 'descontado', 'liquidacion_id' => $liquidacion->id]);
            }

            // Registro contable: mismo criterio que TallerPanel al liquidar
            // un mecanico -- queda como gasto de salida, ligado a la caja
            // abierta si hay una (no es obligatorio tenerla abierta aqui,
            // porque esta liquidacion se hace desde el panel de admin).
            if ($montoNeto > 0) {
                $cajaActiva = \App\Models\Caja::where('empresa_id', $record->empresa_id)
                    ->where('estado', 'abierta')
                    ->latest('opened_at')
                    ->first();

                \App\Models\Gasto::create([
                    'id_gasto' => \App\Models\Gasto::where('empresa_id', $record->empresa_id)->max('id_gasto') + 1,
                    'empresa_id' => $record->empresa_id,
                    'tipo' => 'salida',
                    'categoria' => 'liquidacion_' . $record->rol,
                    'descripcion' => 'Liquidación ' . ($record->rol === 'mesero' ? 'mesero' : 'vendedor') . ': ' . $record->nombre
                        . ($prestamosMonto > 0 ? ' (descontado préstamo de $' . number_format($prestamosMonto, 0, ',', '.') . ')' : ''),
                    'monto' => $montoNeto,
                    'fecha' => today()->toDateString(),
                    'metodo_pago' => $medioPago,
                    'created_by' => auth()->id(),
                    'caja_id' => $cajaActiva?->id,
                ]);
            }

            \Filament\Notifications\Notification::make()
                ->title('Liquidación registrada: $' . number_format($montoNeto, 0, ',', '.'))
                ->success()
                ->send();
        });
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListColaboradores::route('/'),
            'create' => Pages\CreateColaborador::route('/create'),
            'edit'   => Pages\EditColaborador::route('/{record}/edit'),
        ];
    }
}
