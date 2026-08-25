<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RecetaResource\Pages;
use App\Models\Product;
use App\Models\Receta;
use App\Models\RecetaItem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RecetaResource extends Resource
{
    protected static ?string $model = Receta::class;

    protected static ?string $navigationIcon = 'heroicon-o-beaker';
    protected static ?string $navigationGroup = '🍽️ Recetas';
    protected static ?string $navigationLabel = 'Recetas';
    protected static ?string $modelLabel = 'Receta';
    protected static ?string $pluralModelLabel = 'Recetas';
    protected static ?int $navigationSort = 1;

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();
        if (!$user || !($user->hasRole('admin_empresa') || $user->hasRole('digitador'))) {
            return false;
        }
        if (! $user->puedeVerResource('recetas')) {
            return false;
        }
        $config = \App\Models\ConfiguracionEmpresa::where('empresa_id', $user->getEmpresaActualId())->first();
        return (bool) ($config?->usa_recetas ?? false);
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();
        if (!$user || !($user->hasRole('admin_empresa') || $user->hasRole('digitador'))) {
            return false;
        }
        if (! $user->puedeVerResource('recetas')) {
            return false;
        }
        $config = \App\Models\ConfiguracionEmpresa::where('empresa_id', $user->getEmpresaActualId())->first();
        return (bool) ($config?->usa_recetas ?? false);
    }

    public static function getEloquentQuery(): Builder
    {
        $empresaId = auth()->user()->getEmpresaActualId();
        return parent::getEloquentQuery()->where('empresa_id', $empresaId)->with(['producto', 'items.ingrediente']);
    }

    /**
     * Costo con IVA de un producto (ingrediente o producto vendido).
     * Delega en RecetaCosteoService, que usa la misma formula para el
     * recalculo automatico al confirmar una compra.
     */
    protected static function costoConIvaProducto(?Product $producto): float
    {
        return \App\Services\Recetas\RecetaCosteoService::costoConIvaProducto($producto);
    }

    /**
     * Cantidad de un ingrediente convertida a la unidad "grande" en la que
     * normalmente se fija su costo (kg, litro). Delega en
     * RecetaCosteoService (ver ahi la nota sobre la conversion /1000).
     */
    protected static function cantidadEnUnidadDeCosto(float $cantidad, string $unidad): float
    {
        return \App\Services\Recetas\RecetaCosteoService::cantidadEnUnidadDeCosto($cantidad, $unidad);
    }

    public static function form(Form $form): Form
    {
        $empresaId = auth()->user()->getEmpresaActualId();

        $calcularCostoReceta = function (Forms\Get $get): array {
            $items = $get('items') ?? [];
            $costoTotal = 0.0;

            foreach ($items as $item) {
                $ingredienteId = $item['ingrediente_product_id'] ?? null;
                $cantidad = (float) ($item['cantidad'] ?? 0);
                $unidad = $item['unidad'] ?? 'unidad';
                $merma = (float) ($item['merma'] ?? 0);

                if (! $ingredienteId || $cantidad <= 0) {
                    continue;
                }

                $ingrediente = Product::find($ingredienteId);
                $costoUnitario = static::costoConIvaProducto($ingrediente);
                $cantidadBase = static::cantidadEnUnidadDeCosto($cantidad, $unidad);
                $cantidadConMerma = $cantidadBase * (1 + $merma / 100);

                $costoTotal += $costoUnitario * $cantidadConMerma;
            }

            $rendimiento = (float) ($get('rendimiento') ?? 1) ?: 1.0;
            $costoPorPorcion = $costoTotal / $rendimiento;

            $productoVenta = $get('product_id') ? Product::find($get('product_id')) : null;
            $precioVenta = (float) ($productoVenta->precio_venta1 ?? 0);

            $utilidad = $precioVenta > 0
                ? round((($precioVenta - $costoPorPorcion) / $precioVenta) * 100, 2)
                : null;

            return compact('costoTotal', 'costoPorPorcion', 'precioVenta', 'utilidad');
        };

        return $form->schema([
            Forms\Components\Hidden::make('empresa_id')
                ->default($empresaId),

            Forms\Components\Section::make('Información de la receta')
                ->extraAttributes(['class' => 'combo-franja-azul'])
                ->schema([
                    Forms\Components\Grid::make(1)
                        ->extraAttributes(['class' => 'producto-linea-1'])
                        ->schema([
                            Forms\Components\Select::make('product_id')
                                ->label('Producto que se vende')
                                ->options(
                                    Product::where('empresa_id', $empresaId)
                                        ->orderBy('descripcion_larga')
                                        ->pluck('descripcion_larga', 'id')
                                )
                                ->searchable()
                                ->live()
                                ->required(),
                        ]),

                    Forms\Components\Grid::make(2)
                        ->extraAttributes(['class' => 'producto-linea-2'])
                        ->schema([
                            Forms\Components\TextInput::make('nombre')
                                ->label('Nombre de la receta (opcional)')
                                ->placeholder('Ej: Bandeja paisa, Cóctel mojito...')
                                ->maxLength(150),

                            Forms\Components\TextInput::make('rendimiento')
                                ->label('Rendimiento (porciones que produce)')
                                ->numeric()
                                ->default(1)
                                ->minValue(0.001)
                                ->step(0.001)
                                ->live(onBlur: true)
                                ->required(),
                        ]),

                    Forms\Components\Grid::make(2)
                        ->extraAttributes(['class' => 'producto-linea-1'])
                        ->schema([
                            Forms\Components\Select::make('unidad_rendimiento')
                                ->label('Unidad de rendimiento')
                                ->options([
                                    'unidad'  => 'Unidad',
                                    'porcion' => 'Porción',
                                    'litro'   => 'Litro',
                                    'kg'      => 'Kilogramo',
                                ])
                                ->default('unidad')
                                ->required(),

                            Forms\Components\Toggle::make('activo')
                                ->label('Activa')
                                ->default(true),
                        ]),
                ]),

            Forms\Components\Section::make('Ingredientes')
                ->description('Define los ingredientes y cantidades que se descuentan del inventario al vender este producto.')
                ->extraAttributes(['class' => 'combo-franja-azul'])
                ->schema([
                    Forms\Components\Repeater::make('items')
                        ->relationship('items')
                        ->label('')
                        ->defaultItems(1)
                        ->addActionLabel('+ Agregar ingrediente')
                        ->live()
                        ->schema([
                            Forms\Components\Hidden::make('empresa_id')
                                ->default($empresaId),

                            Forms\Components\Grid::make(4)
                                ->extraAttributes(['class' => 'producto-linea-1'])
                                ->schema([
                                    Forms\Components\Select::make('ingrediente_product_id')
                                        ->label('Ingrediente')
                                        ->options(
                                            Product::where('empresa_id', $empresaId)
                                                ->orderBy('descripcion_larga')
                                                ->pluck('descripcion_larga', 'id')
                                        )
                                        ->searchable()
                                        ->live()
                                        ->required()
                                        ->columnSpan(2),

                                    Forms\Components\TextInput::make('cantidad')
                                        ->label('Cantidad')
                                        ->numeric()
                                        ->minValue(0.001)
                                        ->step(0.001)
                                        ->live(onBlur: true)
                                        ->required(),

                                    Forms\Components\Select::make('unidad')
                                        ->label('Unidad')
                                        ->options([
                                            'unidad' => 'Unidad',
                                            'kg'     => 'Kilogramo',
                                            'gr'     => 'Gramo',
                                            'litro'  => 'Litro',
                                            'ml'     => 'Mililitro',
                                            'porcion'=> 'Porción',
                                        ])
                                        ->default('unidad')
                                        ->live()
                                        ->required(),
                                ]),

                            Forms\Components\Grid::make(1)
                                ->extraAttributes(['class' => 'producto-linea-2'])
                                ->schema([
                                    Forms\Components\TextInput::make('merma')
                                        ->label('Merma (%)')
                                        ->numeric()
                                        ->default(0)
                                        ->minValue(0)
                                        ->maxValue(100)
                                        ->step(0.1)
                                        ->live(onBlur: true)
                                        ->helperText('Porcentaje de pérdida del ingrediente'),
                                ]),
                        ]),
                ]),

            Forms\Components\Section::make('Costo y utilidad')
                ->description('Calculado con el costo (con IVA) de cada ingrediente segun la cantidad que se gasta. Ej: si el kilo de un ingrediente cuesta $1.000 con IVA incluido y la receta gasta 100 gramos, ese ingrediente aporta $100 al costo total.')
                ->extraAttributes(['class' => 'combo-franja-azul'])
                ->schema([
                    Forms\Components\Placeholder::make('costo_receta')
                        ->label('')
                        ->content(function (Forms\Get $get) use ($calcularCostoReceta) {
                            $c = $calcularCostoReceta($get);
                            $fmt = fn (float $v) => '$' . number_format($v, 0, ',', '.');

                            $filas = '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;">'
                                . '<div><div style="font-size:11px;color:#64748b;font-weight:700;text-transform:uppercase;">Costo total de la receta</div>'
                                . '<div style="font-size:20px;font-weight:800;color:#1e293b;">' . $fmt($c['costoTotal']) . '</div></div>'
                                . '<div><div style="font-size:11px;color:#64748b;font-weight:700;text-transform:uppercase;">Costo por porción</div>'
                                . '<div style="font-size:20px;font-weight:800;color:#1e293b;">' . $fmt($c['costoPorPorcion']) . '</div></div>';

                            if ($c['precioVenta'] > 0) {
                                $colorUtilidad = $c['utilidad'] >= 0 ? '#16a34a' : '#dc2626';
                                $filas .= '<div><div style="font-size:11px;color:#64748b;font-weight:700;text-transform:uppercase;">Precio de venta</div>'
                                    . '<div style="font-size:20px;font-weight:800;color:#1e293b;">' . $fmt($c['precioVenta']) . '</div></div>'
                                    . '<div><div style="font-size:11px;color:#64748b;font-weight:700;text-transform:uppercase;">Utilidad</div>'
                                    . '<div style="font-size:20px;font-weight:800;color:' . $colorUtilidad . ';">' . number_format($c['utilidad'], 2) . '%</div></div>';
                            } else {
                                $filas .= '<div style="grid-column:1/-1;color:#94a3b8;font-size:13px;">Selecciona el producto que se vende para ver el precio de venta y la utilidad.</div>';
                            }

                            $filas .= '</div>';

                            return new \Illuminate\Support\HtmlString($filas);
                        }),

                    Forms\Components\Actions::make([
                        Forms\Components\Actions\Action::make('actualizar_costo_producto')
                            ->label('💰 Actualizar costo del producto')
                            ->color('success')
                            ->requiresConfirmation()
                            ->modalHeading('¿Actualizar el costo del producto?')
                            ->modalDescription('Reemplaza el costo actual del producto por el costo por porción calculado arriba (ya incluye el IVA de los ingredientes). No se vuelve a aplicar IVA ni descuento comercial encima de este valor.')
                            ->modalSubmitActionLabel('Sí, actualizar')
                            ->disabled(fn (Forms\Get $get) => ! $get('product_id'))
                            ->action(function (Forms\Get $get) use ($calcularCostoReceta) {
                                $producto = Product::find($get('product_id'));

                                if (! $producto) {
                                    return;
                                }

                                $c = $calcularCostoReceta($get);
                                $nuevoCosto = round($c['costoPorPorcion'], 2);

                                $datos = [
                                    'precio_costo' => $nuevoCosto,
                                    'precio_con_descuento' => $nuevoCosto,
                                    'costo_iva' => $nuevoCosto,
                                ];

                                $precioVenta = (float) $producto->precio_venta1;
                                if ($precioVenta > 0) {
                                    $datos['utilidad1'] = round((($precioVenta - $nuevoCosto) / $precioVenta) * 100, 2);
                                }

                                $producto->update($datos);

                                Notification::make()
                                    ->title('Costo del producto actualizado')
                                    ->body('Nuevo costo: $' . number_format($nuevoCosto, 0, ',', '.'))
                                    ->success()
                                    ->send();
                            }),
                    ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('producto.descripcion_larga')
                    ->label('Producto')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('nombre')
                    ->label('Nombre receta')
                    ->placeholder('—')
                    ->searchable(),

                Tables\Columns\TextColumn::make('items_count')
                    ->label('Ingredientes')
                    ->counts('items')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('porciones_disponibles')
                    ->label('Porciones disponibles')
                    ->getStateUsing(fn ($record) => $record->load('items.ingrediente')->porciones_disponibles)
                    ->formatStateUsing(fn ($state, $record) => number_format($state, 2) . ' ' . $record->unidad_rendimiento)
                    ->badge()
                    ->color(fn ($state) => $state <= 0 ? 'danger' : ($state <= 5 ? 'warning' : 'success')),

                Tables\Columns\TextColumn::make('rendimiento')
                    ->label('Rendimiento')
                    ->formatStateUsing(fn ($state, $record) => $state . ' ' . $record->unidad_rendimiento),

                Tables\Columns\IconColumn::make('activo')
                    ->label('Activa')
                    ->boolean(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Actualizada')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('activo')->label('Estado'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListRecetas::route('/'),
            'create' => Pages\CreateReceta::route('/create'),
            'edit'   => Pages\EditReceta::route('/{record}/edit'),
        ];
    }
}
