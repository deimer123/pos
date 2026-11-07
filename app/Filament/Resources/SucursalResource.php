<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SucursalResource\Pages;
use App\Models\Sucursal;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;

// Gestion de sucursales: solo la ve el admin de una empresa a la que el
// super_admin le encendio "Permitir sucursales". No hay boton de borrar: una
// sucursal con ventas/inventario no se puede perder, solo desactivar.
class SucursalResource extends Resource
{
    protected static ?string $model = Sucursal::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';
    protected static ?string $navigationGroup = '👨‍💼 Administración';
    protected static ?string $navigationLabel = 'Sucursales';
    protected static ?string $modelLabel = 'Sucursal';
    protected static ?string $pluralModelLabel = 'Sucursales';
    protected static ?int $navigationSort = 4;

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user
            && $user->hasRole('admin_empresa')
            && $user->empresaUsaSucursales();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('empresa_id', auth()->user()->getEmpresaActualId());
    }

    public static function form(Form $form): Form
    {
        $empresaId = auth()->user()->getEmpresaActualId();

        return $form->schema([
            Forms\Components\Section::make('Datos de la sucursal')
                ->schema([
                    Forms\Components\TextInput::make('nombre')
                        ->label('Nombre')
                        ->required()
                        ->maxLength(150)
                        ->unique(
                            ignoreRecord: true,
                            modifyRuleUsing: fn (Unique $rule) => $rule->where('empresa_id', $empresaId)
                        )
                        ->validationMessages(['unique' => 'Ya existe una sucursal con ese nombre.']),

                    Forms\Components\TextInput::make('codigo')
                        ->label('Código')
                        ->maxLength(30),

                    Forms\Components\TextInput::make('telefono')
                        ->label('Teléfono')
                        ->maxLength(255),

                    Forms\Components\Textarea::make('direccion')
                        ->label('Dirección')
                        ->maxLength(500),

                    Forms\Components\Toggle::make('activo')
                        ->label('Activa')
                        ->default(true)
                        ->disabled(fn (?Sucursal $record) => (bool) $record?->es_principal)
                        ->helperText(fn (?Sucursal $record) => $record?->es_principal
                            ? 'La sucursal Principal no se puede desactivar.'
                            : null),
                ])
                ->columns(2),

            Forms\Components\Section::make('Empleados')
                ->description('Quiénes pueden trabajar en esta sucursal. El administrador de la empresa ve todas.')
                ->schema([
                    Forms\Components\Select::make('usuarios')
                        ->label('Empleados asignados')
                        ->relationship(
                            'usuarios',
                            'name',
                            fn (Builder $query) => $query
                                ->where('users.empresa_id', $empresaId)
                                ->where('users.tipo_usuario', 'empleado')
                        )
                        ->multiple()
                        ->preload()
                        ->searchable(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nombre')->label('Nombre')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('codigo')->label('Código')->placeholder('—'),
                Tables\Columns\IconColumn::make('es_principal')->label('Principal')->boolean(),
                Tables\Columns\TextColumn::make('usuarios_count')->label('Empleados')->counts('usuarios'),
                Tables\Columns\IconColumn::make('activo')->label('Activa')->boolean(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->defaultSort('es_principal', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSucursales::route('/'),
            'create' => Pages\CreateSucursal::route('/create'),
            'edit' => Pages\EditSucursal::route('/{record}/edit'),
        ];
    }
}
