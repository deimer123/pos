<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use App\Models\Actor;
use App\Models\Familia;
use App\Models\Subfamilia;
use App\Models\AlternateCode;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Tables\Columns\TextColumn;
use Filament\Notifications\Notification;
use Filament\Forms\Form; 
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\BelongsToSelect;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\Hidden; // ← importa Hidden
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\ComponentContainer;
use Filament\Forms\Concerns\HasRelationship;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use App\Filament\Resources\ProductResource;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;






class ProductResource extends Resource
{
    protected static ?string $model = Product::class;
    protected static ?string $navigationIcon = 'heroicon-o-cube';
    protected static ?int $navigationSort = 3;
    protected static ?string $pluralLabel = 'Productos';

    public static function form(Form $form): Form
    {
        return $form
        ->extraAttributes([
            'x-data'                          => '{}',
            'x-on:keydown.enter.prevent'     => 'if ($event.target.tagName !== "TEXTAREA") $event.preventDefault()',
        ])
        
        
 ->schema([

    Hidden::make('empresa_id')
    ->default(auth()->id())
    ->dehydrated(),
    Section::make('Información del producto')
        ->schema([
            // Fila 1
            Grid::make([
                'default' => 12,
                'sm' => 12,
            ])->schema([
                TextInput::make('id_producto')
    ->label('Código.')
    ->numeric()
    ->default(fn () => 
    (Product::where('empresa_id', auth()->id())->max('id_producto') ?? 10001) + 1 
)
    ->disabled(true)
    ->dehydrated()
    ->columnSpan(2),

                TextInput::make('descripcion_larga')
    ->label('Nombre del producto')
    ->required()
    ->maxLength(255)
    ->lazy()
    ->rule(function (callable $get) {
        return function ($attribute, $value, $fail) use ($get) {
            $idProducto = $get('id_producto');
            $empresaId = auth()->id();
            $query = \App\Models\Product::where('descripcion_larga', $value)
                ->where('empresa_id', $empresaId);
            if ($idProducto) {
                $query->where('id_producto', '<>', $idProducto);
            }
            if ($query->exists()) {
                $fail('❌ Ese nombre ya está registrado en tu empresa, por favor escoge otro.');
            }
        };
    })
    ->columnSpan(9),

                TextInput::make('existencias') // ✅ Solo lectura
        ->label('Existencias')
        ->disabled()
        ->dehydrated(false) // No se guarda, solo se muestra
                    ->columnSpan(1),
                    ]),

            // Fila 2
            Grid::make(3)->schema([
                Select::make('id_proveedor')
                    ->label('Proveedor')
                    ->searchable()
                    ->required()
                    ->options(function () {
        $empresaId = auth()->id();

        return \App\Models\Actor::where('tipo', 3) // solo proveedores
            ->where('empresa_id', $empresaId)
            ->pluck('nombre', 'id_clip_pro');
    })
                    ->native(false)
                    ->placeholder('Selecciona un proveedor')
                    ->columnSpan(2),

                FileUpload::make('foto')
    
    ->directory('form-foto')
    ->visibility('public')              
                    ->columnSpan(1),
            ]),

            // Fila 3
            Grid::make(3)->schema([
                Select::make('id_familia1')
                    ->label('Departamento')
                   ->options(function () {
        $userId = auth()->id();
        
        return Familia::where('empresa_id', $userId)->pluck('nombre', 'id');
    })
                    ->searchable()
                    ->reactive()
                    ->required()
                    ->afterStateUpdated(function (callable $set, $state) {
                        $set('departamento_id_temp', $state);
                    })
                   ->createOptionForm([
    TextInput::make('nombre')
        ->required()
        ->label('Nombre del departamento')
        
->rule(function () {
    return function ($attribute, $value, $fail) {
        $empresaId = auth()->id(); // ← Aquí defines la variable correctamente
        if (\App\Models\Familia::where('nombre', $value)
                ->where('empresa_id', $empresaId)
                ->exists()) {
            $fail('❌ Este nombre de departamento ya está registrado en tu empresa.');
        }
    };
}),
])
                    ->createOptionUsing(function (array $data) {
    $data['empresa_id'] = auth()->id();
return \App\Models\Familia::create($data)->id;
                    })
                    ->createOptionModalHeading('Crear Departamento'),

                BelongsToSelect::make('id_familia2')
                    ->label('Subfamilia')
                    ->relationship(
                        'subfamilia',
                        'nombre',
                        fn ($query, $get) => $query->where('id_familia1', $get('id_familia1'))
                    )
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->reactive()
                    ->disabled(fn ($get) => empty($get('id_familia1')))
                    ->required()
                    ->placeholder('Selecciona una Subfamilia')
                    ->createOptionAction(fn (Action $action) => $action->disabled(fn (callable $get) => empty($get('id_familia1'))))
                    ->createOptionForm([
    TextInput::make('nombre')
        ->label('Nombre de la subfamilia')
        ->required()
        ->rule(function () {
    return function ($attribute, $value, $fail) {
        $empresaId = auth()->id(); // ← Aquí defines la variable correctamente
        if (\App\Models\Subfamilia::where('nombre', $value)
                ->where('empresa_id', $empresaId)
                ->exists()) {
            $fail('❌ Ese nombre de subfamilia ya está registrado en tu empresa.');
        }
    };
}),
])
                    ->createOptionUsing(function (array $data, callable $get) {
    $idFamilia1 = $get('id_familia1');
    if (!$idFamilia1) {
        throw new \Exception('Selecciona un departamento antes de agregar una subfamilia.');
    }
    $data['id_familia1'] = $idFamilia1;
    $data['empresa_id'] = auth()->id();
    return \App\Models\Subfamilia::create($data)->id_familia2;
})
                    ->createOptionModalHeading('Crear Subfamilia'),

                    Select::make('id_unidad_de_medida')
                    ->label('Unidad de Medida')
                    ->options([
                        1 => 'Pieza (u)',
                        2 => 'Kilogramos (kg)',
                        3 => 'Litros (L)',
                        4 => 'Metros (m)',
                        5 => 'Horas (h)',
                    ])
                    ->default(1)
                    ->required(),
                    
            ]),

            
        ]),
    
          Section::make('Impuestos y precios')
    ->schema([
        Grid::make(4)->schema([ // ✅ Primera fila

            TextInput::make('precio_costo')
    ->label('Precio Costo')
    ->numeric()
    ->prefix(fn ($state) => '$ ' . number_format((float) $state, 0, ',', '.')) // ✅ solo en el prefix
    ->lazy()
    ->default(fn ($record) => $record?->precio_costo ?? 0)
    ->afterStateUpdated(function ($state, callable $get, callable $set) {
        $dcto = floatval($get('descuento_comercial')) / 100;
        $costo = floatval($state);
        $cDesc = round($costo * (1 - $dcto), 2);
        $set('precio_con_descuento', $cDesc);

        $iva = floatval($get('iva_venta')) / 100;
    $cIva = round($cDesc * (1 + $iva), 2); // ✅ Aquí está el cambio clave
    $set('costo_iva', $cIva);

        $util = floatval($get('utilidad1')) / 100;
        $set('precio_venta1', round($cIva * (1 + $util), 2));
    }),

            TextInput::make('descuento_comercial')
                ->label('% Descuento Comercial')
                ->numeric()
                ->suffix('%')
                ->required()
                ->lazy()
                ->default(fn ($record) => $record?->descuento_comercial ?? 0)
                ->afterStateUpdated(function ($state, callable $get, callable $set) {
                    $costo = floatval($get('precio_costo'));
                    $dcto  = floatval($state) / 100;
                    $cDesc = round($costo * (1 - $dcto), 2);
                    $set('precio_con_descuento', $cDesc);
                    $iva = floatval($get('iva_venta')) / 100;
    $cIva = round($cDesc * (1 + $iva), 2); // ✅ Aquí está el cambio clave
    $set('costo_iva', $cIva);
                    $util = floatval($get('utilidad1')) / 100;
                    $set('precio_venta1', round($cIva * (1 + $util), 2));
                }),

            TextInput::make('precio_con_descuento')
                ->label('Costo con Descuento')
                ->prefix(fn ($state) => '$ ' . number_format((float) $state, 0, ',', '.')) // ✅ solo en el prefix
                ->disabled()
                ->dehydrated(),

                Select::make('iva_compra')
                ->label('IVA Compra')
                ->required()
                ->options([
                    '0.00' => 'Excluido (0%)',
                    '5.00' => 'Gravado (5%)',
                    '16.00' => 'Gravado (16%)',
                    '19.00' => 'Gravado (19%)',
                    '8.00' => 'Impoconsumo (8%)',
                ])
                ->reactive()
                ->default('0.00')
                 ->dehydrateStateUsing(fn ($state) => (float) $state) // 👈 convierte al guardar
    ->afterStateHydrated(fn($state, $set) => $set('iva_venta', number_format(floatval($state), 2, '.', ''))), // ✅ Al editar, lo muestra correctamente
            
        ]),

        Grid::make(4)->schema([ // ✅ Segunda fila

            

            Select::make('iva_venta')
                ->label('IVA Venta')
                ->required()
                ->options([
                    '0.00' => 'Excluido (0%)',
                    '5.00' => 'Gravado (5%)',
                    '16.00' => 'Gravado (16%)',
                    '19.00' => 'Gravado (19%)',
                    '8.00' => 'Impoconsumo (8%)',
                ])
                ->reactive()
                ->default('19.00')
                ->dehydrateStateUsing(fn ($state) => (float) $state) // 👈 convierte al guardar
    ->afterStateHydrated(fn($state, $set) => $set('iva_venta', number_format(floatval($state), 2, '.', ''))) // ✅ Al editar, lo muestra correctamente
                ->afterStateUpdated(function (float $state, callable $get, callable $set) {
    $costo = floatval($get('precio_costo'));
    $dcto  = floatval($get('descuento_comercial')) / 100;
    $cDesc = round($costo * (1 - $dcto), 2);
    $set('precio_con_descuento', $cDesc);

    $iva = floatval($get('iva_venta')) / 100;
    $cIva = round($cDesc * (1 + $iva), 2); // ✅ Aquí está el cambio clave
    $set('costo_iva', $cIva);

    $util  = floatval($get('utilidad1')) / 100;
    $set('precio_venta1', round($cIva * (1 + $util), 2));

    logger('IVA Venta (guardado): ' . $get('iva_venta'));
}),
                TextInput::make('costo_iva')
                ->label('Costo + IVA Venta')
                ->prefix(fn ($state) => '$ ' . number_format((float) $state, 0, ',', '.')) // ✅ solo en el prefix
                ->disabled()
               ->dehydrated(true)
                ->default(fn ($record) => 
                    round(floatval($record?->precio_con_descuento ?? 0) * (1 + floatval($record?->iva_venta ?? 0)), 2)
                ),

            TextInput::make('utilidad1')
                ->label('Utilidad %')
                ->numeric()
                ->suffix('%')
                ->required()
                ->lazy()
                ->default(fn ($record) => $record?->utilidad1 ?? 0)
                ->afterStateUpdated(function ($state, callable $get, callable $set) {
                    $cIva = floatval($get('costo_iva'));
                    $util = floatval($state) / 100;
                    $set('precio_venta1', round($cIva * (1 + $util), 2));
                }),

     TextInput::make('precio_venta1')
    ->label('Precio Venta Final')
    ->numeric()
    ->required()
    ->prefix(fn ($state) => '$ ' . number_format((float) $state, 0, ',', '.')) // ✅ solo en el prefix
    ->lazy()
    ->default(fn ($record) => $record?->precio_venta1 ?? 0)
    ->helperText(function (callable $get) {
        $venta = floatval($get('precio_venta1'));
        $costo = floatval($get('costo_iva'));
        return $venta < $costo ? '⚠️ El precio de venta no puede ser menor que el costo + IVA.' : null;
    })
    ->afterStateUpdated(function ($state, callable $get, callable $set) {
        $cIva = floatval($get('costo_iva'));
        if ($cIva > 0 && $state >= $cIva) {
            $util = (($state - $cIva) / $cIva) * 100;
            $set('utilidad1', round($util, 2));
        }
    }),

        ]),
    ]),

      Grid::make(2)->schema([  
    Section::make('Precios Anteriores')
        ->schema([
            Placeholder::make('precio_costo_anterior')
                ->label('Precio Costo Anterior')
                ->content(fn ($record) =>
                    $record?->precio_costo_anterior
                        ? '$' . number_format($record->precio_costo_anterior, 2)
                        : '-'
                ),
            Placeholder::make('precio_venta_anterior')
                ->label('Precio Venta Anterior')
                ->content(fn ($record) =>
                    $record?->precio_venta_anterior
                        ? '$' . number_format($record->precio_venta_anterior, 2)
                        : '-'
                ),
        ])
        ->columnSpan(1), // ⚠️ clave para que se ajuste a la mitad

  Section::make('Códigos Alternos')
    ->schema([
 Repeater::make('alternateCodes')
    ->relationship('alternateCodes')
    ->label('Códigos Alternos')
    ->defaultItems(0)
    ->createItemButtonLabel('Agregar código alterno')
    ->schema([
        Hidden::make('id'), // ✅ capturamos el id del registro existente (null si es nuevo)

        TextInput::make('code')
            ->label('Código alterno')
            ->required()
            ->rule(function (callable $get) {   // ✅ regla que ignora el propio registro al editar
                return function ($attribute, $value, $fail) use ($get) {
                    $empresaId = auth()->id();
                    $currentId = $get('id'); // id del código alterno (null si es nuevo)

                    $existe = \App\Models\AlternateCode::where('empresa_id', $empresaId)
                        ->where('code', $value)
                        ->when($currentId, fn($q) => $q->where('id', '<>', $currentId))
                        ->exists();

                    if ($existe) {
                        $fail('❌ Este código alterno ya está registrado en tu empresa.');
                    }
                };
            })
            ->dehydrated(),
        
        Hidden::make('empresa_id')
            ->default(auth()->id())
            ->dehydrated(),
    ])
    ->mutateRelationshipDataBeforeCreateUsing(function (array $data): array {
        $data['empresa_id'] = auth()->id();
        return $data;
    })
    ->mutateRelationshipDataBeforeSaveUsing(function (array $data): array {
        $data['empresa_id'] = auth()->id();
        return $data;
    }),

        
    ])
        ->columnSpan(1), // ⚠️ igual aquí

       
])
            ]);
         
    }

