<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PlanResource\Pages;
use App\Models\Plan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Catalogo de planes comerciales (visible solo para super_admin, que es
 * quien crea las empresas). Se usa en EmpresaResource para elegir el plan
 * de cada empresa nueva y calcular el total a cobrar.
 */
class PlanResource extends Resource
{
    protected static ?string $model = Plan::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationLabel = 'Planes';

    protected static ?string $modelLabel = 'Plan';

    protected static ?string $pluralModelLabel = 'Planes';

    protected static ?string $navigationGroup = 'Administración';

    protected static ?int $navigationSort = 20;

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
                ->maxLength(100),

            Forms\Components\TextInput::make('meses')
                ->label('Duración (meses)')
                ->numeric()
                ->minValue(1)
                ->required(),

            Forms\Components\TextInput::make('precio')
                ->numeric()
                ->minValue(0)
                ->prefix('$')
                ->required(),

            Forms\Components\TextInput::make('usuarios_incluidos')
                ->label('Usuarios incluidos')
                ->numeric()
                ->minValue(0)
                ->required(),

            Forms\Components\TextInput::make('orden')
                ->numeric()
                ->default(0)
                ->helperText('Controla el orden en que se muestran los planes.'),

            Forms\Components\Toggle::make('recomendado')
                ->helperText('Se marca como el plan destacado/recomendado.'),

            Forms\Components\Toggle::make('activo')
                ->default(true)
                ->helperText('Los planes inactivos no aparecen para elegir al crear una empresa.'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('orden')
            ->columns([
                Tables\Columns\TextColumn::make('nombre')->weight('bold')->searchable(),
                Tables\Columns\TextColumn::make('meses')->label('Meses')->alignCenter(),
                Tables\Columns\TextColumn::make('precio')
                    ->formatStateUsing(fn ($state) => '$ ' . number_format((float) $state, 0, ',', '.'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('usuarios_incluidos')->label('Usuarios')->alignCenter(),
                Tables\Columns\IconColumn::make('recomendado')->boolean(),
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
            'index' => Pages\ListPlans::route('/'),
            'create' => Pages\CreatePlan::route('/create'),
            'edit' => Pages\EditPlan::route('/{record}/edit'),
        ];
    }
}
