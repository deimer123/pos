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
    ->extraAttributes([
        'data-codigo' => 'true',
    ]),       

                        // 📝 PRODUCTO
                        TextInput::make('nombre_producto')
                            ->label('Producto')
                            ->columnSpan(8),

                        // 🔢 CANTIDAD
                        TextInput::make('cantidad_nueva')
                            ->label('Cantidad')
                            ->numeric()
                            ->columnSpan(4),

                    ]),

                ]),

        ])
        ->columns(1);
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