    public static function table(Tables\Table $table): Tables\Table
{
    return $table
        ->columns([
            TextColumn::make('id_producto')
                ->searchable()
                ->label('Código'),

            

            TextColumn::make('descripcion_larga')
    ->label('Nombre del Producto')
    ->searchable(query: function (Builder $query, string $search): Builder {
        // Separar el término de búsqueda por espacios
        $fragments = preg_split('/\s+/', strtolower($search));

        return $query->where(function ($query) use ($fragments) {
            foreach ($fragments as $fragment) {
                $query->whereRaw('LOWER(descripcion_larga) LIKE ?', ["%{$fragment}%"]);
            }
        })
        ->orWhere('id_producto', 'like', "%{$search}%")
        ->orWhereHas('alternateCodes', function ($q) use ($search) {
            $q->where('code', 'like', "%{$search}%");
        });
    }),

            TextColumn::make('existencias')
                ->alignCenter(),
        ])

        ->defaultSort('id_producto', 'desc');
}

    

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/crear'),
            'edit' => Pages\EditProduct::route('/{record}/editar'),
        ];
    }
public static function getEloquentQuery(): Builder
{
    return parent::getEloquentQuery()
       ->where('empresa_id', auth()->id());
}

public static function mutateFormDataBeforeCreate(array $data): array
{
    $data['empresa_id'] = auth()->id();
    return $data;
}

public static function shouldRegisterNavigation(): bool
{
    // Solo mostrar si NO es vendedor
    return !auth()->user()->hasRole('vendedor');
}

public static function canAccess(): bool
{
    return !auth()->user()->hasRole('vendedor');
}
    
}




