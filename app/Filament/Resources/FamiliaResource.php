<?php

namespace App\Filament\Resources;

use App\Models\Familia;
use Filament\Forms;
use Filament\Tables;
use Filament\Resources\Resource;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\FamiliaResource\Pages;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Section;

class FamiliaResource extends Resource
{
    protected static ?string $model = Familia::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-group';
    protected static ?string $label = 'Familia';
    protected static ?string $pluralLabel = 'Familias';
    protected static ?string $navigationGroup = '🏷 Categorías';
    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form->schema([

             Hidden::make('empresa_id')
    ->default(fn () => auth()->user()->getEmpresaActualId())
    ->dehydrated(),

            Section::make('Familia')
                ->extraAttributes(['class' => 'combo-franja-azul'])
                ->schema([
                    Grid::make(2)
                        ->extraAttributes(['class' => 'producto-linea-1'])
                        ->schema([
                            Forms\Components\TextInput::make('nombre')
                                ->label('Nombre de la familia')
                                ->required(),

                            Forms\Components\Toggle::make('mostrar_en_catalogo')
                                ->label('Mostrar en el catálogo público')
                                ->default(true)
                                ->helperText('Desactívala para ocultar esta familia (y sus productos) del catálogo que ven tus clientes.'),
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('nombre')->label('Nombre')->searchable(),
            Tables\Columns\IconColumn::make('mostrar_en_catalogo')->label('En catálogo')->boolean(),
        ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('empresa_id', auth()->user()->getEmpresaActualId());
    }

    public static function mutateFormDataBeforeCreate(array $data): array
    {
        $data['empresa_id'] =auth()->user()->getEmpresaActualId();
        return $data;
    }

    public static function mutateFormDataBeforeSave(array $data): array
    {
        $data['empresa_id'] = auth()->user()->getEmpresaActualId();
        return $data;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFamilias::route('/'),
            'create' => Pages\CreateFamilia::route('/create'),
            'edit' => Pages\EditFamilia::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
{
    $user = auth()->user();
    return (bool) $user && ($user->hasRole('admin_empresa') || ($user->hasRole('digitador') && $user->puedeVerResource('familias')));
}

public static function shouldRegisterNavigation(): bool
{
    return static::canAccess();
}

public static function canCreate(): bool
{
   return auth()->check() &&
    auth()->user()->hasAnyRole([
        'admin_empresa',
        'digitador'
    ]);
}

public static function canEdit($record): bool
{
    return auth()->user()->hasRole('admin_empresa');
}

public static function canDelete($record): bool
{
    return auth()->user()->hasRole('admin_empresa');
}

}
