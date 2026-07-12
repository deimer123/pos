<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use App\Models\CuentaContable;
use App\Models\Actor;
use App\Models\Mecanico;
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
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
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
use Filament\Forms\Get;
use Filament\Forms\Set;



class ProductResource extends Resource
{
    protected static ?string $model = Product::class;
    protected static ?string $navigationIcon = 'heroicon-o-cube';
    protected static ?int $navigationSort = 1;
    protected static ?string $pluralLabel = 'Productos';
    protected static ?string $navigationGroup = '📦 Inventario';

    public static function form(Form $form): Form
    {
        return $form
        ->extraAttributes([
            'x-data'                          => '{}',
            'x-on:keydown.enter.prevent'     => 'if ($event.target.tagName !== "TEXTAREA") $event.preventDefault()',
        ])
        
        
 ->schema([

    Hidden::make('empresa_id')
    ->default(auth()->user()->getEmpresaActualId())
    ->dehydrated(),

    Forms\Components\Tabs::make('ProductoTabs')
        ->columnSpanFull()
        ->tabs([

    Forms\Components\Tabs\Tab::make('Información, precios e impuestos')
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
    (Product::where('empresa_id', auth()->user()->getEmpresaActualId())->max('id_producto') ?? 10001) + 1 
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
            $empresaId = (int) auth()->user()->getEmpresaActualId();
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

            // Fila 2: Proveedor, Departamento, Subfamilia
           Grid::make(3)->schema([
    Select::make('id_proveedor')
        ->label('Proveedor')
        ->searchable()
        ->required()
        ->options(function () {
            $empresaId = auth()->user()->getEmpresaActualId();

            return \App\Models\Actor::query()
                ->where('empresa_id', $empresaId)
                ->whereIn('clasificacion', ['proveedor', 'cliente_proveedor']) // ✅ SOLO PROVEEDORES
                ->orderBy('nombre')
                ->pluck('nombre', 'id_clip_pro');
        })
                    ->native(false)
                    ->placeholder('Selecciona un proveedor'),

                Select::make('id_familia1')
    ->label('Departamento')
    ->options(function () {
        $empresaId = auth()->user()->getEmpresaActualId(); // ✅ CAMBIO CLAVE
        return Familia::where('empresa_id', $empresaId)->pluck('nombre', 'id');
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
        $empresaId = auth()->user()->getEmpresaActualId(); // ✅ CAMBIO CLAVE
        if (\App\Models\Familia::where('nombre', $value)
                ->where('empresa_id', $empresaId)
                ->exists()) {
            $fail('❌ Este nombre de departamento ya está registrado en tu empresa.');
        }
    };
}),
])
                    ->createOptionUsing(function (array $data) {
    $data['empresa_id'] = auth()->user()->getEmpresaActualId();
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
        $empresaId = auth()->user()->getEmpresaActualId(); // ✅
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
    $data['empresa_id'] = auth()->user()->getEmpresaActualId(); // ✅
    return \App\Models\Subfamilia::create($data)->id_familia2;
})
                    ->createOptionModalHeading('Crear Subfamilia'),
            ]),

            // Fila 3: Unidad de Medida, Cuenta contable, Foto
            Grid::make(3)->schema([
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
                    ->required()
                    ->helperText('Define la unidad para compras, inventario y kardex. No cambia la forma de vender en POS.'),

                Select::make('cuenta_contable_id')
                    ->label('Cuenta contable')
                    ->options(function () {
                        $empresaId = auth()->user()->getEmpresaActualId();

                        return CuentaContable::query()
                            ->where('empresa_id', $empresaId)
                            ->orderBy('codigo')
                            ->get()
                            ->mapWithKeys(fn ($c) => [$c->id => $c->codigo . ' - ' . $c->nombre])
                            ->toArray();
                    })
                    ->searchable()
                    ->preload()
                    ->placeholder('Sin asignar'),

                FileUpload::make('foto')
                    ->directory('form-foto')
                    ->visibility('public')
                    ->imagePreviewHeight('90')
                    ->panelLayout('compact'),
            ]),

        Grid::make(4)->schema([
    TextInput::make('precio_costo')
    ->label('Precio Costo')
    ->numeric()
    ->prefix(fn ($state) => '$ ' . number_format((float) $state, 0, ',', '.'))
    ->lazy()
    ->default(fn ($record) => $record?->precio_costo ?? 0)
    ->afterStateHydrated(function (Get $get, Set $set) {
        // 👉 se ejecuta al cargar el edit, calcula valores iniciales
        static::calcularValores($get, $set);
    })
    ->afterStateUpdated(fn ($state, Get $get, Set $set) => static::calcularValores($get, $set)),

    TextInput::make('descuento_comercial')
        ->label('% Descuento Comercial')
        ->numeric()
        ->suffix('%')
        ->required()
        ->lazy()
        ->default(fn ($record) => $record?->descuento_comercial ?? 0)
        
        ->afterStateUpdated(fn ($state, Get $get, Set $set) => static::calcularValores($get, $set)),

    TextInput::make('precio_con_descuento')
        ->label('Costo con Descuento')
        ->prefix(fn ($state) => '$ ' . number_format((float)$state, 0, ',', '.'))
        ->disabled()
       ->reactive()
        ->dehydrated(true),

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
                ->lazy()
                ->default('19.00')
                 ->dehydrateStateUsing(fn ($state) => (float) $state) // 👈 convierte al guardar
    ->afterStateHydrated(fn($state, $set) => $set('iva_venta', number_format(floatval($state), 2, '.', ''))), // ✅ Al editar, lo muestra correctamente

    Select::make('iva_venta')
        ->label('IVA Venta')
        ->required()
        ->options([
            '0.00'  => 'Excluido (0%)',
            '5.00'  => 'Gravado (5%)',
            '8.00'  => 'Gravado (8%)',
            '16.00' => 'Gravado (16%)',
            '19.00' => 'Gravado (19%)',
        ])
        ->default('19.00')
        ->reactive()
        ->dehydrateStateUsing(fn ($state) => (float)$state)
        ->afterStateHydrated(fn ($state, Set $set) => 
            $set('iva_venta', number_format((float)$state, 2, '.', ''))
        )
        ->afterStateUpdated(fn ($state, Get $get, Set $set) => static::calcularValores($get, $set)),

    TextInput::make('costo_iva')
        ->label('Costo + IVA Venta')
        ->prefix(fn ($state) => '$ ' . number_format((float)$state, 0, ',', '.'))
        ->disabled()
        ->reactive()        
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
        ->afterStateUpdated(fn ($state, Get $get, Set $set) => static::calcularValores($get, $set, true)),

    TextInput::make('precio_venta1')
        ->label('Precio Venta Final')
        ->numeric()
        ->required()
        ->prefix(fn ($state) => '$ ' . number_format((float)$state, 0, ',', '.'))
        ->lazy()
       
        ->default(fn ($record) => $record?->precio_venta1 ?? 0)
        ->helperText(function (callable $get) {
            $venta = (float)$get('precio_venta1');
            $costo = (float)$get('costo_iva');
            return $venta < $costo ? '⚠️ El precio de venta no puede ser menor que el costo + IVA.' : null;
        })
        ->afterStateUpdated(fn ($state, Get $get, Set $set) => static::calcularValores($get, $set)),

        ]),
        ]),

