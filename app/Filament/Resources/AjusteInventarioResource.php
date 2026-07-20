<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AjusteInventarioResource\Pages;
use App\Models\AjusteInventario;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Forms\Components\{
    Repeater, TextInput, Grid, Hidden
};
use Filament\Forms\Components\Actions\Action;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Forms\Components\Select; 
use Filament\Forms\Components\View;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Builder;


class AjusteInventarioResource extends Resource
{
    protected static ?string $model = AjusteInventario::class;

    protected static ?string $navigationLabel = 'Ajuste de Inventario';
    protected static ?string $pluralLabel = 'Ajustes de Inventario';
    protected static ?string $navigationIcon = 'heroicon-o-archive-box';
    protected static ?string $navigationGroup = '📦 Inventario';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
{
    return $form
        ->schema([

            // 🔹 TIPO
            Forms\Components\Select::make('tipo')
                ->label('Tipo de movimiento')
                ->options([
                    'ajuste' => 'Ajuste de inventario',
                    'inventario_nuevo' => 'Inventario nuevo',
                ])
                ->required(),

            // 🔹 OBSERVACIÓN
            Forms\Components\Textarea::make('observacion')
                ->label('Observación')
                ->required(),

            // 🔁 REPEATER LIMPIO
            Repeater::make('detalles')
                ->label('Productos')
                ->defaultItems(1)
                ->reorderable(false)
                ->schema([

                    Grid::make(12)->schema([

                        // 🔢 CÓDIGO (AUTOFOCUS REAL SIN JS EXTERNO)
                        TextInput::make('codigo_ingresado')
    ->label('Código')
    ->columnSpan(4)
    ->live(onBlur: true)
    ->extraAttributes([
        'data-codigo' => 'true',
    ])
    ->afterStateUpdated(function ($state, Get $get, Set $set) {
        $codigo = trim((string) $state);

        $empresaId = auth()->user()->getEmpresaActualId();
        $producto = $codigo === '' ? null : Product::where('empresa_id', $empresaId)
            ->where(function ($q) use ($codigo) {
                $q->where('id_producto', $codigo)->orWhere('id', (int) $codigo);
            })
            ->first();

        if (! $producto) {
            $set('nombre_producto', null);
            $set('producto_id', null);
            $set('existencias_actuales', null);
            $set('producto_variante_id', null);
            return;
        }

        $set('codigo_ingresado', (string) $producto->id_producto);
        $set('producto_id', (string) $producto->id_producto);
        $set('nombre_producto', $producto->descripcion_larga);
        $set('existencias_actuales', (float) $producto->existencias);
        $set('producto_variante_id', null);
    }),

                        // 📝 PRODUCTO
                        TextInput::make('nombre_producto')
                            ->label('Producto')
                            ->disabled()
                            ->dehydrated()
                            ->columnSpan(8),

                        Hidden::make('producto_id'),
                        Hidden::make('existencias_actuales'),

                        // 🔢 CANTIDAD
                        TextInput::make('cantidad_nueva')
                            ->label('Cantidad')
                            ->numeric()
                            ->required()
                            ->columnSpan(4),

                        \Filament\Forms\Components\Placeholder::make('existencias_info')
                            ->label('Existencias actuales')
                            ->content(fn (Get $get) => (string) ($get('existencias_actuales') ?? '0'))
                            ->columnSpan(4),

                    ]),

                    Grid::make(12)
                        ->visible(fn (Get $get) => static::productoTieneVariantes($get))
                        ->schema([
                            Select::make('producto_variante_id')
                                ->label('Variante (talla/color)')
                                ->options(fn (Get $get) => static::opcionesVariantes($get))
                                ->required(fn (Get $get) => static::productoTieneVariantes($get))
                                ->validationMessages(['required' => 'Selecciona la variante (talla/color).'])
                                ->columnSpan(12),
                        ]),

                ]),

        ])
        ->columns(1);
}

    /* ----------------- Variantes (talla/color) ----------------- */
    protected static function productoTieneVariantes(Get $get): bool
    {
        $codigo = (string) ($get('producto_id') ?? '');
        if ($codigo === '') {
            return false;
        }

        $producto = Product::where('empresa_id', auth()->user()->getEmpresaActualId())
            ->where('id_producto', $codigo)
            ->first();

        if (! $producto) {
            return false;
        }

        return \App\Models\ProductoVariante::where('product_id', $producto->id)->where('activo', true)->exists();
    }

    protected static function opcionesVariantes(Get $get): array
    {
        $codigo = (string) ($get('producto_id') ?? '');
        if ($codigo === '') {
            return [];
        }

        $producto = Product::where('empresa_id', auth()->user()->getEmpresaActualId())
            ->where('id_producto', $codigo)
            ->first();

        if (! $producto) {
            return [];
        }

        return \App\Models\ProductoVariante::where('product_id', $producto->id)
            ->where('activo', true)
            ->orderBy('nombre')
            ->pluck('nombre', 'id')
            ->toArray();
    }

    public static function table(Table $table): Table
{
    
    return $table
        ->columns([
           Tables\Columns\BadgeColumn::make('tipo')
    ->label('Tipo')
    ->colors([
        'warning' => 'inventario_nuevo',
        'success' => 'ajuste',
    ])
    ->formatStateUsing(fn (string $state) => match ($state) {
        'inventario_nuevo' => 'Inventario nuevo',
        'ajuste'           => 'Ajuste',
        default            => $state,
    }),

            Tables\Columns\TextColumn::make('estado')
                ->badge()
                ->color(fn ($state) => $state === 'borrador' ? 'warning' : 'success'),

            Tables\Columns\TextColumn::make('usuario.name')->label('Usuario'),

            Tables\Columns\TextColumn::make('created_at')
                ->label('Fecha')
                ->dateTime(),
        ])
        ->actions([
    Tables\Actions\Action::make('pdf')
    ->label('PDF')
    ->icon('heroicon-o-document')
    ->url(fn ($record) => url('/inventario/reporte-pdf/' . $record->id))
    ->openUrlInNewTab()
    ->visible(fn ($record) => $record->estado === 'confirmado')

]);
}

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = auth()->user();

        // Si es admin_empresa → usa su propio ID como empresa
        if ($user->hasRole('admin_empresa')) {
            return $query->where('empresa_id', $user->id);
        }

        // Si es empleado → usa empresa_id asignado
        if ($user->hasRole('digitador') || $user->hasRole('vendedor')) {
            return $query->where('empresa_id', $user->empresa_id);
        }

        // Seguridad extra
        return $query->whereRaw('1 = 0');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAjusteInventarios::route('/'),
            'create' => Pages\CreateAjusteInventario::route('/crear'),
        ];
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();
        return (bool) $user && ($user->hasRole('admin_empresa') || ($user->hasRole('digitador') && $user->puedeVerResource('ajustes_inventario')));
    }


}
