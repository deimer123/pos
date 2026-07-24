<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ComplementoResource\Pages;
use App\Models\Complemento;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Catalogo de complementos (visible solo para super_admin). Se usa en
 * EmpresaResource para agregarle cargos adicionales a una empresa (ej.
 * Facturacion Electronica, POS Hibrido) al calcular su total a cobrar.
 */
class ComplementoResource extends Resource
{
    protected static ?string $model = Complemento::class;

    protected static ?string $navigationIcon = 'heroicon-o-puzzle-piece';

    protected static ?string $navigationLabel = 'Complementos';

    protected static ?string $modelLabel = 'Complemento';

    protected static ?string $pluralModelLabel = 'Complementos';

    protected static ?string $navigationGroup = 'Administración';

    protected static ?int $navigationSort = 21;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('nombre')
                ->required()
                ->maxLength(150),

            Forms\Components\TextInput::make('precio')
                ->numeric()
                ->minValue(0)
                ->prefix('$')
                ->required(),

            Forms\Components\TextInput::make('orden')
                ->numeric()
                ->default(0),

            Forms\Components\Toggle::make('activo')
                ->default(true)
                ->helperText('Los complementos inactivos no aparecen para elegir al crear una empresa.'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('orden')
            ->columns([
                Tables\Columns\TextColumn::make('nombre')->weight('bold')->searchable(),
                Tables\Columns\TextColumn::make('precio')
                    ->formatStateUsing(fn ($state) => '$ ' . number_format((float) $state, 0, ',', '.'))
                    ->sortable(),
                Tables\Columns\IconColumn::make('activo')->boolean(),
                Tables\Columns\TextColumn::make('orden')->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListComplementos::route('/'),
            'create' => Pages\CreateComplemento::route('/create'),
            'edit' => Pages\EditComplemento::route('/{record}/edit'),
        ];
    }
}
