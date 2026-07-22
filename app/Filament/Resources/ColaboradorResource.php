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
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('activo')->label('Estado'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
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
