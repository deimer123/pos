<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmpleadoResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;

class EmpleadoResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'Empleados';
    protected static ?string $modelLabel = 'Empleado';
    protected static ?string $pluralModelLabel = 'Empleados';
    protected static ?string $navigationGroup = '👨‍💼 Administración';
    protected static ?int $navigationSort = 5; // Más bajo = aparece más arriba

    // Solo visible para ADMIN_EMPRESA
    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()->hasRole('admin_empresa');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información Personal')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre Completo')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('email')
                            ->label('Correo Electrónico')
                            ->email()
                            ->required()
                            ->unique(User::class, 'email', ignoreRecord: true)
                            ->validationMessages([
        'unique' => 'Este correo ya existe.',
    ])
                            ->maxLength(255),

                        Forms\Components\TextInput::make('telefono')
                            ->label('Teléfono')
                            ->maxLength(255),

                        Forms\Components\Textarea::make('direccion')
                            ->label('Dirección')
                            ->maxLength(500),
                            
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Acceso y Roles')
                    ->schema([
                        Forms\Components\TextInput::make('password')
                            ->label('Contraseña')
                            ->password()
                            ->required(fn (string $context): bool => $context === 'create')
                            ->minLength(8)
                            ->same('passwordConfirmation')
                            ->dehydrated(fn ($state) => filled($state))
                            ->dehydrateStateUsing(fn ($state) => Hash::make($state)),

                        Forms\Components\TextInput::make('passwordConfirmation')
                            ->label('Confirmar Contraseña')
                            ->password()
                            ->required(fn (string $context): bool => $context === 'create')
                            ->minLength(8)
                            ->dehydrated(false),

                        Forms\Components\CheckboxList::make('roles')
                            ->label('Roles del Empleado')
                            ->options([
                                'vendedor' => 'Vendedor',
                                'digitador' => 'Digitador',
                                'cajero' => 'Cajero',
                                'mesero' => 'Mesero',
                                'cocina' => 'Cocina',
                            ])
                            ->descriptions([
                                'vendedor' => 'Puede realizar ventas y consultar productos',
                                'digitador' => 'Puede crear y editar productos, familias, etc.',
                                'cajero' => 'Puede realizar ventas y gestionar el caja',
                                'mesero' => 'Puede tomar órdenes en mesas y enviar a cocina',
                                'cocina' => 'Solo accede a la pantalla de cocina para ver órdenes',
                            ])
                            ->required()
                            ->columns(1)
                            ->afterStateHydrated(function (Forms\Components\CheckboxList $component, $state, $record) {
                                if ($record) {
                                    $component->state($record->roles->pluck('name')->toArray());
                                }
                            }),

                        Forms\Components\Toggle::make('activo')
                            ->label('Usuario Activo')
                            ->default(true),

                        Forms\Components\Hidden::make('tipo_usuario')
                            ->default('empleado'),

                        Forms\Components\Hidden::make('empresa_id')
                            ->default(fn () => auth()->user()->getEmpresaActualId()),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('telefono')
                    ->label('Teléfono')
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('roles')
                    ->label('Roles')
                    ->formatStateUsing(function ($record) {
                        $roleLabels = [
                            'vendedor' => 'Vendedor',
                            'digitador' => 'Digitador',
                            'cajero' => 'Cajero',
                            'mesero' => 'Mesero',
                            'cocina' => 'Cocina',
                        ];
                        
                        return $record->roles
                            ->pluck('name')
                            ->map(fn($role) => $roleLabels[$role] ?? $role)
                            ->implode(', ') ?: 'Sin roles';
                    })
                    ->colors([
                        'success' => fn ($record) => $record->hasRole('vendedor'),
                        'warning' => fn ($record) => $record->hasRole('digitador'),
                        'info' => fn ($record) => $record->hasRole('cajero'),
                    ]),

                Tables\Columns\IconColumn::make('activo')
                    ->label('Estado')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha de Creación')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('activo')
                    ->label('Estado')
                    ->placeholder('Todos los estados')
                    ->trueLabel('Solo activos')
                    ->falseLabel('Solo inactivos'),

                Tables\Filters\SelectFilter::make('roles')
                    ->label('Rol')
                    ->options([
                        'vendedor' => 'Vendedor',
                        'digitador' => 'Digitador',
                        'cajero' => 'Cajero',
                        'mesero' => 'Mesero',
                        'cocina' => 'Cocina',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (filled($data['value'])) {
                            return $query->role($data['value']);
                        }
                        return $query;
                    }),
            ])
            ->actions([
                
            ])
            ->bulkActions([
                
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmpleados::route('/'),
            'create' => Pages\CreateEmpleado::route('/create'),
            'edit' => Pages\EditEmpleado::route('/{record}/edit'),
        ];
    }

    // Solo mostrar empleados de la empresa logueada
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('tipo_usuario', 'empleado')
            ->where('empresa_id', auth()->user()->getEmpresaActualId())
            ->role(['vendedor', 'digitador', 'cajero', 'mesero', 'cocina']);
    }

    // Solo ADMIN_EMPRESA puede acceder
    public static function canAccess(): bool
    {
        return auth()->user()->hasRole('admin_empresa');
    }
}
