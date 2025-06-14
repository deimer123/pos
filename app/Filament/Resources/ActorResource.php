<?php

namespace App\Filament\Resources;

use App\Models\Actor;
use Filament\Forms;
use Filament\Tables;
use Filament\Resources\Resource;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Form;
use Filament\Tables\Table;
use App\Filament\Resources\ActorResource\Pages;
use App\Models\TipoDocumento;

class ActorResource extends Resource
{
    protected static ?string $model = Actor::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'Configuraciones';
    protected static ?string $label = 'Actor';
    protected static ?string $pluralLabel = 'Actores';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                

                Select::make('clasificacion')
                    ->label('Clasificación')
                    ->options([
                        'cliente' => 'Cliente',
                        'proveedor' => 'Proveedor',
                    ])
                    ->required(),

                TextInput::make('nombre')->label('Nombre'),
                TextInput::make('razon_social')->label('Razón Social'),

                Select::make('tipo_persona')
                    ->label('Tipo de persona')
                    ->options([
                        'natural' => 'Natural',
                        'juridica' => 'Jurídica',
                    ]),

                Select::make('tipo_documento_id')
    ->label('Tipo de documento')
    ->options(fn () => TipoDocumento::all()->pluck('nombre', 'id'))
    ->searchable()
    ->required(),

                    Select::make('departamento_id')
    ->label('Departamento')
    ->options(\App\Models\Departamento::pluck('nombre', 'id')->toArray())
    ->searchable()
    ->reactive()
    ->required()
    ->afterStateUpdated(fn (callable $set) => $set('ciudad_id', null)),

Select::make('ciudad_id')
    ->label('Ciudad')
    ->options(fn (callable $get) => 
        $get('departamento_id') 
            ? \App\Models\Ciudad::where('departamento_id', $get('departamento_id'))
                ->pluck('nombre', 'id')
                ->toArray()
            : []
    )
    ->searchable()
    ->required()
    ->disabled(fn (callable $get) => empty($get('departamento_id')))
    ->placeholder('Selecciona primero un departamento'),


                TextInput::make('identificacion')->label('Número de documento'),
                TextInput::make('direccion')->label('Dirección'),
                TextInput::make('telefono')->label('Teléfono'),
                TextInput::make('email')->label('Correo electrónico'),

                Select::make('regimen_tributario')
                    ->label('Régimen')
                    ->options([
                        'comun' => 'Común',
                        'simplificado' => 'Simplificado',
                        'especial' => 'Especial',
                        'otro' => 'Otro',
                    ]),

                Select::make('responsable_iva')
                    ->label('¿Responsable de IVA?')
                    ->options([
                        true => 'Sí',
                        false => 'No',
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre')->label('Nombre')->searchable(),
                TextColumn::make('razon_social')->label('Razón Social')->toggleable(),
                TextColumn::make('identificacion')->label('Identificación')->searchable(),
                TextColumn::make('clasificacion')->label('Clasificación')->badge()->color(fn ($state) => $state === 'cliente' ? 'success' : 'info'),
                TextColumn::make('regimen_tributario')->label('Régimen')->toggleable(),
                TextColumn::make('telefono')->label('Teléfono')->toggleable(),
                TextColumn::make('email')->label('Email')->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('clasificacion')
                    ->options([
                        'cliente' => 'Clientes',
                        'proveedor' => 'Proveedores',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
{
    return [
        'index' => Pages\ListActors::route('/'),
        'create' => Pages\CreateActor::route('/create'),
        'edit' => Pages\EditActor::route('/{record}/edit'),
    ];
}

}
