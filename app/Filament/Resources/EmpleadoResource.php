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
                    ->extraAttributes(['class' => 'combo-franja-azul'])
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->extraAttributes(['class' => 'producto-linea-1'])
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
                            ]),

                        Forms\Components\Grid::make(2)
                            ->extraAttributes(['class' => 'producto-linea-2'])
                            ->schema([
                                Forms\Components\TextInput::make('telefono')
                                    ->label('Teléfono')
                                    ->maxLength(255),

                                Forms\Components\Textarea::make('direccion')
                                    ->label('Dirección')
                                    ->maxLength(500),
                            ]),
                    ]),

                Forms\Components\Section::make('Acceso y Roles')
                    ->extraAttributes(['class' => 'combo-franja-azul'])
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->extraAttributes(['class' => 'producto-linea-1'])
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
                            ]),

                        Forms\Components\Group::make()
                            ->extraAttributes(['class' => 'producto-linea-2'])
                            ->schema([
                        Forms\Components\CheckboxList::make('roles')
                            ->label('Roles del Empleado')
                            ->options(function () {
                                $empresaId = auth()->user()->getEmpresaActualId();
                                $config = \App\Models\ConfiguracionEmpresa::where('empresa_id', $empresaId)->first();
                                $usaMesas  = $config?->usa_mesas;
                                $usaTaller = $config?->usa_taller;
                                $usaHotel  = $config?->usa_hotel;

                                // Un hotel maneja reservas, check-in/check-out, clientes y caja
                                // desde un solo rol: no tiene sentido mezclarlo con vendedor/
                                // digitador/cajero.
                                if ($usaHotel) {
                                    return ['recepcion' => 'Recepcionista'];
                                }

                                $opciones = [];

                                if (! $usaMesas) {
                                    $opciones['vendedor'] = 'Vendedor';
                                }

                                $opciones['digitador'] = 'Digitador';
                                $opciones['cajero']    = 'Cajero';

                                if ($usaMesas) {
                                    $opciones['mesero'] = 'Mesero';
                                    $opciones['cocina'] = 'Cocina';
                                }

                                if ($usaTaller) {
                                    $opciones['taller'] = 'Taller';
                                }

                                return $opciones;
                            })
                            ->descriptions(function () {
                                $empresaId = auth()->user()->getEmpresaActualId();
                                $config = \App\Models\ConfiguracionEmpresa::where('empresa_id', $empresaId)->first();
                                $usaMesas  = $config?->usa_mesas;
                                $usaTaller = $config?->usa_taller;
                                $usaHotel  = $config?->usa_hotel;

                                if ($usaHotel) {
                                    return ['recepcion' => 'Hace todo en el hotel (reservas, check-in/check-out, clientes, facturación) igual que el administrador, pero solo dentro del POS'];
                                }

                                $desc = [];

                                if (! $usaMesas) {
                                    $desc['vendedor'] = 'Puede realizar ventas y consultar productos';
                                }

                                $desc['digitador'] = 'Puede crear y editar productos, familias, etc.';
                                $desc['cajero']    = 'Puede realizar ventas y gestionar el caja';

                                if ($usaMesas) {
                                    $desc['mesero'] = 'Puede tomar órdenes en mesas y enviar a cocina';
                                    $desc['cocina'] = 'Solo accede a la pantalla de cocina para ver órdenes';
                                }

                                if ($usaTaller) {
                                    $desc['taller'] = 'Entra directo al panel de taller (órdenes de trabajo y mecánicos)';
                                }

                                return $desc;
                            })
                            ->required()
                            ->columns(1)
                            ->live()
                            ->afterStateHydrated(function (Forms\Components\CheckboxList $component, $state, $record) {
                                if ($record) {
                                    $component->state($record->roles->pluck('name')->toArray());
                                }
                            }),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->extraAttributes(['class' => 'producto-linea-1'])
                            ->schema([
                                Forms\Components\Toggle::make('activo')
                                    ->label('Usuario Activo')
                                    ->default(true),

                                Forms\Components\Hidden::make('tipo_usuario')
                                    ->default('empleado'),

                                Forms\Components\Hidden::make('empresa_id')
                                    ->default(fn () => auth()->user()->getEmpresaActualId()),
                            ]),
                    ]),

                Forms\Components\Section::make('Botones visibles en el POS')
                    ->description('Marca los botones que NO quieres que este empleado vea en el punto de venta. Admin Empresa siempre los ve todos.')
                    ->extraAttributes(['class' => 'combo-franja-azul'])
                    ->schema([
                        Forms\Components\CheckboxList::make('botones_ocultos_pos')
                            ->extraAttributes(['class' => 'producto-linea-2'])
                            ->label('Ocultar estos botones')
                            ->options([
                                'facturar' => 'Facturar',
                                'buscar_cliente' => 'Buscar Cliente',
                                'caja' => 'Abrir/Cerrar caja',
                                'editar' => 'Editar',
                                'mas_cliente' => '+ Cliente',
                                'cartera' => 'Cartera',
                                'entrada_salida' => 'Entrada/Salida',
                                'limpiar' => 'Limpiar',
                                'guardar' => 'Guardar',
                                'ver' => 'Ver',
                            ])
                            ->columns(2),
                    ]),

                Forms\Components\Section::make('Recursos visibles en el Admin')
                    ->description('Solo aplica a Digitador (es el unico rol, aparte de Admin Empresa, que entra al panel de administracion). Se listan todos los modulos operativos que aplican al tipo de negocio; marca los que NO quieres que este empleado vea. Los modulos contables/reportes y la gestion de empleados siguen siendo exclusivos de Admin Empresa. Admin Empresa siempre ve todo.')
                    ->extraAttributes(['class' => 'combo-franja-azul'])
                    ->visible(fn (Forms\Get $get) => in_array('digitador', $get('roles') ?? []))
                    ->schema([
                        Forms\Components\CheckboxList::make('recursos_ocultos_admin')
                            ->extraAttributes(['class' => 'producto-linea-2'])
                            ->label('Ocultar estos recursos')
                            ->options(function () {
                                $empresaId = auth()->user()->getEmpresaActualId();
                                $config = \App\Models\ConfiguracionEmpresa::where('empresa_id', $empresaId)->first();

                                // Todos los resources operativos/de catalogo que
                                // Digitador puede llegar a usar (se excluyen a
                                // proposito los contables/reportes financieros y
                                // Empleados/Configuracion de Empresa, que se
                                // quedan exclusivos de Admin Empresa). Marcar uno
                                // aca lo oculta aunque antes fuera visible por
                                // defecto -- los propios de un tipo de negocio
                                // solo aparecen en la lista si la empresa los
                                // tiene activados.
                                $opciones = [
                                    'productos' => 'Productos',
                                    'combos'    => 'Combos',
                                    'familias'  => 'Categorías',
                                    'subfamilias' => 'Subcategorías',
                                    'compras'   => 'Compras a Proveedor',
                                    'clientes'  => 'Clientes y Proveedores',
                                    'catalogo'  => 'Catálogo público',
                                    'ajustes_inventario' => 'Ajustes de Inventario',
                                    'notas_credito' => 'Notas Crédito',
                                    'kardex' => 'Kardex',
                                ];

                                if ($config?->usa_recetas) {
                                    $opciones['recetas'] = 'Recetas';
                                }

                                if ($config?->usa_servicios) {
                                    $opciones['servicios'] = 'Servicios';
                                }

                                if ($config?->usa_taller) {
                                    $opciones['mecanicos'] = 'Mecánicos';
                                }

                                if ($config?->usa_mesas) {
                                    $opciones['mesas'] = 'Mesas';
                                }

                                if ($config?->usa_hotel) {
                                    $opciones['habitaciones'] = 'Habitaciones';
                                }

                                return $opciones;
                            })
                            ->columns(2),
                    ]),
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
                            'taller' => 'Taller',
                            'recepcion' => 'Recepcionista',
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
                        'primary' => fn ($record) => $record->hasRole('recepcion'),
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
                    ->options(function () {
                        $empresaId = auth()->user()->getEmpresaActualId();
                        $config = \App\Models\ConfiguracionEmpresa::where('empresa_id', $empresaId)->first();
                        $usaMesas  = $config?->usa_mesas;
                        $usaTaller = $config?->usa_taller;
                        $usaHotel  = $config?->usa_hotel;

                        if ($usaHotel) {
                            return ['recepcion' => 'Recepcionista'];
                        }

                        $opciones = [];
                        if (! $usaMesas) {
                            $opciones['vendedor'] = 'Vendedor';
                        }
                        $opciones['digitador'] = 'Digitador';
                        $opciones['cajero']    = 'Cajero';
                        if ($usaMesas) {
                            $opciones['mesero'] = 'Mesero';
                            $opciones['cocina'] = 'Cocina';
                        }
                        if ($usaTaller) {
                            $opciones['taller'] = 'Taller';
                        }
                        return $opciones;
                    })
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
            ->role(['vendedor', 'digitador', 'cajero', 'mesero', 'cocina', 'taller', 'recepcion']);
    }

    // Solo ADMIN_EMPRESA puede acceder
    public static function canAccess(): bool
    {
        return auth()->user()->hasRole('admin_empresa');
    }
}
