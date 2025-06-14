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
use App\Models\TipoDocumento;
use App\Models\Departamento;
use App\Models\Ciudad;
use App\Filament\Resources\ClienteResource\Pages;

class ClienteResource extends Resource
{
    protected static ?string $model = Actor::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?int $navigationSort = 1;
    protected static ?string $label = 'Cliente';
    protected static ?string $pluralLabel = 'Clientes';

    public static function form(Form $form): Form
    {
        return $form->schema([
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
                ->options(Departamento::pluck('nombre', 'id')->toArray())
                ->searchable()
                ->reactive()
                ->required()
                ->afterStateUpdated(fn (callable $set) => $set('ciudad_id', null)),

            Select::make('ciudad_id')
                ->label('Ciudad')
                ->options(fn (callable $get) => 
                    $get('departamento_id') 
                        ? Ciudad::where('departamento_id', $get('departamento_id'))
                            ->pluck('nombre', 'id')
                            ->toArray()
                        : [])
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
                TextColumn::make('regimen_tributario')->label('Régimen')->toggleable(),
                TextColumn::make('telefono')->label('Teléfono')->toggleable(),
                TextColumn::make('email')->label('Email')->toggleable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([]); // ❌ Eliminar deshabilitado
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClientes::route('/'),
            'create' => Pages\CreateCliente::route('/create'),
            'edit' => Pages\EditCliente::route('/{record}/edit'),
        ];
    }
}
