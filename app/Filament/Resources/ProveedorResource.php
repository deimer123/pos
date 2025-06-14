<?php

namespace App\Filament\Resources;

use App\Models\Actor;
use Filament\Forms;
use Filament\Tables;
use Filament\Resources\Resource;
use Filament\Forms\Form;
use Filament\Tables\Table;
use App\Models\TipoDocumento;
use App\Models\Departamento;
use App\Models\Ciudad;
use App\Filament\Resources\ProveedorResource\Pages;

class ProveedorResource extends Resource
{
    protected static ?string $model = Actor::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?int $navigationSort = 2;
    protected static ?string $label = 'Proveedor';
    protected static ?string $pluralLabel = 'Proveedores';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Hidden::make('clasificacion')->default('proveedor'),

                Forms\Components\TextInput::make('nombre')->label('Nombre'),
                Forms\Components\TextInput::make('razon_social')->label('Razón Social'),

                Forms\Components\Select::make('tipo_persona')
                    ->label('Tipo de persona')
                    ->options([
                        'natural' => 'Natural',
                        'juridica' => 'Jurídica',
                    ]),

                Forms\Components\Select::make('tipo_documento_id')
                    ->label('Tipo de documento')
                    ->options(fn () => TipoDocumento::pluck('nombre', 'id'))
                    ->searchable()
                    ->required(),

                Forms\Components\Select::make('departamento_id')
                    ->label('Departamento')
                    ->options(fn () => Departamento::pluck('nombre', 'id'))
                    ->searchable()
                    ->reactive()
                    ->required()
                    ->afterStateUpdated(fn ($set) => $set('ciudad_id', null)),

                Forms\Components\Select::make('ciudad_id')
                    ->label('Ciudad')
                    ->options(fn ($get) =>
                        $get('departamento_id')
                            ? Ciudad::where('departamento_id', $get('departamento_id'))->pluck('nombre', 'id')->toArray()
                            : []
                    )
                    ->searchable()
                    ->required()
                    ->disabled(fn ($get) => empty($get('departamento_id')))
                    ->placeholder('Selecciona primero un departamento'),

                Forms\Components\TextInput::make('identificacion')->label('Número de documento'),
                Forms\Components\TextInput::make('direccion')->label('Dirección'),
                Forms\Components\TextInput::make('telefono')->label('Teléfono'),
                Forms\Components\TextInput::make('email')->label('Correo electrónico'),

                Forms\Components\Select::make('regimen_tributario')
                    ->label('Régimen')
                    ->options([
                        'comun' => 'Común',
                        'simplificado' => 'Simplificado',
                        'especial' => 'Especial',
                        'otro' => 'Otro',
                    ]),

                Forms\Components\Select::make('responsable_iva')
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
                Tables\Columns\TextColumn::make('nombre')->label('Nombre')->searchable(),
                Tables\Columns\TextColumn::make('razon_social')->label('Razón Social'),
                Tables\Columns\TextColumn::make('identificacion')->label('Identificación'),
                
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                // ❌ No permitir eliminar
            ])
            ->bulkActions([]); // ❌ Desactiva eliminación masiva
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProveedores::route('/'),
            'create' => Pages\CreateProveedor::route('/create'),
            'edit' => Pages\EditProveedor::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        // ✅ Mostrar solo proveedores
        return parent::getEloquentQuery()->where('clasificacion', 'proveedor');
    }
}
