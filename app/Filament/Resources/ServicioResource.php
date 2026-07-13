<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ServicioResource\Pages;
use App\Models\Mecanico;
use App\Models\Product;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ServicioResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?string $navigationLabel = 'Servicios';

    protected static ?string $modelLabel = 'Servicio';

    protected static ?string $pluralModelLabel = 'Servicios';

    protected static ?string $navigationGroup = '🔧 Servicios';

    protected static ?int $navigationSort = 1;

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canAccess(): bool
    {
        $empresaId = auth()->user()?->getEmpresaActualId();

        return (bool) \App\Models\ConfiguracionEmpresa::where('empresa_id', $empresaId)->value('usa_servicios');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Hidden::make('empresa_id')
                ->default(fn () => auth()->user()->getEmpresaActualId())
                ->dehydrated(),

            Hidden::make('id_producto')
                ->default(fn () => (Product::where('empresa_id', auth()->user()->getEmpresaActualId())->max('id_producto') ?? 10001) + 1)
                ->dehydrated(),

            Hidden::make('tipo_producto')->default('servicio')->dehydrated(),
            Hidden::make('vende_por')->default('unidad')->dehydrated(),
            Hidden::make('maneja_inventario')->default(false)->dehydrated(),
            Hidden::make('permite_fraccion')->default(false)->dehydrated(),
            Hidden::make('requiere_cocina')->default(false)->dehydrated(),
            Hidden::make('id_familia1')->default(0)->dehydrated(),
            Hidden::make('id_familia2')->default(0)->dehydrated(),
            Hidden::make('id_unidad_de_medida')->default(0)->dehydrated(),
            Hidden::make('descuento_comercial')->default(0)->dehydrated(),
            Hidden::make('iva_compra')->default(0)->dehydrated(),
            Hidden::make('existencias')->default(0)->dehydrated(),

            Section::make('Datos del servicio')
                ->icon('heroicon-o-wrench-screwdriver')
                ->description('Define el servicio, quién lo ejecuta y cuánto cuesta.')
                ->extraAttributes(['class' => 'combo-franja-azul'])
                ->schema([
                    Grid::make(1)
                        ->extraAttributes(['class' => 'producto-linea-1'])
                        ->schema([
                            TextInput::make('descripcion_larga')
                                ->label('Nombre del servicio')
                                ->prefixIcon('heroicon-o-tag')
                                ->required()
                                ->maxLength(255)
                                ->lazy()
                                ->rule(function (callable $get) {
                                    return function ($attribute, $value, $fail) use ($get) {
                                        $idProducto = $get('id_producto');
                                        $empresaId = (int) auth()->user()->getEmpresaActualId();
                                        $query = Product::where('descripcion_larga', $value)
                                            ->where('empresa_id', $empresaId);
                                        if ($idProducto) {
                                            $query->where('id_producto', '<>', $idProducto);
                                        }
                                        if ($query->exists()) {
                                            $fail('Ese nombre ya está registrado en tu empresa, por favor escoge otro.');
                                        }
                                    };
                                }),
                        ]),

                    Grid::make(2)
                        ->extraAttributes(['class' => 'producto-linea-2'])
                        ->schema([
                            Select::make('tipo_servicio')
                                ->label('Tipo de servicio')
                                ->native(false)
                                ->options([
                                    'propio'  => '🔧 Propio (lo hace la empresa / sus mecánicos)',
                                    'tercero' => '🤝 De tercero (no es ganancia de la empresa)',
                                ])
                                ->required()
                                ->live()
                                ->helperText('Propio: la empresa se queda con un % de ganancia. Tercero: solo se cobra y se contabiliza aparte.'),

                            TextInput::make('precio_venta1')
                                ->label('Valor del servicio')
                                ->numeric()
                                ->minValue(0)
                                ->required()
                                ->prefix('$')
                                ->helperText('Valor sugerido. Se puede editar al facturar en el POS.'),
                        ]),

                    Grid::make(2)
                        ->extraAttributes(['class' => 'producto-linea-1'])
                        ->schema([
                            TextInput::make('porcentaje_empresa')
                                ->label('% de ganancia para la empresa')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(100)
                                ->suffix('%')
                                ->visible(fn (Get $get) => $get('tipo_servicio') === 'propio')
                                ->required(fn (Get $get) => $get('tipo_servicio') === 'propio')
                                ->helperText('Puede variar de un servicio a otro.'),

                            Select::make('mecanico_id')
                                ->label('Mecánico')
                                ->native(false)
                                ->options(fn () => Mecanico::where('empresa_id', auth()->user()->getEmpresaActualId())
                                    ->where('activo', true)
                                    ->pluck('nombre', 'id'))
                                ->searchable()
                                ->visible(fn (Get $get) => $get('tipo_servicio') === 'propio')
                                ->required(fn (Get $get) => $get('tipo_servicio') === 'propio')
                                ->helperText('Quién lo ejecuta, para poder liquidarle su parte.'),

                            TextInput::make('tercero_nombre')
                                ->label('Nombre del tercero')
                                ->prefixIcon('heroicon-o-user')
                                ->maxLength(255)
                                ->columnSpanFull()
                                ->visible(fn (Get $get) => $get('tipo_servicio') === 'tercero')
                                ->required(fn (Get $get) => $get('tipo_servicio') === 'tercero')
                                ->helperText('A quién se le paga este servicio (no es ganancia de la empresa).'),
                        ]),

                    Grid::make(1)
                        ->extraAttributes(['class' => 'producto-linea-2'])
                        ->schema([
                            TextInput::make('iva_venta')
                                ->label('IVA venta (%)')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(100)
                                ->default(0)
                                ->suffix('%'),
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        $money = fn ($state): string => '$ ' . number_format((float) $state, 0, ',', '.');
        $percent = fn ($state): string => $state === null ? '-' : number_format((float) $state, 2, ',', '.') . ' %';

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('descripcion_larga')
                    ->label('Servicio')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('tipo_servicio')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state === 'propio' ? 'Propio' : 'Tercero')
                    ->color(fn ($state) => $state === 'propio' ? 'success' : 'warning'),

                Tables\Columns\TextColumn::make('mecanico.nombre')
                    ->label('Mecánico / Tercero')
                    ->formatStateUsing(fn ($state, $record) => $record->tipo_servicio === 'tercero' ? ($record->tercero_nombre ?: '-') : ($state ?: '-')),

                Tables\Columns\TextColumn::make('porcentaje_empresa')
                    ->label('% Empresa')
                    ->formatStateUsing($percent)
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('precio_venta1')
                    ->label('Valor')
                    ->formatStateUsing($money)
                    ->sortable()
                    ->alignRight(),

                Tables\Columns\TextColumn::make('iva_venta')
                    ->label('IVA')
                    ->formatStateUsing($percent)
                    ->alignCenter(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tipo_servicio')
                    ->label('Tipo')
                    ->options([
                        'propio'  => 'Propio',
                        'tercero' => 'Tercero',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('empresa_id', auth()->user()->getEmpresaActualId())
            ->where('tipo_producto', 'servicio');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListServicios::route('/'),
            'create' => Pages\CreateServicio::route('/crear'),
            'edit'   => Pages\EditServicio::route('/{record}/editar'),
        ];
    }
}
