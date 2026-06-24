<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductComboResource\Pages;
use App\Models\Product;
use App\Models\ProductCombo;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductComboResource extends Resource
{
    protected static ?string $model = ProductCombo::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';
    protected static ?string $navigationGroup = '🏷️ Combos';
    protected static ?string $navigationLabel = 'Combos';
    protected static ?string $modelLabel = 'Combo';
    protected static ?string $pluralModelLabel = 'Combos';
    protected static ?int $navigationSort = 1;

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();
        return $user && ($user->hasRole('admin_empresa') || $user->hasRole('digitador'));
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();
        return $user && ($user->hasRole('admin_empresa') || $user->hasRole('digitador'));
    }

    public static function getEloquentQuery(): Builder
    {
        $empresaId = auth()->user()->getEmpresaActualId();
        return parent::getEloquentQuery()->where('empresa_id', $empresaId)->with('producto');
    }

    public static function form(Form $form): Form
    {
        $empresaId = auth()->user()->getEmpresaActualId();

        return $form->schema([
            Forms\Components\Section::make('Datos del combo')
                ->columns(2)
                ->schema([
                    Forms\Components\Hidden::make('empresa_id')->default($empresaId),

                    Forms\Components\Select::make('product_id')
                        ->label('Producto')
                        ->options(
                            Product::where('empresa_id', $empresaId)
                                ->orderBy('descripcion_larga')
                                ->pluck('descripcion_larga', 'id')
                        )
                        ->searchable()
                        ->required()
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('nombre')
                        ->label('Nombre del combo')
                        ->maxLength(150)
                        ->placeholder('Ej: Combo familiar, Promo 2x1...'),

                    Forms\Components\TextInput::make('cantidad_minima')
                        ->label('Cant. mínima')
                        ->numeric()
                        ->default(1)
                        ->minValue(0.01)
                        ->step(0.01)
                        ->required(),

                    Forms\Components\TextInput::make('precio_combo')
                        ->label('Precio combo')
                        ->numeric()
                        ->minValue(0)
                        ->step(0.01)
                        ->prefix('$')
                        ->required(),

                    Forms\Components\Toggle::make('activo')
                        ->label('Activo')
                        ->default(true),
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
                    ->label('Nombre combo')
                    ->placeholder('—')
                    ->searchable(),

                Tables\Columns\TextColumn::make('cantidad_minima')
                    ->label('Cant. mínima')
                    ->sortable(),

                Tables\Columns\TextColumn::make('precio_combo')
                    ->label('Precio combo')
                    ->money('COP')
                    ->sortable(),

                Tables\Columns\IconColumn::make('activo')
                    ->label('Activo')
                    ->boolean(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Actualizado')
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
            'index'  => Pages\ListCombos::route('/'),
            'create' => Pages\CreateCombo::route('/create'),
            'edit'   => Pages\EditCombo::route('/{record}/edit'),
        ];
    }
}
