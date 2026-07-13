<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CatalogoResource\Pages;
use App\Models\Catalogo;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class CatalogoResource extends Resource
{
    protected static ?string $model = Catalogo::class;
    protected static ?string $navigationIcon = 'heroicon-o-book-open';
    protected static ?string $navigationLabel = 'Catálogos';
    protected static ?string $modelLabel = 'Catálogo';
    protected static ?string $pluralModelLabel = 'Catálogos';
    protected static ?string $navigationGroup = 'Administración';

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasAnyRole(['super_admin', 'admin_empresa']);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasAnyRole(['super_admin', 'admin_empresa']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Hidden::make('empresa_id')
                ->default(fn () => auth()->user()->getEmpresaActualId())
                ->dehydrated(),

            Forms\Components\Section::make('Catálogo')
                ->extraAttributes(['class' => 'combo-franja-azul'])
                ->schema([
                    Forms\Components\Grid::make(2)
                        ->extraAttributes(['class' => 'producto-linea-1'])
                        ->schema([
                            Forms\Components\TextInput::make('nombre')
                                ->label('Nombre del catálogo')
                                ->required()
                                ->maxLength(255),

                            Forms\Components\Toggle::make('activo')
                                ->label('Activo')
                                ->default(true)
                                ->helperText('Si lo desactivas, el enlace público dejará de mostrar productos.'),
                        ]),

                    Forms\Components\Grid::make(1)
                        ->extraAttributes(['class' => 'producto-linea-2'])
                        ->schema([
                            Forms\Components\TextInput::make('slug')
                                ->label('Nombre del catálogo (URL)')
                                ->prefix(url('/catalogo') . '/')
                                ->maxLength(255)
                                ->unique(table: Catalogo::class, column: 'slug', ignoreRecord: true)
                                ->helperText('Solo letras, números y guiones. Si lo dejas vacío, se genera automáticamente a partir del nombre.')
                                ->dehydrateStateUsing(fn (?string $state) => filled($state) ? Str::slug($state) : null),

                            Forms\Components\Placeholder::make('catalogo_url')
                                ->label('Enlace público')
                                ->visible(fn (?Catalogo $record): bool => (bool) $record?->slug)
                                ->content(function (?Catalogo $record) {
                                    if (! $record?->slug) {
                                        return '—';
                                    }

                                    $url = url('/catalogo/' . $record->slug);

                                    return new \Illuminate\Support\HtmlString(
                                        '<a href="' . e($url) . '" target="_blank" style="color:#4f46e5; text-decoration:underline;">' . e($url) . '</a>'
                                    );
                                }),
                        ]),

                    Forms\Components\Grid::make(1)
                        ->extraAttributes(['class' => 'producto-linea-1'])
                        ->schema([
                            Forms\Components\Select::make('tipo_seleccion')
                                ->label('¿Qué productos incluye este catálogo?')
                                ->options([
                                    'todos' => 'Todos los productos de la empresa',
                                    'familias' => 'Por familias',
                                    'subfamilias' => 'Por subfamilias',
                                    'detallado' => 'Elegir producto por producto',
                                ])
                                ->default('detallado')
                                ->required()
                                ->live()
                                ->afterStateUpdated(function (Forms\Set $set) {
                                    $set('familias', []);
                                    $set('subfamilias', []);
                                    $set('itemsProductos', []);
                                }),
                        ]),

                    Forms\Components\Placeholder::make('tipo_seleccion_todos_info')
                        ->label('')
                        ->visible(fn (Forms\Get $get) => $get('tipo_seleccion') === 'todos')
                        ->content('Se mostrarán todos los productos visibles de la empresa. No necesitas elegir nada más aquí.'),

                    Forms\Components\Select::make('familias')
                        ->relationship(
                            name: 'familias',
                            titleAttribute: 'nombre',
                            modifyQueryUsing: fn (Builder $query) => $query->where('empresa_id', auth()->user()->getEmpresaActualId()),
                        )
                        ->label('Familias incluidas en este catálogo')
                        ->multiple()
                        ->searchable()
                        ->visible(fn (Forms\Get $get) => $get('tipo_seleccion') === 'familias'),

                    Forms\Components\Select::make('subfamilias')
                        ->relationship(
                            name: 'subfamilias',
                            titleAttribute: 'nombre',
                            modifyQueryUsing: fn (Builder $query) => $query->where('empresa_id', auth()->user()->getEmpresaActualId()),
                        )
                        ->label('Subfamilias incluidas en este catálogo')
                        ->multiple()
                        ->searchable()
                        ->visible(fn (Forms\Get $get) => $get('tipo_seleccion') === 'subfamilias'),

                    Forms\Components\Repeater::make('itemsProductos')
                        ->relationship('itemsProductos')
                        ->label('Productos incluidos en este catálogo')
                        ->addActionLabel('+ Agregar producto')
                        ->defaultItems(0)
                        ->grid(2)
                        ->visible(fn (Forms\Get $get) => $get('tipo_seleccion') === 'detallado')
                        ->simple(
                            Forms\Components\Select::make('product_id')
                                ->label('Producto')
                                ->live()
                                ->options(function (Forms\Get $get, ?string $state) {
                                    $yaAgregados = collect($get('../../itemsProductos'))
                                        ->pluck('product_id')
                                        ->filter()
                                        ->reject(fn ($id) => (string) $id === (string) $state)
                                        ->all();

                                    return \App\Models\Product::where('empresa_id', auth()->user()->getEmpresaActualId())
                                        ->when($yaAgregados, fn ($q) => $q->whereNotIn('id', $yaAgregados))
                                        ->orderBy('descripcion_larga')
                                        ->get()
                                        ->mapWithKeys(fn ($producto) => [
                                            $producto->id => $producto->descripcion_larga . ' — $' . number_format((float) $producto->precio_venta1, 0, ',', '.'),
                                        ]);
                                })
                                ->searchable()
                                ->required()
                        ),
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
                    ->sortable(),

                Tables\Columns\TextColumn::make('slug')
                    ->label('URL')
                    ->searchable(),

                Tables\Columns\TextColumn::make('tipo_seleccion')
                    ->label('Tipo')
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'todos' => 'Todos los productos',
                        'familias' => 'Por familias',
                        'subfamilias' => 'Por subfamilias',
                        default => 'Producto por producto',
                    })
                    ->badge(),

                Tables\Columns\TextColumn::make('productos_count')
                    ->counts('productos')
                    ->label('Productos (detallado)'),

                Tables\Columns\IconColumn::make('activo')
                    ->label('Activo')
                    ->boolean(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('activo')
                    ->label('Estado'),
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

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        if ($user && $user->hasRole('super_admin')) {
            return parent::getEloquentQuery();
        }

        return parent::getEloquentQuery()
            ->where('empresa_id', $user->getEmpresaActualId());
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCatalogos::route('/'),
            'create' => Pages\CreateCatalogo::route('/create'),
            'edit' => Pages\EditCatalogo::route('/{record}/edit'),
        ];
    }
}