    Forms\Components\Tabs\Tab::make('Catálogo público')
        ->schema([
                Forms\Components\Toggle::make('mostrar_en_catalogo')
                    ->label('Mostrar en el catálogo público')
                    ->default(true)
                    ->helperText('Desactívalo si este producto no debe aparecer en la carta que ven tus clientes.'),

                Forms\Components\Textarea::make('descripcion_catalogo')
                    ->label('Descripción para el catálogo')
                    ->helperText('Texto de venta que ven tus clientes (distinto al nombre interno del producto). Déjalo vacío si no aplica.')
                    ->rows(2),
        ]),

    Forms\Components\Tabs\Tab::make('Modo de venta')
        ->schema([
                Grid::make(2)->schema([
                    Select::make('tipo_producto')
                        ->label('Tipo de producto')
                        ->options([
                            'producto' => 'Producto',
                            'servicio' => 'Servicio',
                            'insumo' => 'Insumo',
                            'combo' => 'Combo / Paquete',
                        ])
                        ->default('producto')
                        ->required()
                        ->live(),

                    Select::make('tipo_servicio')
                        ->label('Tipo de servicio')
                        ->options([
                            'propio'  => 'Propio (lo hace la empresa / sus mecánicos)',
                            'tercero' => 'Servicio de tercero (no es ganancia de la empresa)',
                        ])
                        ->helperText('Propio: la empresa se queda con el % de ganancia de este servicio. Tercero: el dinero no pertenece a la empresa, solo se cobra y se contabiliza aparte.')
                        ->visible(fn (Get $get) => $get('tipo_producto') === 'servicio')
                        ->required(fn (Get $get) => $get('tipo_producto') === 'servicio')
                        ->live(),

                    TextInput::make('porcentaje_empresa')
                        ->label('% de ganancia para la empresa')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(100)
                        ->suffix('%')
                        ->visible(fn (Get $get) => $get('tipo_producto') === 'servicio' && $get('tipo_servicio') === 'propio')
                        ->required(fn (Get $get) => $get('tipo_producto') === 'servicio' && $get('tipo_servicio') === 'propio')
                        ->helperText('Este porcentaje es propio de este servicio, puede variar de un servicio a otro.'),

                    Select::make('mecanico_id')
                        ->label('Mecánico asignado')
                        ->options(function () {
                            $empresaId = auth()->user()->getEmpresaActualId();
                            return Mecanico::where('empresa_id', $empresaId)
                                ->where('activo', true)
                                ->pluck('nombre', 'id');
                        })
                        ->searchable()
                        ->nullable()
                        ->placeholder('Sin mecánico asignado')
                        ->visible(fn (Get $get) => $get('tipo_producto') === 'servicio' && $get('tipo_servicio') === 'propio')
                        ->helperText('El mecánico que realiza este servicio. Se usará para la liquidación.'),

                    Forms\Components\TextInput::make('tercero_nombre')
                        ->label('Nombre del tercero / proveedor')
                        ->maxLength(150)
                        ->placeholder('Ej: Taller Pérez, Proveedor XYZ')
                        ->visible(fn (Get $get) => $get('tipo_producto') === 'servicio' && $get('tipo_servicio') === 'tercero')
                        ->helperText('Nombre de la empresa o persona a quien se le debe pagar este servicio.'),

                    Select::make('vende_por')
                        ->label('Vende por')
                        ->options([
                            'unidad' => 'Unidad',
                            'peso' => 'Peso',
                            'porcion' => 'Porción',
                            'litro' => 'Litro',
                            'metro' => 'Metro',
                            'hora' => 'Hora',
                        ])
                        ->default('unidad')
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (Set $set, $state) {
                            if (in_array($state, ['peso', 'porcion', 'litro', 'metro', 'hora'], true)) {
                                $set('permite_fraccion', true);
                                $set('maneja_inventario', true);
                            }

                            if ($state === 'servicio') {
                                $set('maneja_inventario', false);
                                $set('permite_fraccion', false);
                            }

                            if ($state === 'unidad') {
                                $set('permite_fraccion', false);
                            }
                        })
                        ->helperText('Define cómo se cobra en el POS. No modifica la unidad de medida del inventario.'),

                    Placeholder::make('modo_ayuda')
                        ->label('Modo de venta')
                        ->content('Unidad: venta normal. Peso o porción: cantidades decimales. Servicio: sin inventario. Esto ya afecta al POS.')
                        ->columnSpanFull(),

                    Forms\Components\Toggle::make('maneja_inventario')
                        ->label('Maneja inventario')
                        ->default(true),

                    Forms\Components\Toggle::make('permite_fraccion')
                        ->label('Permite fracción')
                        ->helperText('Para vender cantidades decimales como 0.25 o 0.5.'),

                    Forms\Components\Toggle::make('requiere_cocina')
                        ->label('Requiere cocina')
                        ->helperText('Marca el producto para flujo de cocina o preparación.'),

                    TextInput::make('peso_base')
                        ->label('Peso base')
                        ->numeric()
                        ->step('0.001')
                        ->helperText('Peso de referencia del producto cuando se vende por peso.')
                        ->default(null),
                ]),
        ]),

    Forms\Components\Tabs\Tab::make('Precios anteriores y códigos alternos')
        ->schema([
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
    ->grid(2)
    ->extraAttributes(['class' => 'codigos-alternos-repeater'])
    ->schema([
        Hidden::make('id'), // ✅ capturamos el id del registro existente (null si es nuevo)

        TextInput::make('code')
            ->hiddenLabel()
            ->placeholder('Código alterno')
            ->required()
            ->rule(function (callable $get) {   // ✅ regla que ignora el propio registro al editar
                return function ($attribute, $value, $fail) use ($get) {
                   $empresaId = (int) auth()->user()->getEmpresaActualId();
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
            ->default(fn () => auth()->user()->getEmpresaActualId())
            ->dehydrated(),
    ])
    ->mutateRelationshipDataBeforeCreateUsing(function (array $data): array {
       $data['empresa_id'] = auth()->user()->getEmpresaActualId();
        return $data;
    })
    ->mutateRelationshipDataBeforeSaveUsing(function (array $data): array {
        $data['empresa_id'] = auth()->user()->getEmpresaActualId();
        return $data;
    }),

        
    ])
        ->columnSpan(1), // ⚠️ igual aquí


])
        ]),

    ]),
            ]);

    }

    public static function table(Tables\Table $table): Tables\Table
{
    return $table
        ->defaultSort('id', 'desc')
        ->contentGrid([
            'sm' => 2,
            'md' => 3,
            'lg' => 4,
            'xl' => 5,
        ])
        ->columns([
            Stack::make([
                ImageColumn::make('foto')
                    ->label('')
                    ->disk('public')
                    ->height(90)
                    ->extraImgAttributes(['class' => 'w-full h-[90px] object-cover rounded-t-xl'])
                    ->defaultImageUrl(asset('images/sin-imagen.png')),

                Split::make([
                    TextColumn::make('existencias')
                        ->label('Stock')
                        ->sortable()
                        ->badge()
                        ->color(fn ($state) => match (true) {
                            $state < 0 => 'danger',
                            $state == 0 => 'warning',
                            $state > 0 => 'success',
                        }),

                    TextColumn::make('vende_por')
                        ->label('Venta')
                        ->badge()
                        ->formatStateUsing(fn ($state) => match ($state) {
                            'peso' => 'Peso',
                            'porcion' => 'Porción',
                            'litro' => 'Litro',
                            'metro' => 'Metro',
                            'hora' => 'Hora',
                            default => 'Unidad',
                        })
                        ->color(fn ($state) => match ($state) {
                            'peso', 'porcion', 'litro', 'metro', 'hora' => 'warning',
                            default => 'gray',
                        }),
                ])->extraAttributes(['class' => 'px-3 pt-2']),

                TextColumn::make('precio_venta1')
                    ->label('Precio')
                    ->formatStateUsing(fn ($state) => '$ ' . number_format((float) $state, 0, ',', '.'))
                    ->weight('bold')
                    ->color('success')
                    ->extraAttributes(['class' => 'px-3']),

                TextColumn::make('id_producto')
                    ->label('Código')
                    ->searchable()
                    ->badge()
                    ->color('primary')
                    ->weight('bold')
                    ->extraAttributes(['class' => 'px-3']),

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
                    })
                    ->weight('bold')
                    ->size('sm')
                    ->lineClamp(2)
                    ->extraAttributes(['class' => 'px-3 pb-2']),
            ])->space(1)->extraAttributes(['class' => 'producto-card']),
        ])
        ->paginated([25, 50, 100, 200])
        ->defaultPaginationPageOption(50)

        ->filters([

    // PROVEEDOR

    Tables\Filters\SelectFilter::make('id_proveedor')

        ->label('Proveedor')

        ->searchable()

        ->options(

            \App\Models\Actor::query()

                ->where('tipo', 3)

                ->whereNotNull('razon_social')

                ->where('empresa_id', auth()->user()->getEmpresaActualId())

                ->orderBy('razon_social')

                ->pluck('razon_social', 'id_clip_pro')

        ),




    // FAMILIA

    Tables\Filters\SelectFilter::make('id_familia1')

        ->label('Familia')

        ->searchable()

        ->options(

            \App\Models\Familia::query()

                ->where('empresa_id', auth()->user()->getEmpresaActualId())

                ->whereNotNull('nombre')

                ->orderBy('nombre')

                ->pluck('nombre', 'id')

        ),




    // SUBFAMILIA

    Tables\Filters\SelectFilter::make('id_familia2')

        ->label('Subfamilia')

        ->searchable()

        ->options(

            \App\Models\Subfamilia::query()

                ->where('empresa_id', auth()->user()->getEmpresaActualId())

                ->whereNotNull('nombre')

                ->orderBy('nombre')

                ->pluck('nombre', 'id_familia2')

        ),




    // STOCK NEGATIVO

    Tables\Filters\Filter::make('negativos')

        ->label('Stock Negativo')

        ->query(fn ($query) =>

            $query->where('existencias', '<', 0)
        ),




    // STOCK EN CERO

    Tables\Filters\Filter::make('cero')

        ->label('Stock en Cero')

        ->query(fn ($query) =>

            $query->where('existencias', 0)
        ),




    // STOCK POSITIVO

    Tables\Filters\Filter::make('positivos')

        ->label('Stock Positivo')

        ->query(fn ($query) =>

            $query->where('existencias', '>', 0)
        ),

]);

       
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
    $query = parent::getEloquentQuery();

    $user = auth()->user();

    // Si es admin_empresa → usa su propio ID como empresa_id
    if ($user->hasRole('admin_empresa')) {
        return $query->where('empresa_id', $user->id);
    }

    // Si es un empleado (digitador, vendedor, etc.) → usa empresa_id asignado
    if ($user->hasRole('digitador') || $user->hasRole('vendedor')) {
        return $query->where('empresa_id', $user->empresa_id);
    }

    // Si no tiene ningún rol válido → no mostrar nada
    return $query->whereRaw('1 = 0');
}


