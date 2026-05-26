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
        return auth()->check() && auth()->user()->hasRole('admin_empresa');
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
