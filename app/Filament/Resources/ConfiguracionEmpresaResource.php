<?php
// filepath: c:\laragon\www\posapp\app\Filament\Resources\ConfiguracionEmpresaResource.php

namespace App\Filament\Resources;

use App\Filament\Resources\ConfiguracionEmpresaResource\Pages;
use App\Models\ConfiguracionEmpresa;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ConfiguracionEmpresaResource extends Resource
{
    protected static ?string $model = ConfiguracionEmpresa::class;
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationLabel = 'Configurar Empresa';
    protected static ?string $modelLabel = 'Configuración de Empresa';
    protected static ?string $pluralModelLabel = 'Configuración de Empresa';
    protected static ?string $navigationGroup = 'Administración';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasAnyRole(['super_admin', 'admin_empresa']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Hidden::make('empresa_id')
                ->default(auth()->id()),

            Forms\Components\Section::make('Información de la Empresa')
                ->schema([
                    Forms\Components\TextInput::make('nombre_empresa')
                        ->label('Nombre de la Empresa')
                        ->required()
                        ->maxLength(255),
                        
                    Forms\Components\TextInput::make('representante_legal')
                        ->label('Representante Legal')
                        ->required()
                        ->maxLength(255),
                        
                    Forms\Components\TextInput::make('nit')
                        ->label('NIT')
                        ->required()
                        ->maxLength(20),
                        
                    Forms\Components\TextInput::make('telefono')
                        ->label('Teléfono')
                        ->tel()
                        ->maxLength(20),
                        
                    Forms\Components\Textarea::make('direccion')
                        ->label('Dirección')
                        ->maxLength(500),
                        
                    Forms\Components\TextInput::make('lema')
                        ->label('Lema')
                        ->maxLength(255),
                        
                    Forms\Components\FileUpload::make('logo')
                        ->label('Logo')
                        ->image()
                        ->directory('logos')        // se guarda como logos/archivo.jpg
                         ->disk('public')
                         ->visibility('public')
                        ->preserveFilenames()
                        ->maxSize(2048),
                ]),

            Forms\Components\Section::make('Modo de negocio')
                ->schema([
                    Forms\Components\Select::make('tipo_negocio')
                        ->label('Tipo de negocio')
                        ->options([
                            'tienda' => 'Tienda / Almacén',
                            'restaurante' => 'Restaurante',
                            'bar' => 'Bar',
                            'carniceria' => 'Carnicería',
                            'panaderia' => 'Panadería',
                            'farmacia' => 'Farmacia',
                            'servicios' => 'Servicios',
                            'mixto' => 'Mixto',
                            'otro' => 'Otro',
                        ])
                        ->required()
                        ->default('tienda'),

                    Forms\Components\Grid::make(2)
                        ->schema([
                            Forms\Components\Toggle::make('usa_mesas')
                                ->label('Usa mesas')
                                ->helperText('Actívalo solo si el negocio atiende por mesa.'),

                            Forms\Components\Toggle::make('usa_cocina')
                                ->label('Usa cocina')
                                ->helperText('Para negocios que envían pedidos a cocina.'),

                            Forms\Components\Toggle::make('usa_recetas')
                                ->label('Usa recetas')
                                ->helperText('Útil cuando un producto descuenta ingredientes.'),

                            Forms\Components\Toggle::make('usa_variantes')
                                ->label('Usa variantes')
                                ->helperText('Ejemplo: talla, color, sabor o presentación.'),

                            Forms\Components\Toggle::make('usa_peso')
                                ->label('Vende por peso')
                                ->helperText('Ideal para carnicería, fruver o productos a granel.'),

                            Forms\Components\Toggle::make('usa_servicios')
                                ->label('Vende servicios')
                                ->helperText('Para mano de obra, horas o servicios intangibles. El % de ganancia se configura en cada servicio, en el menú Servicios.'),

                            Forms\Components\Toggle::make('usa_domicilios')
                                ->label('Usa domicilios')
                                ->helperText('Activa el módulo de pedidos a domicilio y repartidores.'),

                            Forms\Components\Toggle::make('usa_taller')
                                ->label('Usa taller')
                                ->helperText('Activa el módulo de taller: órdenes de trabajo, vehículos y repuestos.'),

                            Forms\Components\Toggle::make('permite_stock_negativo')
                                ->label('Permitir stock negativo')
                                ->helperText('Deja vender aunque no haya inventario suficiente.'),

                            Forms\Components\Toggle::make('imprime_ticket')
                                ->label('Imprime ticket')
                                ->default(true),

                            Forms\Components\Toggle::make('mostrar_iva_separado')
                                ->label('Mostrar IVA separado')
                                ->helperText('Sirve para pantallas y reportes con IVA desglosado.'),
                        ]),
                ]),

            Forms\Components\Section::make('Facturación Electrónica')
                ->schema([
                    Forms\Components\TextInput::make('prefijo')
                        ->label('Prefijo')
                        ->maxLength(10),
                        
                    Forms\Components\TextInput::make('rango_desde')
                        ->label('Rango Desde')
                        ->numeric(),
                        
                    Forms\Components\TextInput::make('rango_hasta')
                        ->label('Rango Hasta')
                        ->numeric(),
                        
                    Forms\Components\TextInput::make('rango_actual')
                        ->label('Rango Actual')
                        ->numeric(),
                        
                    Forms\Components\TextInput::make('numero_resolucion')
                        ->label('Número de Resolución')
                        ->maxLength(50),
                        
                    Forms\Components\DatePicker::make('fecha_inicio')
                        ->label('Fecha Inicio'),
                        
                    Forms\Components\DatePicker::make('fecha_fin')
                        ->label('Fecha Fin'),
                        
                    Forms\Components\TextInput::make('llave')
                        ->label('Llave')
                        ->maxLength(255),
                        
                    Forms\Components\Toggle::make('expirado')
                        ->label('Expirado')
                        ->default(false),
                        
                    Forms\Components\Toggle::make('activo')
                        ->label('Activo')
                        ->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nombre_empresa')
                    ->label('Empresa')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('representante_legal')
                    ->label('Representante Legal')
                    ->searchable(),
                    
                Tables\Columns\TextColumn::make('nit')
                    ->label('NIT')
                    ->searchable(),
                    
                Tables\Columns\IconColumn::make('activo')
                    ->label('Activo')
                    ->boolean(),
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('activo')
                    ->label('Estado'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                // Sin bulk actions para evitar eliminar configuraciones
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListConfiguracionEmpresas::route('/'),
            'create' => Pages\CreateConfiguracionEmpresa::route('/create'),
            'edit' => Pages\EditConfiguracionEmpresa::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();
        
        // Si es super_admin, puede ver todas las configuraciones
        if ($user && $user->hasRole('super_admin')) {
            return parent::getEloquentQuery();
        }
        
        // Si no, solo las de su empresa
        return parent::getEloquentQuery()
            ->where('empresa_id', $user->id);
    }

    // ✅ Método corregido con la firma compatible
    public static function getUrl(string $name = 'index', array $parameters = [], bool $isAbsolute = true, ?string $panel = null, ?\Illuminate\Database\Eloquent\Model $tenant = null): string
    {
        $user = auth()->user();

        if ($user && $user->hasRole('super_admin')) {
            return parent::getUrl($name, $parameters, $isAbsolute, $panel, $tenant);
        }
        
        // Solo aplicar la lógica si el usuario está autenticado
        if ($user) {
            // Verificar si ya tiene configuración
            $configuracion = ConfiguracionEmpresa::where('empresa_id', $user->id)->first();
            
            // Si está pidiendo index o create y ya tiene configuración, ir al edit
            if (($name === 'index' || $name === 'create') && $configuracion) {
                return parent::getUrl('edit', ['record' => $configuracion->id], $isAbsolute, $panel, $tenant);
            }
            
            // Si está pidiendo index y NO tiene configuración, ir al create
            if ($name === 'index' && !$configuracion) {
                return parent::getUrl('create', [], $isAbsolute, $panel, $tenant);
            }
        }
        
        return parent::getUrl($name, $parameters, $isAbsolute, $panel, $tenant);
    }
}