public static function mutateFormDataBeforeCreate(array $data): array
{
   $data['empresa_id'] = auth()->user()->getEmpresaActualId();
    return $data;
}

public static function shouldRegisterNavigation(): bool
{
    // Solo mostrar si NO es vendedor
    return !auth()->user()->hasRole('vendedor') && !auth()->user()->hasRole('super_admin');
}

public static function canAccess(): bool
{
    return !auth()->user()->hasRole('vendedor') && !auth()->user()->hasRole('super_admin');
}

protected static function calcularValores(Get $get, Set $set, bool $forzarUtilidad = false): void
{
    $costo = (float)$get('precio_costo');
    $desc  = (float)$get('descuento_comercial');
    $iva   = (float)$get('iva_venta');
    $util  = (float)$get('utilidad1');
    $venta = (float)$get('precio_venta1');

    // 1️⃣ Calcular costo con descuento e IVA
    $cDesc = round($costo * (1 - $desc / 100), 2);
    $set('precio_con_descuento', $cDesc);

    $cIva = round($cDesc * (1 + $iva / 100), 2);
    $set('costo_iva', $cIva);

    // 2️⃣ Si cambió la utilidad manualmente → recalcular PV
    if ($forzarUtilidad) {
        if ($util >= 100) {
            $set('precio_venta1', $cIva);
            return;
        }

        $pv = $cIva / (1 - ($util / 100));
        $set('precio_venta1', round($pv, 2));
        return;
    }

    // 3️⃣ Si cambió costo/desc/iva o PV → recalcular utilidad
    if ($venta > 0) {
        $utilReal = (($venta - $cIva) / $venta) * 100;
        $set('utilidad1', round($utilReal, 2));
    }
}

    
}
