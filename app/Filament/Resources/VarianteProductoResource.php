<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VarianteProductoResource\Pages;
use App\Models\ConfiguracionEmpresa;
use App\Models\ProductoVariante;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

// Listado de solo lectura: UNA fila POR VARIANTE (talla/color), no una fila
// resumida por producto -- para ver de un vistazo cuanto stock hay de cada
// talla/color sin tener que entrar producto por producto a revisar el
// repeater de Variantes en Productos. Editar el stock se sigue haciendo
// desde el producto o por Ajuste de Inventario; esta pantalla es solo de
// consulta.
class VarianteProductoResource extends Resource
{
    protected static ?string $model = ProductoVariante::class;

    protected static ?string $navigationIcon = 'heroicon-o-swatch';

    protected static ?string $navigationLabel = 'Variantes';

    protected static ?string $modelLabel = 'Variante';

    protected static ?string $pluralModelLabel = 'Variantes';

    protected static ?string $navigationGroup = '📦 Inventario';

    protected static ?int $navigationSort = 2;

    public static function shouldRegisterNavigation(): bool
    {
        // Es solo informativo (las variantes se editan dentro de cada
        // Producto) -- se saca del menu para no duplicar la navegacion.
        return false;
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();
        if (! $user || ! $user->puedeVerResource('productos')) {
            return false;
        }

        return (bool) ConfiguracionEmpresa::where('empresa_id', $user->getEmpresaActualId())->value('usa_variantes');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('producto.descripcion_larga')
                    ->label('Producto')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('atributos.talla')
                    ->label('Talla')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('atributos.color')
                    ->label('Color')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('stock')
                    ->label('Stock')
                    ->numeric()
                    ->sortable()
                    ->badge()
                    ->color(fn ($state) => match (true) {
                        (float) $state <= 0 => 'danger',
                        (float) $state <= 5 => 'warning',
                        default => 'success',
                    }),

                Tables\Columns\IconColumn::make('activo')
                    ->label('Activa')
                    ->boolean(),
            ])
            ->defaultSort('producto.descripcion_larga')
            ->filters([
                Tables\Filters\SelectFilter::make('product_id')
                    ->label('Producto')
                    ->relationship(
                        'producto',
                        'descripcion_larga',
                        fn (Builder $query) => $query->where('empresa_id', auth()->user()->getEmpresaActualId())
                    )
                    ->searchable(),

                Tables\Filters\TernaryFilter::make('activo')
                    ->label('Activa'),
            ])
            ->actions([]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('empresa_id', auth()->user()->getEmpresaActualId())
            ->with('producto');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVarianteProductos::route('/'),
        ];
    }
}
