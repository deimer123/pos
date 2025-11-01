<?php
namespace App\Filament\Resources;

use App\Filament\Resources\ActorResource\Pages;
use App\Models\Actor;
use App\Models\TipoDocumento;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ActorResource extends Resource
{
    protected static ?string $model          = Actor::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?int $navigationSort    = 1;
    protected static ?string $label          = 'Actor';
    protected static ?string $pluralLabel    = 'Clientes y Proveedores';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Grid::make(1)->schema([
                Forms\Components\Hidden::make('id_clip_pro')
                    ->default(fn () => (Actor::max('id_clip_pro') ?? 10000) + 1)
                    ->dehydrated(),

                Forms\Components\Section::make('Clasificación')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('clasificacion')
                            ->label('Clasificación')
                            ->options([
                                'cliente'           => 'Cliente',
                                'proveedor'         => 'Proveedor',
                                'cliente_proveedor' => 'Cliente - Proveedor',
                            ])
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set) {
                                $set('tipo', match ($state) {
                                    'cliente'           => 3,
                                    'proveedor'         => 1,
                                    'cliente_proveedor' => 2,
                                    default             => null,
                                });
                            }),

                        Forms\Components\Hidden::make('tipo')
                            ->dehydrated()
                            ->required(),
                    ]),

                Forms\Components\Section::make('Identificación y Contacto')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('tipo_documento_id')
                            ->label('Tipo de documento')
                            ->options(fn () => TipoDocumento::query()->pluck('nombre', 'id'))
                            ->searchable()
                            ->required(),

                        Forms\Components\TextInput::make('identificacion')
                            ->label('Número de documento')
                            ->required(),

                        Forms\Components\TextInput::make('nombre')
                            ->label('Nombre')
                            ->required(),

                        Forms\Components\TextInput::make('razon_social')
                            ->label('Razón Social'),

                        Forms\Components\TextInput::make('direccion')
                            ->label('Dirección')
                            ->required(),

                        Forms\Components\TextInput::make('telefono')
                            ->label('Teléfono')
                            ->required(),

                        Forms\Components\TextInput::make('email')
                            ->label('Correo electrónico')
                            ->email(),
                    ]),

                Forms\Components\Section::make('Ubicación')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('departamento_id')
                            ->label('Departamento')
                            ->options(\App\Models\Departamento::pluck('nombre', 'id')->toArray())
                            ->searchable()
                            ->reactive()
                            ->required()
                            ->afterStateUpdated(fn (callable $set) => $set('ciudad_id', null)),

                        Forms\Components\Select::make('ciudad_id')
                            ->label('Ciudad')
                            ->options(fn (callable $get) =>
                                $get('departamento_id')
                                    ? \App\Models\Ciudad::where('departamento_id', $get('departamento_id'))->pluck('nombre', 'id')->toArray()
                                    : []
                            )
                            ->searchable()
                            ->required()
                            ->disabled(fn (callable $get) => empty($get('departamento_id')))
                            ->placeholder('Selecciona primero un departamento'),
                    ]),

                Forms\Components\Section::make('Información Tributaria')
                    ->columns(3)
                    ->schema([
                        Forms\Components\Select::make('tipo_persona')
                            ->label('Tipo de persona')
                            ->options([
                                'natural'  => 'Natural',
                                'juridica' => 'Jurídica',
                            ]),

                        Forms\Components\Select::make('regimen_tributario')
                            ->label('Régimen')
                            ->options([
                                'comun'        => 'Común',
                                'simplificado' => 'Simplificado',
                                'especial'     => 'Especial',
                                'otro'         => 'Otro',
                            ])
                            ->required(),

                        Forms\Components\Select::make('responsable_iva')
                            ->label('¿Responsable de IVA?')
                            ->options([
                                true  => 'Sí',
                                false => 'No',
                            ])
                            ->required(),
                    ]),

                // ======================
                //   CRÉDITO (solo admin_empresa y clientes)
                // ======================
                Forms\Components\Section::make('Crédito (solo admin empresa)')
                    ->columns(3)
                    ->visible(function (callable $get) {
                        $esCliente = in_array($get('clasificacion'), ['cliente', 'cliente_proveedor']);
                        $esAdminEmpresa = auth()->user()?->hasRole('admin_empresa') === true;
                        return $esCliente && $esAdminEmpresa;
                    })
                    ->schema([
                        Forms\Components\Toggle::make('permite_credito')
                            ->label('Permitir pago a crédito')
                            ->inline(false)
                            ->reactive()
                            ->default(false),

                        Forms\Components\TextInput::make('dias_credito')
                            ->label('Días de crédito')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->disabled(fn (callable $get) => ! $get('permite_credito'))
                            ->required(fn (callable $get) => (bool) $get('permite_credito')),

                        Forms\Components\TextInput::make('limite_credito')
                            ->label('Límite de crédito')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->prefix('$')
                            ->disabled(fn (callable $get) => ! $get('permite_credito'))
                            ->required(fn (callable $get) => (bool) $get('permite_credito')),
                    ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nombre')->label('Nombre')->searchable(),
                Tables\Columns\TextColumn::make('identificacion')->label('Identificación')->searchable(),
                Tables\Columns\TextColumn::make('clasificacion')
                    ->label('Clasificación')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'cliente'           => 'Cliente',
                        'proveedor'         => 'Proveedor',
                        'cliente_proveedor' => 'Cliente - Proveedor',
                        default             => $state
                    })
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'cliente'           => 'success',
                        'proveedor'         => 'info',
                        'cliente_proveedor' => 'warning',
                        default             => 'gray'
                    }),

                // Columnas de crédito SOLO visibles para admin_empresa
                Tables\Columns\IconColumn::make('permite_credito')
                    ->label('Crédito')
                    ->boolean()
                    ->visible(fn () => auth()->user()?->hasRole('admin_empresa') === true),

                

                Tables\Columns\TextColumn::make('email')->label('Email')->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('clasificacion')
                    ->label('Filtrar por Clasificación')
                    ->options([
                        'cliente'           => 'Solo Clientes',
                        'proveedor'         => 'Solo Proveedores',
                        'cliente_proveedor' => 'Cliente - Proveedor',
                    ]),
            ])
            ->actions([])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListActors::route('/'),
            'create' => Pages\CreateActor::route('/create'),
            'edit'   => Pages\EditActor::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereIn('clasificacion', ['cliente', 'proveedor', 'cliente_proveedor'])
            ->where('empresa_id', auth()->id());
    }

    public static function mutateFormDataBeforeCreate(array $data): array
    {
        $data['empresa_id'] = auth()->id();
        $data['tipo'] = match ($data['clasificacion']) {
            'cliente' => 3,
            'proveedor' => 1,
            'cliente_proveedor' => 2,
            default => 0,
        };

        $esCliente = in_array($data['clasificacion'] ?? null, ['cliente', 'cliente_proveedor']);
        $esAdminEmpresa = auth()->user()?->hasRole('admin_empresa') === true;

        // Si NO es admin_empresa, forzar sin crédito
        if (! $esAdminEmpresa || ! $esCliente) {
            $data['permite_credito'] = false;
            $data['dias_credito']    = 0;
            $data['limite_credito']  = 0;
        } else {
            $permite = (bool)($data['permite_credito'] ?? false);
            $data['permite_credito'] = $permite;
            $data['dias_credito']    = $permite ? (int)($data['dias_credito'] ?? 0) : 0;
            $data['limite_credito']  = $permite ? (float)($data['limite_credito'] ?? 0) : 0;
        }

        return $data;
    }

    public static function mutateFormDataBeforeSave(array $data): array
    {
        $data['tipo'] = match ($data['clasificacion']) {
            'cliente' => 3,
            'proveedor' => 1,
            'cliente_proveedor' => 2,
            default => 0,
        };

        $esCliente = in_array($data['clasificacion'] ?? null, ['cliente', 'cliente_proveedor']);
        $esAdminEmpresa = auth()->user()?->hasRole('admin_empresa') === true;

        if (! $esAdminEmpresa || ! $esCliente) {
            $data['permite_credito'] = false;
            $data['dias_credito']    = 0;
            $data['limite_credito']  = 0;
        } else {
            $permite = (bool)($data['permite_credito'] ?? false);
            $data['permite_credito'] = $permite;
            $data['dias_credito']    = $permite ? (int)($data['dias_credito'] ?? 0) : 0;
            $data['limite_credito']  = $permite ? (float)($data['limite_credito'] ?? 0) : 0;
        }

        return $data;
    }

    public static function shouldRegisterNavigation(): bool
    {
        // Ya ocultas el recurso a vendedores; mantenemos igual.
        return ! auth()->user()->hasRole('vendedor');
    }

    public static function canAccess(): bool
    {
        return ! auth()->user()->hasRole('vendedor');
    }
}
