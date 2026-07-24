<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaqueteUsuariosResource\Pages;
use App\Models\PaqueteUsuarios;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Catalogo de paquetes de usuarios adicionales (visible solo para
 * super_admin). Se usa en EmpresaResource para sumarle cupos de usuario
 * extra a una empresa por encima de los que trae su plan.
 */
class PaqueteUsuariosResource extends Resource
{
    protected static ?string $model = PaqueteUsuarios::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-plus';

    protected static ?string $navigationLabel = 'Paquetes de usuarios';

    protected static ?string $modelLabel = 'Paquete de usuarios';

    protected static ?string $pluralModelLabel = 'Paquetes de usuarios';

    protected static ?string $navigationGroup = 'Administración';

    protected static ?int $navigationSort = 22;

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

            Forms\Components\TextInput::make('usuarios_adicionales')
                ->label('Usuarios adicionales')
                ->numeric()
                ->minValue(1)
                ->required(),

            Forms\Components\TextInput::make('precio')
                ->numeric()
                ->minValue(0)
                ->prefix('$')
                ->suffix('/año')
                ->required(),

            Forms\Components\TextInput::make('orden')
                ->numeric()
                ->default(0),

            Forms\Components\Toggle::make('activo')
                ->default(true)
                ->helperText('Los paquetes inactivos no aparecen para elegir al crear una empresa.'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('orden')
            ->columns([
                Tables\Columns\TextColumn::make('nombre')->weight('bold')->searchable(),
                Tables\Columns\TextColumn::make('usuarios_adicionales')->label('Usuarios')->alignCenter(),
                Tables\Columns\TextColumn::make('precio')
                    ->formatStateUsing(fn ($state) => '$ ' . number_format((float) $state, 0, ',', '.') . '/año')
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
            'index' => Pages\ListPaqueteUsuarios::route('/'),
            'create' => Pages\CreatePaqueteUsuarios::route('/create'),
            'edit' => Pages\EditPaqueteUsuarios::route('/{record}/edit'),
        ];
    }
}
