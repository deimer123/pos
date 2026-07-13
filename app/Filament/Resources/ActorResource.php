<?php
namespace App\Filament\Resources;

use App\Filament\Resources\ActorResource\Pages;
use App\Models\Actor;
use App\Models\Ciudad;
use App\Models\Departamento;
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
    protected static ?int $navigationSort = 4;
    protected static ?string $label          = 'Actor';
    protected static ?string $pluralLabel    = 'Clientes y Proveedores';
    protected static ?string $navigationGroup = '👥 Terceros';

    public static function form(Form $form): Form
    {
        return $form->schema([

            Forms\Components\Hidden::make('empresa_id')
    ->default(auth()->user()->getEmpresaActualId())
    ->dehydrated(),

            Forms\Components\Grid::make(1)->schema([
                Forms\Components\Hidden::make('id_clip_pro')
                    ->default(fn () => (Actor::max('id_clip_pro') ?? 10000) + 1)
                    ->dehydrated(),

                Forms\Components\Hidden::make('tipo')
                    ->dehydrated()
                    ->required(),

                Forms\Components\Section::make('Identificación y Contacto')
                    ->extraAttributes(['class' => 'combo-franja-azul'])
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->extraAttributes(['class' => 'producto-linea-1'])
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
                                            'cliente'           => 1,
                                            'proveedor'         => 3,
                                            'cliente_proveedor' => 2,
                                            default             => null,
                                        });
                                    }),

                                Forms\Components\Select::make('tipo_documento_id')
                                    ->label('Tipo de documento')
                                    ->options(fn (): array => TipoDocumento::query()
                                        ->whereNotNull('nombre')
                                        ->orderBy('nombre')
                                        ->get()
                                        ->mapWithKeys(fn (TipoDocumento $tipo) => [
                                            $tipo->getKey() => (string) $tipo->nombre,
                                        ])
                                        ->all())
                                    ->searchable()
                                    ->preload()
                                    ->required(),

                                Forms\Components\TextInput::make('identificacion')
                                    ->label('Número de documento')
                                    ->required(),
                            ]),

                        Forms\Components\Grid::make(3)
                            ->extraAttributes(['class' => 'producto-linea-2'])
                            ->schema([
                                Forms\Components\TextInput::make('nombre')
                                    ->label('Nombre')
                                    ->required(),

                                Forms\Components\TextInput::make('razon_social')
                                    ->label('Razón Social'),

                                Forms\Components\TextInput::make('direccion')
                                    ->label('Dirección')
                                    ->required(),
                            ]),

                        Forms\Components\Grid::make(3)
                            ->extraAttributes(['class' => 'producto-linea-1'])
                            ->schema([
                                Forms\Components\TextInput::make('telefono')
                                    ->label('Teléfono')
                                    ->required(),

                                Forms\Components\TextInput::make('email')
                                    ->label('Correo electrónico')
                                    ->email(),
                            ]),
                    ]),

                Forms\Components\Section::make('Ubicación')
                    ->extraAttributes(['class' => 'combo-franja-azul'])
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->extraAttributes(['class' => 'producto-linea-2'])
                            ->schema([
                                Forms\Components\Select::make('departamento_id')
                                    ->label('Departamento')
                                    ->options(fn (): array => Departamento::query()
                                        ->whereNotNull('nombre')
                                        ->orderBy('nombre')
                                        ->get()
                                        ->mapWithKeys(fn (Departamento $departamento) => [
                                            $departamento->getKey() => (string) $departamento->nombre,
                                        ])
                                        ->all())
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->required()
                                    ->afterStateUpdated(fn (callable $set) => $set('ciudad_id', null)),

                                Forms\Components\Select::make('ciudad_id')
                                    ->label('Ciudad')
                                    ->options(fn (callable $get): array => Ciudad::query()
                                        ->where('departamento_id', $get('departamento_id'))
                                        ->whereNotNull('nombre')
                                        ->orderBy('nombre')
                                        ->get()
                                        ->mapWithKeys(fn (Ciudad $ciudad) => [
                                            $ciudad->getKey() => (string) $ciudad->nombre,
                                        ])
                                        ->all())
                                    ->searchable()
                                    ->live()
                                    ->required()
                                    ->disabled(fn (callable $get) => empty($get('departamento_id')))
                                    ->placeholder('Selecciona primero un departamento'),
                            ]),
                    ]),

                Forms\Components\Section::make('Información Tributaria')
                    ->extraAttributes(['class' => 'combo-franja-azul'])
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->extraAttributes(['class' => 'producto-linea-1'])
                            ->schema([
                                Forms\Components\Select::make('tipo_persona')
                                    ->label('Tipo de persona')
                                    ->options([
                                        'natural'  => 'Natural',
                                        'juridica' => 'Jurídica',
                                    ])
                                    ->default('natural'),

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
                                    ->boolean()
                                    ->required(),
                            ]),
                    ]),

                // ======================
                //   CRÉDITO (solo admin_empresa y clientes)
                // ======================
                Forms\Components\Section::make('Crédito')
                    ->extraAttributes(['class' => 'combo-franja-azul'])
                    ->visible(function (callable $get) {
                        $esCliente = in_array($get('clasificacion'), ['cliente', 'cliente_proveedor']);
                        $esAdminEmpresa = auth()->user()?->hasRole('admin_empresa') === true;
                        return $esCliente && $esAdminEmpresa;
                    })
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->extraAttributes(['class' => 'producto-linea-2'])
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
                                    ->lazy()
                                    ->prefix(fn ($state) => '$ ' . number_format((float) $state, 0, ',', '.'))
                                    ->disabled(fn (callable $get) => ! $get('permite_credito'))
                                    ->required(fn (callable $get) => (bool) $get('permite_credito')),
                            ]),
                    ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nombre')
                    ->label('Nombre')
                    ->searchable(),
                Tables\Columns\TextColumn::make('identificacion')
                    ->label('Identificación')
                    ->searchable(),
                Tables\Columns\TextColumn::make('tipoDocumento.nombre')
                    ->label('Tipo documento')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('departamento.nombre')
                    ->label('Departamento')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('ciudad.nombre')
                    ->label('Ciudad')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('razon_social')
                    ->label('Razon social')
                    ->toggleable(isToggledHiddenByDefault: true),
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
    $user = auth()->user();

    return parent::getEloquentQuery()
        ->whereIn('clasificacion', ['cliente', 'proveedor', 'cliente_proveedor'])
        ->where('empresa_id', $user->getEmpresaActualId());
}


    public static function mutateFormDataBeforeCreate(array $data): array
    {
        $data['empresa_id'] = auth()->user()->getEmpresaActualId();

        $data['tipo'] = match ($data['clasificacion']) {
            'cliente' => 1,
            'proveedor' => 3,
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
            'cliente' => 1,
            'proveedor' => 3,
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
        return ! auth()->user()->hasRole('vendedor') && ! auth()->user()->hasRole('super_admin');
    }

    public static function canAccess(): bool
    {
        return ! auth()->user()->hasRole('vendedor') && ! auth()->user()->hasRole('super_admin');
    }
}
