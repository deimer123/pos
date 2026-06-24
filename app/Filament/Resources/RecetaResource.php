<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RecetaResource\Pages;
use App\Models\Product;
use App\Models\Receta;
use App\Models\RecetaItem;
use Filament\Forms;
use Filament\Forms\Form;
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
        $config = \App\Models\ConfiguracionEmpresa::where('empresa_id', $user->getEmpresaActualId())->first();
        return (bool) ($config?->usa_recetas ?? false);
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();
        if (!$user || !($user->hasRole('admin_empresa') || $user->hasRole('digitador'))) {
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

    public static function form(Form $form): Form
    {
        $empresaId = auth()->user()->getEmpresaActualId();

        return $form->schema([
            Forms\Components\Section::make('Información de la receta')
                ->columns(2)
                ->schema([
                    Forms\Components\Hidden::make('empresa_id')
                        ->default($empresaId),

                    Forms\Components\Select::make('product_id')
                        ->label('Producto que se vende')
                        ->options(
                            Product::where('empresa_id', $empresaId)
                                ->orderBy('descripcion_larga')
                                ->pluck('descripcion_larga', 'id')
                        )
                        ->searchable()
                        ->required()
                        ->columnSpanFull(),

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
                        ->required(),

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

            Forms\Components\Section::make('Ingredientes')
                ->description('Define los ingredientes y cantidades que se descuentan del inventario al vender este producto.')
                ->schema([
                    Forms\Components\Repeater::make('items')
                        ->relationship('items')
                        ->label('')
                        ->columns(4)
                        ->defaultItems(1)
                        ->addActionLabel('+ Agregar ingrediente')
                        ->schema([
                            Forms\Components\Hidden::make('empresa_id')
                                ->default($empresaId),

                            Forms\Components\Select::make('ingrediente_product_id')
                                ->label('Ingrediente')
                                ->options(
                                    Product::where('empresa_id', $empresaId)
                                        ->orderBy('descripcion_larga')
                                        ->pluck('descripcion_larga', 'id')
                                )
                                ->searchable()
                                ->required()
                                ->columnSpan(2),

                            Forms\Components\TextInput::make('cantidad')
                                ->label('Cantidad')
                                ->numeric()
                                ->minValue(0.001)
                                ->step(0.001)
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
                                ->required(),

                            Forms\Components\TextInput::make('merma')
                                ->label('Merma (%)')
                                ->numeric()
                                ->default(0)
                                ->minValue(0)
                                ->maxValue(100)
                                ->step(0.1)
                                ->helperText('Porcentaje de pérdida del ingrediente'),
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
