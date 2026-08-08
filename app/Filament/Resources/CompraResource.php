<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CompraResource\Pages;
use App\Models\Actor;
use App\Models\Compra;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Forms\Components\{Card, Section, Select, DatePicker, Textarea, Placeholder, TextInput, Repeater};
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\{BadgeColumn, TextColumn};
use Illuminate\Validation\Rule;
use Filament\Forms\Components\View;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Filters\TernaryFilter;
use App\Filament\Resources\CompraResource\Pages\EditCompra;
use Illuminate\Validation\Rules\Unique;
use Illuminate\Support\Facades\DB;
use Filament\Infolists;
use Filament\Infolists\Components\{Section as InfoSection, Grid as InfoGrid, TextEntry, RepeatableEntry};
use App\Filament\Resources\CompraResource\Pages\ViewCompra;
use Filament\Tables\Actions\{Action, ActionGroup, EditAction};
use Illuminate\Database\Eloquent\Model;
use Filament\Notifications\Notification;
use App\Filament\Resources\CompraResource\RelationManagers;
use Carbon\Carbon;
use Filament\Tables\Filters\SelectFilter;
use Barryvdh\DomPDF\Facade\Pdf;
use Milon\Barcode\DNS1D;
use SimpleSoftwareIO\SimpleQrCode\Facades\QrCode;



class CompraResource extends Resource
{
    protected static ?string $model = Compra::class;
    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?string $navigationLabel = 'Compras a Proveedor';
   public static function getNavigationGroup(): ?string
{
    $user = auth()->user();

    if (
        $user->hasRole('digitador') &&
        ! $user->hasRole('admin_empresa')
    ) {
        return '🚚 Compras';
    }

    return '⚙️ Operaciones';
}
    protected static ?int $navigationSort = 2;

    protected static bool $filtroInicialAplicado = false;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && ! auth()->user()->hasRole('super_admin')
            && auth()->user()->puedeVerResource('compras');
    }

    public static function canAccess(): bool
    {
        return auth()->check() && ! auth()->user()->hasRole('super_admin')
            && auth()->user()->puedeVerResource('compras');
    }

    /** FORM (Filament v3) */
    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->statePath('data')
            ->schema([

                Section::make('Datos de la compra')
                    ->model(\App\Models\Compra::class)
                    ->extraAttributes(['class' => 'compra-franja-azul'])
                    ->schema([
                        Grid::make(12)->extraAttributes(['class' => 'compra-datos-linea-1'])->schema([

                                                /* ================== PROVEEDOR ================== */
                                                Select::make('proveedor_id')
    ->label('Proveedor')
    ->options(fn () => Actor::query()
        ->where('empresa_id', auth()->user()->getEmpresaActualId())
        ->whereIn('clasificacion', ['proveedor', 'cliente_proveedor'])
        ->whereNotNull('nombre')
        ->orderBy('nombre')
        ->pluck('nombre', 'id')
    )
    // 👇 Muestra la etiqueta incluso si el proveedor no aparece en options()
    ->getOptionLabelUsing(fn ($value) =>
        $value ? Actor::where('empresa_id', auth()->user()->getEmpresaActualId())
            ->where('id', $value)
            ->value('nombre') : null
    )
    ->searchable()
    ->preload()
    ->required()
    ->validationMessages(['required' => 'Selecciona un proveedor.'])
    // que siempre se hidrate y se guarde como entero
    ->dehydrated(true)
    ->dehydrateStateUsing(fn ($v) => filled($v) ? (int) $v : null)
    ->live()
    // 🔒 bloqueos:
    // - en EDIT (detección por record en la ruta)
    // - si hay items ya cargados en CREATE
    // - si la compra está ANULADA
    ->disabled(function (Get $get) {
        $anulada   = static::compraEstaAnulada($get);
        $esEdit    = (bool) request()->route('record');
        $tieneRows = static::repeaterTieneItems($get); // tu helper existente
        return $anulada || $esEdit || $tieneRows;
    })
    ->helperText(function (Get $get) {
        if (static::compraEstaAnulada($get)) {
            return 'Compra anulada: solo lectura.';
        }
        if ((bool) request()->route('record')) {
            return 'No es posible cambiar el proveedor durante la edición.';
        }
        if (static::repeaterTieneItems($get)) {
            return 'Para cambiar el proveedor, elimina primero los ítems del detalle.';
        }
        return null;
    })
    ->columnSpan(4),

                                                // Número de factura / compra
                                                TextInput::make('numero_factura')
                        ->label('N° factura / compra')
                        ->placeholder('Ej: A-12345')
                        ->maxLength(50)                        
                        ->nullable()
                        ->unique(
                            table: 'compras',
                            column: 'numero_factura',
                            ignorable: fn (?Compra $record) => $record, // ignora el propio registro en edición
                            modifyRuleUsing: function (Unique $rule) {
                                // Tomamos el proveedor elegido desde el estado de Filament (request->data.*)
                                $prov = (int) data_get(request()->all(), 'data.proveedor_id');

                                return $rule
                                    ->where('empresa_id', auth()->user()->getEmpresaActualId())
                                    ->where('proveedor_id', $prov ?: 0); // único por empresa+proveedor
                            }
                        )
                        ->validationAttribute('número de factura')
                        ->required()
                        ->columnSpan(4),

                                                Select::make('tipo_pago')
                                                    ->label('Tipo pago')
                                                    ->options([
                                                        'contado' => 'Contado',
                                                        'credito' => 'Crédito',
                                                    ])
                                                    ->default('credito')
                                                    ->required()
                                                    ->live()
                                                    ->afterStateUpdated(function ($state, Get $get, Set $set) {
                                                        if ($state === 'contado') {
                                                            $set('fecha_vencimiento', null);
                                                        } else {
                                                            $fecha = $get('fecha');
                                                            if ($fecha) $set('fecha_vencimiento', $fecha);
                                                        }
                                                    })
                                                    ->columnSpan(4),
                        ]),

                        Grid::make(12)->extraAttributes(['class' => 'compra-datos-linea-2'])->schema([

                                                DatePicker::make('fecha')
                                                    ->label('Fecha')
                                                    ->required()
                                                    ->native(false)
                                                    ->displayFormat('d/m/Y')
                                                    ->live()
                                                    ->afterStateUpdated(function ($state, Get $get, Set $set) {
                                                        if ($get('tipo_pago') === 'credito' && $state) {
                                                            $set('fecha_vencimiento', $state);
                                                        }
                                                    })
                                                    ->columnSpan(4),

                                                DatePicker::make('fecha_vencimiento')
                                                    ->label('Vence')
                                                    ->native(false)
                                                    ->displayFormat('d/m/Y')
                                                    ->visible(fn (Get $get) => $get('tipo_pago') === 'credito')
                                                    ->required(fn (Get $get) => $get('tipo_pago') === 'credito')
                                                    ->columnSpan(4),
                        ]),
                    ])

                      ->disabled(fn (?Compra $record) => $record?->estado === 'anulada')

                    ->collapsible(),

                /* ======================== ÍTEMS ========================= */
                Section::make('Ítems')
                    ->extraAttributes(['class' => 'compra-franja-azul'])
                    ->schema([
                 Repeater::make('detalles')
    ->relationship()
    ->extraAttributes(['class' => 'compra-detalles-repeater'])
    // 🔒 en ANULADA: inputs deshabilitados y sin agregar/eliminar
    ->disabled(fn (Get $get) => static::compraEstaAnulada($get))
    ->addable(fn (Get $get) => ! static::compraEstaAnulada($get))
    ->deletable(fn (Get $get) => ! static::compraEstaAnulada($get))
    ->reorderable(false)                  
    ->live()
   ->afterStateHydrated(function (?array $state, \Filament\Forms\Set $set, \Filament\Forms\Get $get) {
    if (!is_array($state)) return;

    foreach ($state as $i => $row) {

        if ($row instanceof \Illuminate\Database\Eloquent\Model) {
            $row = $row->toArray();
        }
        $row = is_array($row) ? $row : [];

        // Normalizar numéricos
        $row['cantidad']         = (float)($row['cantidad'] ?? 1);
        $row['costo_unitario']   = (float)($row['costo_unitario'] ?? 0);
        $row['desc_comercial']   = (float)($row['desc_comercial'] ?? 0);
        $row['iva_pct']          = (float)($row['iva_pct'] ?? 0);
        $row['precio_venta']     = (float)($row['precio_venta'] ?? 0);
        $row['precio_venta_act'] = (float)($row['precio_venta_act'] ?? 0);
        $row['utilidad_pct']     = array_key_exists('utilidad_pct', $row) ? (float)$row['utilidad_pct'] : null;

        // Calcular utilidad si no viene
        if ($row['utilidad_pct'] === null) {
            $cDesc = max(0, $row['costo_unitario'] * (1 - ($row['desc_comercial'] / 100)));
            $cIva  = $cDesc * (1 + ($row['iva_pct'] / 100));
            $pvRef = $row['precio_venta'] > 0 ? $row['precio_venta'] : $row['precio_venta_act'];
            $row['utilidad_pct'] = ($cIva > 0 && $pvRef > 0)
                ? round((($pvRef - $cIva) / $cIva) * 100, 2)
                : 0.0;
        }

        // Recalcular línea
        $calc = \App\Filament\Resources\CompraResource::serverRecalcLinea($row);

        foreach (['subtotal','impuesto','total','costo_con_desc_input','costo_mas_iva_input','total_linea_input'] as $f) {
            $set("detalles.$i.$f", $calc[$f] ?? null);
        }

        $set("detalles.$i.iva_pct", number_format((float)$row['iva_pct'], 2, '.', ''));
        $set("detalles.$i.precio_venta_act", (float)$row['precio_venta_act']);
        $set("detalles.$i.utilidad_pct", (float)$row['utilidad_pct']);

        /*
        ───────────────────────────────────────────────────────────
        ✅ AQUÍ VIENE LA CORRECCIÓN IMPORTANTE
        Convertir product_id → ID REAL (no id_producto)
        ───────────────────────────────────────────────────────────
        */
        $rawCodigo = $row['product_id'] ?? $row['codigo_ingresado'] ?? null;

        if (!empty($rawCodigo)) {
            $producto = \App\Models\Product::where(function($q) use ($rawCodigo) {
                    $q->where('id_producto', (string)$rawCodigo)
                      ->orWhere('id', (int)$rawCodigo);
                })
                ->where('empresa_id', auth()->user()->getEmpresaActualId())
                ->first();

            if ($producto) {
                // Guardamos ambos
                $set("detalles.$i.product_id", (int)$producto->id); // ID REAL
                $set("detalles.$i.codigo_ingresado", (string)$producto->id_producto); // Código
                $row['product_id'] = (int)$producto->id;
            }
        }

        // Mostrar existencias reales
        $pid = (int)($row['product_id'] ?? 0);
        $exist = \App\Models\Product::stock($pid);
        $set("detalles.$i.existencias_actuales", (int)$exist);

        if (!empty($producto)) {
            $set("detalles.$i.unidad_medida_label", static::unidadMedidaLabel((int) ($producto->id_unidad_de_medida ?? 1)));
            $set("detalles.$i.modo_venta_label", static::modoVentaLabel((string) ($producto->vende_por ?? 'unidad')));
            $set("detalles.$i.permite_decimal_cantidad", $producto->permiteCantidadDecimal());
        }

    }
})


    ->schema([

        /* ------- Línea 1: Código + Nombre ------- */
        Forms\Components\Grid::make(14)->extraAttributes(['class' => 'compra-linea-1'])->schema([

         TextInput::make('codigo_ingresado')
    ->label('Código')
    ->placeholder('Escribe el código')
    ->required()
    ->validationMessages([
        'required' => 'Debe seleccionar un producto.',
    ])
    ->lazy()
    ->live(onBlur: true)

    // 👇 Corrige valores numéricos al abrir EDIT o hidratar el repeater
    ->afterStateHydrated(function ($state, \Filament\Forms\Set $set) {
        // Convierte cualquier número a string limpio
        if (is_numeric($state)) {
            $set('codigo_ingresado', (string) $state);
        } elseif (is_null($state)) {
            $set('codigo_ingresado', '');
        } else {
            $set('codigo_ingresado', trim((string) $state));
        }
    })

    // 👇 Corrige antes de guardar / refrescar Livewire
    ->dehydrateStateUsing(fn ($state) => trim((string) $state))

    // 👇 Evita validación tipo strict: nullable y string solo si no es vacío
    ->rules(['nullable', 'max:50'])
    ->formatStateUsing(fn ($state) => (string) $state)
    


    // ✅ Duplicado por PRODUCTO (principal o alterno). Solo marca la fila repetida, no la primera.
    ->rule(fn (Get $get) => function (string $attribute, $value, $fail) use ($get) {
        $code = trim((string) $value);
        if ($code === '') return;

        // índice de la fila actual
        $idx = null;
        if (preg_match('/detalles\.(\d+)\./', $attribute, $m)) {
            $idx = (int) $m[1];
        }

        $empresaId = (int) auth()->user()->getEmpresaActualId();
        $provClip  = (int) (static::proveedorClip($get) ?? 0);
        if (! $provClip) return;

        // product_id resuelto del código ingresado (principal o alterno)
        $currentPid = static::productIdByCode($empresaId, $provClip, $code);

        $rows = (array) ($get('../../detalles') ?? []);
        $dupesIdx = [];

        foreach ($rows as $i => $r) {
            $rPid  = (int) ($r['product_id'] ?? 0);
            $rCode = trim((string) ($r['codigo_ingresado'] ?? ''));

            $same = false;

            // Si ambas filas tienen product_id, compara por id
            if ($currentPid && $rPid && $rPid === $currentPid) {
                $same = true;

            // Si la otra fila no tiene product_id aún, intenta resolverlo por su código
            } elseif ($currentPid && ! $rPid && $rCode !== '') {
                $otherPid = static::productIdByCode($empresaId, $provClip, $rCode);
                if ($otherPid && $otherPid === $currentPid) {
                    $same = true;
                }

            // Fallback por texto (por si aún no resolvió pid)
            } elseif ($rCode !== '' && strcasecmp($rCode, $code) === 0) {
                $same = true;
            }

            if ($same) {
                $dupesIdx[] = (int) $i;
            }
        }

        // Si hay más de una coincidencia, solo falla en las repeticiones
        if (count($dupesIdx) > 1) {
            $first = $dupesIdx[0];
            if ($idx === null || $idx !== $first) {
                $fail('Este producto ya está en la lista.');
            }
        }
    })
    ->validationMessages([
        'required' => 'Digite un código',
    ])

    // Autocompletar (usa productIdByCode para que coincida lo que valida)
    ->afterStateUpdated(function ($state, Get $get, Set $set) {
        $codigo = trim((string) $state);
        if ($codigo === '') return;

        $empresaId = (int) auth()->user()->getEmpresaActualId();
        $provClip  = (int) (static::proveedorClip($get) ?? 0);
        if (! $provClip) return;

        // Evita autopoblar si YA existe ese producto en otra fila
        $pidFromCode = static::productIdByCode($empresaId, $provClip, $codigo);
        if (! $pidFromCode) return;

        $rows = (array) ($get('../../detalles') ?? []);
        $seen = 0;
        foreach ($rows as $r) {
            $rPid  = (int) ($r['product_id'] ?? 0);
            $rCode = trim((string) ($r['codigo_ingresado'] ?? ''));

            $same = false;
            if ($rPid && $rPid === $pidFromCode) {
                $same = true;
            } elseif ($rCode !== '') {
                $otherPid = static::productIdByCode($empresaId, $provClip, $rCode);
                if ($otherPid && $otherPid === $pidFromCode) $same = true;
            }
            if ($same) {
                $seen++;
                if ($seen > 1) return; // ya estaba → no autopoblar
            }
        }

        // Carga el producto por id (consistente con la validación)
        $p = \App\Models\Product::select([
            'id','id_producto','descripcion_larga',
            'precio_costo','descuento_comercial','iva_compra',
            'precio_venta1','utilidad1','id_unidad_de_medida',
            'vende_por','permite_fraccion'
        ])->find($pidFromCode);

        if (! $p) return;

        // Relleno de la fila
        $set('codigo_ingresado', (string) ($p->id_producto ?? $codigo)); // normaliza al principal si quieres
        $set('product_id',       $p->id);
        $set('nombre_producto',  $p->descripcion_larga ?? '');
        $set('costo_unitario',   (float)($p->precio_costo ?? 0));
        $set('desc_comercial',   (float)($p->descuento_comercial ?? 0));
        $set('iva_pct',          number_format((float)($p->iva_compra ?? 0), 2, '.', ''));
        $set('precio_venta_act', (float)($p->precio_venta1 ?? 0));

        $util = (float)($p->utilidad1 ?? 0);
        $set('utilidad_pct', $util);

        $costo = (float)($p->precio_costo ?? 0);
        $desc  = (float)($p->descuento_comercial ?? 0);
        $iva   = (float)($p->iva_compra ?? 0);
        $cDesc = max(0, $costo * (1 - $desc / 100));
        $cIva  = $cDesc * (1 + $iva / 100);
        $set('precio_venta', round($cIva * (1 + $util / 100), 2));

        $set('existencias_actuales', \App\Filament\Resources\CompraResource::stockActual($p->id));
        $set('unidad_medida_label', static::unidadMedidaLabel((int) ($p->id_unidad_de_medida ?? 1)));
        $set('modo_venta_label', static::modoVentaLabel((string) ($p->vende_por ?? 'unidad')));
        $set('permite_decimal_cantidad', $p->permiteCantidadDecimal());
        $set('producto_variante_id', null);
        static::recalcLinea($get, $set);
    })

    // Búsqueda con modal (respeta anti-duplicado y setea existencias)
  ->suffixAction(
    \Filament\Forms\Components\Actions\Action::make('abrir-modal-buscar')
        ->icon('heroicon-m-magnifying-glass')
        ->disabled(fn (Get $get) => ! (static::proveedorClip($get) ?? 0) || static::compraEstaAnulada($get))
        ->modalHeading('Buscar producto')
        ->modalWidth('4xl')
        ->closeModalByClickingAway(false)
        ->form(function (Get $get) {
            $empresaId = (int) auth()->user()->getEmpresaActualId();
            $provClip  = (int) (static::proveedorClip($get) ?? 0);
            $rowsSnapshot = (array) ($get('../../detalles') ?? []);

            return [
                Forms\Components\Hidden::make('empresa_ctx')->default($empresaId),
                Forms\Components\Hidden::make('provclip_ctx')->default($provClip),
                Forms\Components\Hidden::make('rows_snapshot')->default($rowsSnapshot),

                // Encabezado de tabla
                Forms\Components\View::make('filament.custom.producto-encabezado'),

                Forms\Components\Select::make('producto_id')
                    ->label(false)
                    ->searchable()
                    ->preload()
                    ->required()
                    ->reactive()
                    ->allowHtml()

                    // LISTADO INICIAL
                    ->options(function (Get $get): array {
    $empresaId = (int) ($get('empresa_ctx') ?? auth()->user()->getEmpresaActualId());
    $provClip  = (int) ($get('provclip_ctx') ?? 0);

    // 🟡 OBTENEMOS LOS PRODUCTOS YA AGREGADOS EN EL REPEATER
    $rows = (array) ($get('rows_snapshot') ?? []);
    $yaEstan = collect($rows)
        ->map(function ($r) {
            // Normaliza si viene como modelo o array
            if (is_array($r)) return (int) ($r['product_id'] ?? 0);
            if ($r instanceof \Illuminate\Database\Eloquent\Model) return (int) $r->product_id;
            return 0;
        })
        ->filter()
        ->unique()
        ->values()
        ->toArray();

    $q = \App\Models\Product::query()
        ->select(['id','id_producto','descripcion_larga','existencias'])
        ->where('empresa_id', $empresaId);

    // ✅ Filtrar por proveedor si corresponde
    if ($provClip > 0) {
        $q->where('id_proveedor', $provClip);
    }

    // ✅ EXCLUIR PRODUCTOS YA AGREGADOS EN CREATE Y EDIT
    if (!empty($yaEstan)) {
        $q->whereNotIn('id', $yaEstan);
    }

    return $q->orderBy('descripcion_larga')
        ->limit(100)
        ->get()
        ->mapWithKeys(fn ($p) => [
            $p->id => view('filament.custom.producto-opcion', [
                'exist' => $p->existencias,
                'codigo' => $p->id_producto,
                'nombre' => $p->descripcion_larga,
            ])->render(),
        ])
        ->toArray();
                    })

                  ->getSearchResultsUsing(function (string $search, Get $get): array {
    $empresaId = (int) ($get('empresa_ctx') ?? auth()->user()->getEmpresaActualId());
    $provClip  = (int) ($get('provclip_ctx') ?? 0);

    $search = trim($search ?? '');
    if (mb_strlen($search) < 2) {
        return [];
    }

    $rows = (array) ($get('rows_snapshot') ?? []);
    $yaEstan = collect($rows)
        ->pluck('product_id')
        ->filter()
        ->map(fn ($v) => (int) $v)
        ->values()
        ->all();

    $like = '%' . str_replace(['%','_'], ['\%','\_'], $search) . '%';

    $q = \App\Models\Product::query()
        ->select(['products.id','products.id_producto','products.descripcion_larga','products.existencias'])
        ->where('products.empresa_id', $empresaId);

    if ($provClip > 0) {
        $q->where('products.id_proveedor', $provClip);
    }

    if (!empty($yaEstan)) {
        $q->whereNotIn('products.id', $yaEstan);
    }

    // ✅ Filtrar por coincidencia parcial (descripcion, código, alternos)
   $q->where(function ($query) use ($search) {
        $like = '%' . str_replace(' ', '%', $search) . '%';
        $query->where('products.descripcion_larga', 'like', $like)
              ->orWhere('products.id_producto', 'like', $like)
              ->orWhereExists(function ($sub) use ($like) {
                  $sub->from('alternate_codes as ac')
                      ->whereColumn('ac.product_id', 'products.id')
                      ->where('ac.code', 'like', $like);
              });
    });

    return $q->orderBy('products.descripcion_larga')
             ->limit(50)
             ->get()
             ->mapWithKeys(function ($p) {
                 $exist = number_format((int)($p->existencias ?? 0), 0, ',', '.');
                 return [
                     $p->id => view('filament.custom.producto-opcion', [
                         'codigo' => $p->id_producto,
                         'nombre' => $p->descripcion_larga,
                         'exist'  => $exist,
                     ])->render(),
                 ];
             })
             ->toArray();
                    }),
            ];
        })

        // ✅ AQUÍ VA TU ACCIÓN ORIGINAL, SIN MODIFICAR NADA
        ->action(function (array $data, Get $get, Set $set, \Filament\Forms\Components\Actions\Action $action) {

            $rows = (array) ($get('../../detalles') ?? []);
            $productId = (int) ($data['producto_id'] ?? 0);

            foreach ($rows as $r) {
                if ((int) ($r['product_id'] ?? 0) === $productId) {
                    $action->halt();
                    \Filament\Notifications\Notification::make()
                        ->title('Este producto ya está en la lista.')
                        ->warning()
                        ->send();
                    return;
                }
            }

            $p = \App\Models\Product::select([
                'id','id_producto','descripcion_larga',
                'precio_costo','descuento_comercial','iva_compra',
                'precio_venta1','utilidad1','existencias',
                'id_unidad_de_medida','vende_por','permite_fraccion'
            ])->find($productId);

            if (! $p) {
                $action->halt();
                \Filament\Notifications\Notification::make()
                    ->title('Producto no encontrado.')
                    ->danger()
                    ->send();
                return;
            }

            $set('codigo_ingresado', $p->id_producto);
            $set('product_id',       $p->id);
            $set('nombre_producto',  $p->descripcion_larga);
            $set('costo_unitario',   (float)$p->precio_costo);
            $set('desc_comercial',   (float)$p->descuento_comercial);
            $set('iva_pct',          number_format((float)$p->iva_compra, 2, '.', ''));
            $set('precio_venta_act', (float)$p->precio_venta1);
            $set('utilidad_pct',     (float)$p->utilidad1);

            $costo = (float)$p->precio_costo;
            $desc  = (float)$p->descuento_comercial;
            $iva   = (float)$p->iva_compra;
            $cDesc = max(0, $costo * (1 - $desc / 100));
            $cIva  = $cDesc * (1 + $iva / 100);
            $set('precio_venta', round($cIva * (1 + (float)$p->utilidad1 / 100), 2));

            $set('existencias_actuales', \App\Filament\Resources\CompraResource::stockActual($p->id));
            $set('unidad_medida_label', static::unidadMedidaLabel((int) ($p->id_unidad_de_medida ?? 1)));
            $set('modo_venta_label', static::modoVentaLabel((string) ($p->vende_por ?? 'unidad')));
            $set('permite_decimal_cantidad', $p->permiteCantidadDecimal());
            $set('producto_variante_id', null);
            static::recalcLinea($get, $set);
        })
)
->columnSpan(3),



// SIN dehydrated(false): se guarda e hidrata
TextInput::make('nombre_producto')
    ->label('Nombre')
    ->disabled()
    ->dehydrated(true)
    ->extraInputAttributes(['class' => 'h-9 text-sm'])
    ->columnSpan(5),

Placeholder::make('unidad_medida_info')
    ->label('Unidad')
    ->content(fn (Get $get) => $get('unidad_medida_label') ?: '-')
    ->dehydrated(false)
    ->columnSpan(2),

Placeholder::make('modo_venta_info')
    ->label('Modo')
    ->content(fn (Get $get) => $get('modo_venta_label') ?: '-')
    ->dehydrated(false)
    ->columnSpan(2),

Placeholder::make('existencias_inline')
    ->label('Exist.')
    ->reactive()
    ->content(function (Get $get) {
        $pid = $get('product_id');
        if (!$pid) return '0';
        return (string) \App\Models\Product::stock((int)$pid);
    })
    ->extraAttributes(['class' => 'text-right font-bold text-sm text-gray-700'])
    ->dehydrated(false)
    ->columnSpan(2),

        ]),

        /* - Variante (solo si el producto vende por talla/color) - */
        Forms\Components\Grid::make(6)->extraAttributes(['class' => 'compra-linea-variante'])
            ->visible(fn (Get $get) => static::productoTieneVariantes($get))
            ->schema([
                Select::make('producto_variante_id')
                    ->label('Variante (talla/color)')
                    ->options(fn (Get $get) => static::opcionesVariantes($get))
                    ->required(fn (Get $get) => static::productoTieneVariantes($get))
                    ->validationMessages(['required' => 'Selecciona la variante (talla/color).'])
                    ->live()
                    ->afterStateUpdated(fn (Get $get, Set $set) => static::recalcLinea($get, $set))
                    ->columnSpan(6),
            ]),

        /* - Lote (solo empresas de droguería): texto libre, no un Select --
             ver resolverLoteId(), cada compra trae un lote fisicamente
             nuevo con su propia fecha de vencimiento. - */
        Forms\Components\Grid::make(6)->extraAttributes(['class' => 'compra-linea-lote'])
            ->visible(fn () => static::empresaEsDrogueria())
            ->schema([
                TextInput::make('lote_texto')
                    ->label('Lote')
                    ->required(fn () => static::empresaEsDrogueria())
                    ->validationMessages(['required' => 'Escribe el número de lote de esta entrada.'])
                    ->columnSpan(3),

                Forms\Components\DatePicker::make('lote_fecha_vencimiento')
                    ->label('Fecha de vencimiento')
                    ->native(false)
                    ->required(fn () => static::empresaEsDrogueria())
                    ->validationMessages(['required' => 'Escribe la fecha de vencimiento de este lote.'])
                    ->columnSpan(3),
            ]),



        /* - Línea 2: Costo + D% + Costo c/desc + IVA + Costo+IVA - */
        Forms\Components\Grid::make(20)->extraAttributes(['class' => 'compra-linea-2'])->schema([
    // ─────────────── Costo y Descuento ───────────────
 TextInput::make('costo_unitario')
    ->label('Costo')
    ->numeric()
    ->minValue(0)
    ->default(0)
    ->rules([
        'required',
        'numeric',
        'min:1',
    ])
    ->lazy()
    ->prefix(fn ($state) => '$ ' . number_format((float) $state, 0, ',', '.'))
    ->extraInputAttributes(['class' => 'h-8 text-xs'])
    ->afterStateUpdated(fn ($state, Get $get, Set $set) => static::calcularLineaCompra($get, $set))
    ->columnSpan(5),

TextInput::make('desc_comercial')
    ->label('Desc.%')
    ->numeric()
    ->minValue(0)
    ->maxValue(100)
    ->default(0)
    ->lazy()
    ->extraInputAttributes(['class' => 'h-8 text-xs'])
    ->afterStateUpdated(fn ($state, Get $get, Set $set) => static::calcularLineaCompra($get, $set))
    ->columnSpan(2),

TextInput::make('costo_con_desc_input')
    ->label('Costo Con Desc')
    ->disabled()
    ->dehydrated(false)
    ->prefix(fn ($state) => '$ ' . number_format((float) $state, 0, ',', '.'))
    ->formatStateUsing(fn ($state) => '$ ' . number_format((float) $state, 0, ',', '.'))
    ->extraInputAttributes(['class' => 'h-8 text-xs text-right'])
    ->columnSpan(5),

Select::make('iva_pct')
    ->label('IVA %')
    ->options([
        '0.00'  => '0%',
        '5.00'  => '5%',
        '8.00'  => '8%',
        '16.00' => '16%',
        '19.00' => '19%',
    ])
    ->default('0.00')
    ->reactive()
    ->dehydrateStateUsing(fn ($state) => (float) $state)
    ->afterStateHydrated(fn ($state, Set $set) =>
        $set('iva_pct', number_format((float) $state, 2, '.', ''))
    )
    ->afterStateUpdated(fn ($state, Get $get, Set $set) => static::calcularLineaCompra($get, $set))
    ->extraAttributes(['class' => 'h-8 text-xs'])
    ->columnSpan(3),

TextInput::make('costo_mas_iva_input')
    ->label('Costo Total + IVA')
    ->disabled()
    ->dehydrated(false)
    ->prefix(fn ($state) => '$ ' . number_format((float) $state, 0, ',', '.'))
    ->formatStateUsing(fn ($state) => '$ ' . number_format((float) $state, 0, ',', '.'))
    ->extraInputAttributes(['class' => 'h-8 text-xs text-right'])
    ->columnSpan(5),
        ]),

        /* - Línea 3: P. Venta Actual + Utili% + Nuevo P. Venta + Cant + Total - */
        Forms\Components\Grid::make(20)->extraAttributes(['class' => 'compra-linea-3'])->schema([

    TextInput::make('precio_venta_act')
    ->label('P. Venta Actual')
    ->numeric() 
    ->default(0) 
    ->disabled() 
    ->dehydrated(true) 
    ->prefix('$ ') 
    ->afterStateHydrated(fn ($state, \Filament\Forms\Set $set)
     => $set('precio_venta_act', is_numeric($state) ? (float) $state : 0) )
      ->dehydrateStateUsing(fn ($state) => (float) $state) 
      ->columnSpan(5),


      TextInput::make('utilidad_pct')
    ->label('Utili.%')
    ->numeric()
    ->minValue(0)
    ->default(0)
    ->lazy()
    ->extraInputAttributes(['class' => 'h-8 text-xs'])
    ->afterStateUpdated(fn ($state, Get $get, Set $set) => static::calcularLineaCompra($get, $set, true))
    ->columnSpan(3),

TextInput::make('precio_venta')
    ->label('Nuevo P. Venta')
    ->numeric()
    ->minValue(0)
    ->default(0)
    ->lazy()
    ->prefix(fn ($state) => '$ ' . number_format((float) $state, 0, ',', '.'))
    ->extraInputAttributes(['class' => 'h-8 text-xs'])
    ->helperText(function (Get $get) {
        $pv = (float) $get('precio_venta');
        $cIva = (float) $get('costo_mas_iva_input');
        return ($pv > 0 && $pv < $cIva)
            ? '⚠️ PV < costo + IVA.'
            : null;
    })
    ->afterStateUpdated(fn ($state, Get $get, Set $set) => static::calcularLineaCompra($get, $set))
    ->columnSpan(5),


    TextInput::make('cantidad')
        ->label('Cant.')
        ->numeric()
        ->minValue(0.01)
        ->default(1)
        ->lazy()
        ->step(fn (Get $get) => $get('permite_decimal_cantidad') ? '0.01' : '1')
        ->inputMode(fn (Get $get) => $get('permite_decimal_cantidad') ? 'decimal' : 'numeric')
        ->helperText(fn (Get $get) => $get('permite_decimal_cantidad') ? 'Acepta decimales.' : 'Solo enteros.')
        ->extraInputAttributes([
            'class' => 'h-8 text-xs text-center',
            'style' => 'max-width:120px',
        ])
        ->afterStateUpdated(function ($state, Get $get, Set $set) {
            $cant = max(0, (float) $state);
            $costo = (float) $get('costo_unitario');
            $desc = (float) $get('desc_comercial');
            $iva = (float) $get('iva_pct');
            $cDesc = max(0, $costo * (1 - $desc / 100));
            $base = $cant * $cDesc;
            $imp = $base * ($iva / 100);
            $set('total_linea_input', round($base + $imp, 2));
        })
        ->columnSpan(2),

    TextInput::make('total_linea_input')
        ->label('Total')
        ->disabled()
        ->dehydrated(false)
        ->prefix(fn ($state) => '$ ' . number_format((float) $state, 0, ',', '.'))
        ->formatStateUsing(fn ($state) => '$ ' . number_format((float) $state, 0, ',', '.'))
        ->extraInputAttributes(['class' => 'h-8 text-xs text-right font-semibold'])
        ->columnSpan(5),

            // Persistencia (FK y totales)
            Forms\Components\Hidden::make('id')->dehydrated(), // <-- NECESARIO para upsert
            Forms\Components\Hidden::make('unidad_medida_label')->dehydrated(false),
            Forms\Components\Hidden::make('modo_venta_label')->dehydrated(false),
            Forms\Components\Hidden::make('permite_decimal_cantidad')->dehydrated(false),
            Forms\Components\Hidden::make('product_id')->required(),
            Forms\Components\Hidden::make('subtotal'),
            Forms\Components\Hidden::make('impuesto'),
            Forms\Components\Hidden::make('total'),
        ]),
    ])
    ->createItemButtonLabel('Agregar ítem'),


                    ]),

                /* ======================== TOTALES ======================== */
                Card::make()
    ->extraAttributes(['class' => 'compra-franja-azul'])
    ->schema([

        Grid::make(3)->extraAttributes(['class' => 'compra-tot-linea-1'])->schema([

        // 🔹 Ítems y cantidades
        Placeholder::make('tot_items')
            ->label('🧾 Ítems')
            ->content(fn (callable $get) => count($get('detalles') ?? []))
            ->extraAttributes(['style' => 'font-weight:600;color:#374151']),

        Placeholder::make('tot_cant')
            ->label('📦 Cant. total')
            ->content(fn (callable $get) => number_format(
                collect($get('detalles') ?? [])->sum(fn($i) => (float)($i['cantidad'] ?? 0)),
                0, ',', '.'
            ))
            ->extraAttributes(['style' => 'font-weight:600;color:#374151']),

        Placeholder::make('tot_desc')
            ->label('💸 Descuento comercial')
            ->content(function (callable $get) {
                $detalles = $get('detalles') ?? [];
                $descuento = collect($detalles)->sum(function ($i) {
                    $cant  = (float)($i['cantidad'] ?? 0);
                    $costo = (float)($i['costo_unitario'] ?? 0);
                    $descP = (float)($i['desc_comercial'] ?? 0);
                    return $cant * $costo * ($descP / 100);
                });
                return '$ ' . number_format($descuento, 0, ',', '.');
            })
            ->extraAttributes(['style' => 'color:#b91c1c;font-weight:600;']),
        ]),

        Grid::make(3)->extraAttributes(['class' => 'compra-tot-linea-2'])->schema([

        // 🔹 Subtotal antes de descuentos
         Placeholder::make('tot_sub')
            ->label('Subtotal (sin descuento)')
            ->content(function (callable $get) {
                $detalles = $get('detalles') ?? [];
                $total = collect($detalles)->sum(fn($i) =>
                    ((float)($i['cantidad'] ?? 0)) * ((float)($i['costo_unitario'] ?? 0))
                );
                return '$ ' . number_format($total, 0, ',', '.');
            })
            ->extraAttributes(['style' => 'color:#111827;font-weight:600;']),

        // 🔹 Subtotal después de descuentos
        Placeholder::make('tot_sub_desc')
            ->label('Subtotal (con descuento)')
            ->content(function (callable $get) {
                $detalles = $get('detalles') ?? [];
                $total = collect($detalles)->sum(function ($i) {
                    $cant  = (float)($i['cantidad'] ?? 0);
                    $costo = (float)($i['costo_unitario'] ?? 0);
                    $descP = (float)($i['desc_comercial'] ?? 0);
                    return $cant * $costo * (1 - $descP / 100);
                });
                return '$ ' . number_format($total, 0, ',', '.');
            })
            ->extraAttributes(['style' => 'color:#0d9488;font-weight:600;']),

        // 🔹 Total impuestos
         Placeholder::make('tot_imp')
            ->label('IVA total')
            ->content(function (callable $get) {
                $detalles = $get('detalles') ?? [];
                $iva = collect($detalles)->sum(function ($i) {
                    $cant  = (float)($i['cantidad'] ?? 0);
                    $costo = (float)($i['costo_unitario'] ?? 0);
                    $descP = (float)($i['desc_comercial'] ?? 0);
                    $ivaP  = (float)($i['iva_pct'] ?? 0);
                    $base  = $cant * $costo * (1 - $descP / 100);
                    return $base * ($ivaP / 100);
                });
                return '$ ' . number_format($iva, 0, ',', '.');
            })
            ->extraAttributes(['style' => 'color:#ca8a04;font-weight:600;']),
        ]),

        // 🔹 Total general (con descuento + IVA)
       Placeholder::make('tot_tot')
            ->label(' ')
            ->content(function (callable $get) {
                $detalles = $get('detalles') ?? [];
                $total = collect($detalles)->sum(function ($i) {
                    $cant  = (float)($i['cantidad'] ?? 0);
                    $costo = (float)($i['costo_unitario'] ?? 0);
                    $descP = (float)($i['desc_comercial'] ?? 0);
                    $ivaP  = (float)($i['iva_pct'] ?? 0);
                    $base  = $cant * $costo * (1 - $descP / 100);
                    return $base * (1 + $ivaP / 100);
                });
                return '💰 TOTAL: $ ' . number_format($total, 0, ',', '.');
            })
            ->extraAttributes([
                'style' => '
                    background:#ecfdf5;
                    color:#065f46;
                    font-weight:700;
                    font-size:1.4rem;
                    text-align:center;
                    border-radius:12px;
                    border:2px solid #6ee7b7;
                    padding:1rem;
                    margin-top:1.5rem;
                    box-shadow:0 8px 18px rgba(6,95,70,.12);
                    display:block;
                ',
            ])
            ->columnSpanFull(),

        // 🔹 Observaciones
        Section::make('📝 Observaciones')
            ->schema([
                Textarea::make('nota')
                    ->label(false)
                    ->rows(2)
                    ->placeholder('Agregar una nota u observación...')
                    ->extraAttributes(['style' => 'font-size:13px'])
                    ->columnSpanFull(),
            ])
            ->compact()
            ->collapsible()
            ->collapsed(),

                        View::make('filament.custom.form-cache-js')
    ->dehydrated(false)
    ->columnSpanFull()
    ->extraAttributes(['style' => 'display:none'])
    ->viewData([
        'cacheKey' => 'compra_form_cache_u_' . auth()->id(), // ✅ calcúlala aquí
    ]),

    View::make('filament.custom.form-cache-edit-js')
    ->dehydrated(false)
    ->columnSpanFull()
    ->visible(fn () => request()->routeIs('filament.admin.resources.compras.edit'))
    ->viewData([
        // clave directa, sin Closures, por usuario + id del record
        'cacheKey' => 'compra_form_cache_edit_u_' . (auth()->id() ?? 'guest')
            . '_r_' . (string) (
                ($r = request()->route('record')) instanceof \Illuminate\Database\Eloquent\Model
                    ? $r->getKey()
                    : ($r ?? '0')
            ),
    ]),
                    ]),
            ]);
    }

    /* ----------------- Variantes (talla/color) ----------------- */
    protected static function productoTieneVariantes(Get $get): bool
    {
        $pid = (int) ($get('product_id') ?? 0);
        if (! $pid) {
            return false;
        }

        return \App\Models\ProductoVariante::where('product_id', $pid)->where('activo', true)->exists();
    }

    protected static function opcionesVariantes(Get $get): array
    {
        $pid = (int) ($get('product_id') ?? 0);
        if (! $pid) {
            return [];
        }

        return \App\Models\ProductoVariante::where('product_id', $pid)
            ->where('activo', true)
            ->orderBy('nombre')
            ->pluck('nombre', 'id')
            ->toArray();
    }

    /* ----------------- Lotes (droguería) -----------------
     * A diferencia de variantes (que ya existen de antemano y aca solo se
     * elige una), un lote no tiene sentido crearlo por adelantado: cada
     * compra trae fisicamente un lote nuevo con su propia fecha de
     * vencimiento. Por eso la linea de compra pide texto libre (lote +
     * vencimiento) en vez de un Select, y resolverLoteId() hace
     * find-or-create por (product_id, lote) -- ver plan "Drogueria: lotes
     * con stock y vencimiento propios".
     */
    public static function empresaEsDrogueria(): bool
    {
        $empresaId = auth()->user()->getEmpresaActualId();

        return \App\Models\ConfiguracionEmpresa::where('empresa_id', $empresaId)->value('tipo_negocio') === 'drogueria';
    }

    /**
     * Devuelve el id del ProductoLote para (productId, lote), creandolo
     * (con stock 0 y codigo auto-sugerido) si todavia no existia. El
     * incremento real de stock lo hace Compra::confirmar() (mismo momento
     * en que hoy se incrementa el stock de una variante), no aca -- aca
     * solo se resuelve CUAL lote es, para que quede guardado en el
     * detalle de la compra.
     */
    public static function resolverLoteId(int $productId, int $empresaId, ?string $loteTexto, ?string $fechaVencimiento): ?int
    {
        $lote = trim((string) $loteTexto);
        if ($lote === '' || ! $fechaVencimiento) {
            return null;
        }

        $existente = \App\Models\ProductoLote::where('product_id', $productId)->where('lote', $lote)->first();
        if ($existente) {
            return $existente->id;
        }

        $producto = \App\Models\Product::find($productId);
        $codigosEnUso = \App\Models\ProductoLote::where('product_id', $productId)
            ->pluck('codigo')
            ->filter()
            ->all();

        $codigo = \App\Support\VariantCodeGenerator::sugerir((string) ($producto?->id_producto ?? $productId), $lote, '', $codigosEnUso);

        return \App\Models\ProductoLote::create([
            'empresa_id' => $empresaId,
            'product_id' => $productId,
            'codigo' => $codigo,
            'lote' => $lote,
            'fecha_vencimiento' => $fechaVencimiento,
            'stock' => 0,
            'activo' => true,
        ])->id;
    }

    /* ================== Cálculo de una fila (cliente) ================== */
    public static function serverRecalcLinea(array $row): array
    {
        $costo = (float)($row['costo_unitario'] ?? 0);
        $desc  = (float)($row['desc_comercial'] ?? 0);
        $iva   = (float)($row['iva_pct'] ?? 0);
        $cant  = max(0, (float)($row['cantidad'] ?? 1));

        $costoDesc   = max(0, $costo * (1 - $desc / 100));
        $base        = $cant * $costoDesc;
        $imp         = $base * ($iva / 100);
        $total       = $base + $imp;

        $row['subtotal'] = round($base, 2);
        $row['impuesto'] = round($imp,  2);
        $row['total']    = round($total,2);

        $row['costo_con_desc_input'] = round($costoDesc, 2);
        $row['costo_mas_iva_input']  = round($costoDesc * (1 + $iva / 100), 2);
        $row['total_linea_input']    = $row['total'];

        return $row;
    }

    /* ----------------- Cálculos de línea ----------------- */
    protected static function recalcLinea(Get $get, Set $set): void
    {
        $costo = (float) ($get('costo_unitario') ?? 0);
        $desc  = (float) ($get('desc_comercial') ?? 0);
        $iva   = (float) ($get('iva_pct') ?? 0);
        $cant  = max(0, (float) ($get('cantidad') ?? 1));

        $costoDesc   = max(0, $costo * (1 - $desc / 100));
        $costoMasIva = $costoDesc * (1 + $iva / 100);

        $base  = $cant * $costoDesc;
        $imp   = $base * ($iva / 100);
        $total = $base + $imp;

        $set('costo_con_desc_input', round($costoDesc, 2));
        $set('costo_mas_iva_input',  round($costoMasIva, 2));
        $set('total_linea_input',    round($total, 2));

        $set('subtotal', round($base, 2));
        $set('impuesto', round($imp,  2));
        $set('total',    round($total, 2));
    }

    /* ----------------- Totales del Repeater ----------------- */
    protected static function sumTot(callable $get, string $field): string
    {
        $rows = $get('detalles') ?? [];
        $sum = 0;
        foreach ($rows as $r) {
            $cant  = (float)($r['cantidad'] ?? 0);
            $costo = (float)($r['costo_unitario'] ?? 0);
            $desc  = (float)($r['desc_comercial'] ?? 0);
            $iva   = (float)($r['iva_pct'] ?? 0);

            $costoDesc = max(0, $costo - ($costo * $desc / 100));
            $base      = $cant * $costoDesc;
            $imp       = $base * ($iva / 100);
            $total     = $base + $imp;

            $sum += match ($field) {
                'subtotal' => $base,
                'impuesto' => $imp,
                'total'    => $total,
                default    => 0,
            };
        }
        return '$ ' . number_format($sum, 0, ',', '.');
    }

    protected static function unidadMedidaLabel(int $unidadId): string
    {
        return match ($unidadId) {
            2 => 'Kilogramos (kg)',
            3 => 'Litros (lt)',
            4 => 'Metros (mt)',
            5 => 'Horas (hr)',
            default => 'Pieza (und)',
        };
    }

    protected static function modoVentaLabel(string $modo): string
    {
        return match ($modo) {
            'peso' => 'Por peso',
            'porcion' => 'Por porción',
            'litro' => 'Por litro',
            'metro' => 'Por metro',
            'hora' => 'Por hora',
            default => 'Por unidad',
        };
    }

    /** ----------------- Tabla (Listado) ----------------- */
public static function table(Tables\Table $table): Tables\Table
{
    return $table

        // ===============================
        //   CONSULTA BASE
        // ===============================
      ->modifyQueryUsing(function (Builder $query, $livewire) {

    $user = auth()->user();

    if (!$user->hasRole('super_admin')) {
        $empresaId = $user->empresa_id ?: $user->id;
        $query->where('empresa_id', $empresaId);
    }

    // Obtener filtros aplicados (Filament 3)
    $filtros = collect($livewire->tableFilters ?? [])
        ->filter(fn ($f) => !empty($f['value']))
        ->toArray();

    $tieneFiltros = count($filtros) > 0;

    // Si NO hay filtros → mostrar solo borradores del mes actual
    if (!$tieneFiltros) {

        $query->where('estado', 'borrador');

        $inicio = now()->startOfMonth()->toDateString();
        $fin    = now()->endOfMonth()->toDateString();

        $query->whereBetween('created_at', [$inicio, $fin]);
    }

})
        ->defaultSort('created_at', 'desc')

        /* ============================================================
         *                    COLUMNAS
         * ============================================================ */
        ->columns([

            // Nombre del proveedor
            TextColumn::make('proveedor.nombre')
                ->label('Proveedor')
                ->sortable(),

            TextColumn::make('numero_factura')
                ->label('N° Factura')
                ->sortable()
                ->searchable(),

            // Mostrar fecha de creación
            TextColumn::make('created_at')
                ->label('Fecha')
                ->date('d/m/Y')
                ->sortable(),

            // Estado corregido total
            BadgeColumn::make('estado')
                ->label('Estado')
                ->state(function ($record) {

                    if ($record->estado === 'anulada') {
                        return 'anulada';
                    }

                    $pagado = $record->pagos()->sum('monto');
                    $total = $record->total;

                    if ($record->estado === 'borrador') {
                        return 'borrador';
                    }

                    // Contado = pagada
                    if ($record->tipo_pago === 'contado') {
                        return 'pagada';
                    }

                    // Crédito
                    if ($pagado >= $total) {
                        return 'pagada';
                    }

                    return 'pendiente';
                })
                ->colors([
                    'gray'    => 'borrador',
                    'success' => 'pagada',
                    'warning' => 'pendiente',
                    'danger'  => 'anulada',
                ]),

            // Tipo de pago con colores nuevos
            BadgeColumn::make('tipo_pago')
                ->label('Pago')
                ->colors([
                    'info'    => 'contado',
                    'warning' => 'credito',
                ]),

            // Total
            TextColumn::make('total')
                ->label('Total factura')
                ->sortable()
                ->formatStateUsing(fn ($state) =>
                    '$ ' . number_format((float) $state, 0, ',', '.')
                ),
        ])

         ->actions([
                Action::make('exportar_bartender')
                    ->label('Exportar BarTender')
                    ->color('success')
                    ->icon('heroicon-o-command-line')
                    ->form([
                        Forms\Components\Select::make('tamano_bartender')
                            ->label('Tamano de etiqueta')
                            ->options([
                                '2x50x25' => '2 columnas - 50 x 25 mm',
                                '3x32x25' => '3 columnas - 32 x 25 mm',
                            ])
                            ->default('2x50x25')
                            ->required(),
                        Forms\Components\Select::make('mostrar_precio_bartender')
                            ->label('Precio')
                            ->options([
                                'con_precio' => 'Con precio',
                                'sin_precio' => 'Sin precio',
                            ])
                            ->default('con_precio')
                            ->required(),
                    ])
                    ->action(function (Compra $record, array $data) {
                        $empresaId = auth()->user()->getEmpresaActualId();

                        if ((int) $record->empresa_id !== (int) $empresaId) {
                            abort(403);
                        }

                        $detalles = $record->detalles()
                            ->whereHas('compra', fn ($query) => $query->where('empresa_id', $empresaId))
                            ->orderBy('nombre_producto')
                            ->get(['product_id', 'producto_variante_id', 'nombre_producto', 'cantidad', 'precio_venta']);

                        // 🎨 Si la linea de compra fue de una variante puntual
                        // (talla/color), el codigo de barras a imprimir es el
                        // de esa variante -- si no tiene codigo propio, se cae
                        // al del producto para no imprimir un codigo vacio.
                        $variantesPorId = \App\Models\ProductoVariante::whereIn(
                            'id',
                            $detalles->pluck('producto_variante_id')->filter()
                        )->get()->keyBy('id');

                        $lineas = $detalles->map(function ($detalle) use ($variantesPorId) {
                            $variante = $detalle->producto_variante_id
                                ? $variantesPorId->get($detalle->producto_variante_id)
                                : null;

                            return [
                                'product_id'      => $variante?->codigoPrincipal() ?: $detalle->product_id,
                                'nombre_producto' => $variante ? $detalle->nombre_producto . ' - ' . $variante->nombre : $detalle->nombre_producto,
                                'cantidad'        => $detalle->cantidad,
                                'precio_venta'    => $detalle->precio_venta,
                            ];
                        })->all();

                        return \App\Support\BarTenderExport::generarBat(
                            $lineas,
                            $data['tamano_bartender'] ?? '2x50x25',
                            $data['mostrar_precio_bartender'] ?? 'con_precio',
                            'compra_' . $record->id,
                        );
                    })
            ])
        /* ============================================================
         *                    FILTROS
         * ============================================================ */
        ->filters([

         Tables\Filters\SelectFilter::make('proveedor_id')
    ->label('Proveedor')

    ->options(fn () =>

        Actor::query()

            ->where('tipo', 3)

            ->where('empresa_id', auth()->user()->getEmpresaActualId())

            ->orderBy('nombre')

            ->pluck('nombre', 'id')

            ->toArray()

    )

    ->searchable()

    ->preload()

    ->placeholder('Todos los proveedores'),

            // Estado
            SelectFilter::make('estado_logico')
    ->label('Estado')
    ->options([
        'borrador'  => 'Borrador',
        'pendiente' => 'Pendiente',
        'pagada'    => 'Pagada',
        'anulada'   => 'Anulada',
    ])
    ->query(function (Builder $query, $data) {
        if (!$data['value']) return;

        $estado = $data['value'];

        $query->whereRaw("
            (
                CASE
                    WHEN estado = 'anulada' THEN 'anulada'
                    WHEN estado = 'borrador' THEN 'borrador'
                    WHEN tipo_pago = 'contado' THEN 'pagada'
                    WHEN (SELECT COALESCE(SUM(monto),0) FROM pagos WHERE pagos.compra_id = compras.id) >= total THEN 'pagada'
                    ELSE 'pendiente'
                END
            ) = ?
        ", [$estado]);
    })
    ->placeholder('Todos los estados'),

            // Tipo de pago
            Tables\Filters\SelectFilter::make('tipo_pago')
                ->label('Pago')
                ->options([
                    'contado' => 'Contado',
                    'credito' => 'Crédito',
                ])
                ->placeholder('Todos'),

            // Fecha
            Tables\Filters\Filter::make('fecha')
                ->form([
                    DatePicker::make('desde'),
                    DatePicker::make('hasta'),
                ])
                ->query(function (Builder $query, array $data) {

                    $desde = $data['desde'] ?? null;
                    $hasta = $data['hasta'] ?? null;

                    if ($desde && $hasta) {
                        return $query->whereBetween('created_at', [$desde, $hasta]);
                    }

                    if ($desde) {
                        return $query->where('created_at', '>=', $desde);
                    }

                    if ($hasta) {
                        return $query->where('created_at', '<=', $hasta);
                    }
                }),
        ]);
}

   public static function getRelations(): array
{
    return [
        RelationManagers\PagosRelationManager::class,
        RelationManagers\NotaCreditosRelationManager::class,
    ];
}

public static function getEloquentQuery(): Builder
{
    $query = parent::getEloquentQuery();
    $user = auth()->user();

    if ($user?->hasRole('super_admin')) {
        return $query;
    }

    return $query->where('empresa_id', $user?->getEmpresaActualId());
}


    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListCompras::route('/'),
            'create' => Pages\CreateCompra::route('/create'),
            'edit'   => Pages\EditCompra::route('/{record}/edit'),
            'view'   => Pages\ViewCompra::route('/{record}'),
        ];
    }

    /* ----------------- Helpers de búsqueda ----------------- */

    /**
     * compras.proveedor_id guarda actors.id; productos.id_proveedor usa actors.id_clip_pro.
     */
    protected static function proveedorClip(\Filament\Forms\Get $get): ?int
{
    $valor = $get('../../proveedor_id') ?? $get('proveedor_id') ?? 0;
    $actorId = (int) $valor;

    if ($actorId <= 0) {
        return null;
    }

    $idClipPro = (int) Actor::query()
        ->where('empresa_id', auth()->user()->getEmpresaActualId())
        ->whereKey($actorId)
        ->value('id_clip_pro');

    return $idClipPro > 0 ? $idClipPro : null;
}

    /**
     * Búsqueda rápida por código/nombre/alterno
     * Filtra SIEMPRE por empresa y por id_proveedor = id_clip_pro
     */
    protected static function fastFindProduct(int $empresaId, int $provClip, string $input): ?\App\Models\Product
{
    $code = trim($input);
    if ($code === '') return null;

    // Normaliza por si te llegan espacios o guiones en alternos (opcional)
    $normalized = strtoupper(preg_replace('/[\s\-]+/', '', $code));

    return \App\Models\Product::query()
        ->where('empresa_id', $empresaId)
        ->where('id_proveedor', $provClip)
        ->where(function ($q) use ($code, $normalized, $empresaId) {
            // Si es todo dígitos, intenta match exacto con id_producto
            if (ctype_digit($code)) {
                $q->orWhere('id_producto', (int) $code);
            }

            // Siempre verifica códigos alternos (match exacto; si quieres "contiene", cámbialo a like)
            $q->orWhereExists(function ($sub) use ($normalized, $empresaId) {
                $sub->from('alternate_codes as ac')
                    ->whereColumn('ac.product_id', 'products.id')
                    ->where('ac.empresa_id', $empresaId)
                    // exacto: normaliza igual que arriba
                    ->whereRaw("REPLACE(UPPER(ac.code), ' ', '') = ?", [$normalized]);
                    // si prefieres 'contiene': ->where('ac.code', 'like', "%{$normalized}%")
            });
        })
        // Prioriza coincidencia exacta por id_producto si el input era numérico
        ->when(ctype_digit($code), fn ($q) =>
            $q->orderByRaw('CASE WHEN id_producto = ? THEN 0 ELSE 1 END', [(int) $code])
        )
        ->first();
}
    /**
     * Opciones del modal: SOLO productos de ese proveedor (id_clip_pro) y empresa actual.
     */
    protected static function productsOptionsForProveedor(int $empresaId, int $provClip): array
    {
        if (!$provClip) return [];

        return Product::query()
            ->select(['id', 'id_producto', 'descripcion_larga'])
            ->where('empresa_id', $empresaId)
            ->where('id_proveedor', $provClip)
            ->orderBy('descripcion_larga')
            ->limit(500)
            ->get()
            ->mapWithKeys(fn ($p) => [
                $p->id => $p->id_producto . ' · ' . $p->descripcion_larga,
            ])->toArray();
    }


    protected static function stockActual(?int $productId): float
{
    if (!$productId) return 0.0;

    // Desde products.existencias
    $p = \App\Models\Product::select('id','existencias')->find($productId);
    if ($p && $p->existencias !== null) {
        return (float) $p->existencias;
    }

    // (Opcional) Fallback al kardex, si existe
    try {
        $in  = (float) DB::table('kardex')->where('product_id',$productId)->sum('entrada');
        $out = (float) DB::table('kardex')->where('product_id',$productId)->sum('salida');
        return $in - $out;
    } catch (\Throwable $e) {
        return 0.0;
    }

    
}

protected static function repeaterTieneItems(\Filament\Forms\Get $get): bool
{
    $rows = (array) ($get('detalles') ?? []);
    foreach ($rows as $r) {
        $pid  = $r['product_id'] ?? null;
        $code = trim((string) ($r['codigo_ingresado'] ?? ''));
        if (!empty($pid) || $code !== '') return true;
    }
    return false;
}

protected function mutateFormDataBeforeSave(array $data): array
{
    // ✅ 1) Validación: si hay ítems y el usuario intenta cambiar el proveedor, lo bloqueamos
    $rows = $data['detalles'] ?? [];
    $tieneItems = collect($rows)->contains(function ($r) {
        return !empty($r['product_id']) || trim((string)($r['codigo_ingresado'] ?? '')) !== '';
    });

    if ($tieneItems && $this->record && (int)$data['proveedor_id'] !== (int)$this->record->proveedor_id) {
        throw \Illuminate\Validation\ValidationException::withMessages([
            'data.proveedor_id' => 'Para cambiar el proveedor, elimina primero los ítems del detalle.',
        ]);
    }

    // ✅ 2) Recalcular totales antes de guardar (teniendo en cuenta desc_comercial)
    $detalles = $data['detalles'] ?? [];

    $subtotal = 0;
    $impuesto = 0;
    $descuento = 0;
    $total = 0;

    foreach ($detalles as $item) {
        $cantidad = (float)($item['cantidad'] ?? 0);
        $costo = (float)($item['costo_unitario'] ?? 0);
        $desc = (float)($item['desc_comercial'] ?? 0);
        $iva  = (float)($item['iva_pct'] ?? 0);

        // 💰 Subtotal sin descuento
        $subtotalItem = $cantidad * $costo;

        // 💸 Descuento del ítem
        $descuentoItem = $subtotalItem * ($desc / 100);

        // 💵 Subtotal con descuento
        $base = $subtotalItem - $descuentoItem;

        // 🧾 Impuesto del ítem
        $imp = $base * ($iva / 100);

        // 📊 Acumular totales
        $subtotal += $subtotalItem;
        $descuento += $descuentoItem;
        $impuesto += $imp;
        $total += $base + $imp;
    }

    // 🧮 Guardar en el array de datos
    $data['subtotal'] = round($subtotal, 2);
    $data['descuento_total'] = round($descuento, 2);
    $data['impuesto_total'] = round($impuesto, 2);
    $data['total'] = round($total, 2);

    return $data;
}



// Dentro de App\Filament\Resources\CompraResource (puede ser al final, como los otros helpers)
protected static function productIdByCode(int $empresaId, int $provClip, string $code): ?int
{
    $code = trim($code);
    if ($code === '') return null;

    // Busca por id_producto exacto o por código alterno exacto
    $pid = \App\Models\Product::query()
        ->where('empresa_id', $empresaId)
        ->where('id_proveedor', $provClip)
        ->where(function ($qq) use ($code, $empresaId) {
            $qq->where('id_producto', $code) // MySQL castea strings a int si la columna es int
               ->orWhereExists(function ($sub) use ($code, $empresaId) {
                   $sub->from('alternate_codes as ac')
                       ->whereColumn('ac.product_id', 'products.id')
                       ->where('ac.empresa_id', $empresaId)
                       ->where('ac.code', $code); // exacto
               });
        })
        ->value('id');

    return $pid ? (int) $pid : null;
}

protected static function compraEstaAnulada(Get $get): bool
{
    // 1) Intenta leerlo del estado del form
    $estado = $get('../../estado') ?? $get('estado') ?? null; // desde el repeater sube dos niveles
    if (is_string($estado)) {
        return $estado === 'anulada';
    }

    // 2) Fallback: carga por la ruta del Edit
    $id = request()->route('record');
    if ($id) {
        $c = \App\Models\Compra::query()->select('estado')->find($id);
        return ($c?->estado === 'anulada');
    }

    return false;
}



public static function viewInfolistSchema(): array
{
    return [
        InfoSection::make('Cabecera')
            ->schema([
                InfoGrid::make(12)->schema([
                    TextEntry::make('numero_factura')
                        ->label('No. factura')
                        ->columnSpan(3),

                    TextEntry::make('fecha')
                        ->label('Fecha')
                        ->date('d/m/Y')
                        ->columnSpan(3),

                    TextEntry::make('tipo_pago')
                        ->label('Tipo de pago')
                        ->badge()
                        ->color(fn (string|null $state) => $state === 'contado' ? 'success' : 'warning')
                        ->columnSpan(3),

                    TextEntry::make('estado')
    ->label('Estado')
    ->state(function (Compra $record) {
        if ($record->estado === 'confirmada') {
            $saldo = max(0, $record->total - $record->pagos()->sum('monto'));
            return $saldo <= 0 ? 'pagada' : 'pendiente';
        }
        return $record->estado;
    })
    ->badge()
    ->color(fn (string $state) => match ($state) {
        'pagada'    => 'success',
        'pendiente' => 'warning',
        'anulada'   => 'danger',
        'borrador'  => 'gray',
        default     => 'gray',
    })
    ->icon(fn (string $state) => match ($state) {
        'pagada'    => 'heroicon-o-check-circle',
        'pendiente' => 'heroicon-o-clock',
        'anulada'   => 'heroicon-o-x-circle',
        'borrador'  => 'heroicon-o-document',
        default     => null,
    })
    ->columnSpan(3),

                    // Nombre del proveedor a partir de proveedor_id (actors.id)
                    TextEntry::make('proveedor_nombre')
                        ->label('Proveedor')
                        ->state(function (Compra $record): string {
                            return (string) Actor::query()
                                ->where('empresa_id', $record->empresa_id)
                                ->whereIn('clasificacion', ['proveedor', 'cliente_proveedor'])
                                ->whereKey($record->proveedor_id)
                                ->value('nombre') ?? '—';
                        })
                        ->columnSpan(6),

                    TextEntry::make('nota')
                        ->label('Nota')
                        ->default('—')
                        ->columnSpan(6),
                ]),
            ])
            ->collapsible(),

      InfoSection::make('Ítems')
      
    ->schema([
        // 🔹 Cabecera
        InfoGrid::make(12)->schema([
            TextEntry::make('h_cod')->label('Código')->columnSpan(2),
            TextEntry::make('h_prod')->label('Producto')->columnSpan(3),
            TextEntry::make('h_precio')->label('Precio U.')->columnSpan(2)->extraAttributes(['class' => 'text-right']),
            TextEntry::make('h_desc')->label('Des. %')->columnSpan(1)->extraAttributes([
                'class' => 'text-right whitespace-nowrap' // evita salto de línea
            ]),
            TextEntry::make('h_iva')->label('IVA %')->columnSpan(1)->extraAttributes(['class' => 'text-right']),
            TextEntry::make('h_cant')->label('Cant.')->columnSpan(1)->extraAttributes(['class' => 'text-right']),
            TextEntry::make('h_total')->label('Total')->columnSpan(2)->extraAttributes(['class' => 'text-right']),
        ])->extraAttributes(['class' => 'text-xs font-semibold text-gray-500']),

        // 🔹 Filas de productos
        RepeatableEntry::make('detalles')
            ->label('')
            ->state(fn ($record) => collect($record->detalles)->values())
            ->schema([
                // Código
                TextEntry::make('codigo_ingresado')
                    ->hiddenLabel()
                    ->columnSpan(2)
                    ->extraAttributes(['class' => 'text-[13px] tabular-nums']),

                // Producto
                TextEntry::make('nombre_producto')
                    ->hiddenLabel()
                    ->columnSpan(3)
                    ->extraAttributes(['class' => 'text-[13px] leading-5']),

                // Precio unitario
                TextEntry::make('costo_unitario')
                    ->hiddenLabel()
                    ->formatStateUsing(fn ($state) => '$ ' . number_format((float) ($state ?? 0), 0, ',', '.'))
                    ->columnSpan(2)
                    ->extraAttributes(['class' => 'text-right text-[13px] tabular-nums']),

                // Descuento comercial (sin decimales)
                TextEntry::make('desc_comercial')
                    ->hiddenLabel()
                    ->formatStateUsing(fn ($state) => number_format((float) ($state ?? 0), 0, ',', '.') . ' %')
                    ->columnSpan(1)
                    ->extraAttributes([
                        'class' => 'text-right text-[13px] tabular-nums text-gray-700 whitespace-nowrap'
                    ]),

                // IVA %
                TextEntry::make('iva_pct')
                    ->hiddenLabel()
                    ->formatStateUsing(fn ($state) => number_format((float) ($state ?? 0), 0, ',', '.') . '%')
                    ->columnSpan(1)
                    ->extraAttributes(['class' => 'text-right text-[13px] tabular-nums']),

                // Cantidad (con devoluciones)
                TextEntry::make('cantidad')
                    ->hiddenLabel()
                    ->formatStateUsing(function ($state, $record) {
                        $cantidad = number_format((float) ($state ?? 0), 0, ',', '.');
                        $devuelto = \App\Models\DevolucionCompraDetalle::where('compra_detalle_id', $record->id)->sum('cantidad');

                        if ($devuelto > 0) {
                            $devueltoFmt = number_format($devuelto, 0, ',', '.');
                            return "{$cantidad} <span style='color:#d32f2f;font-weight:600;'>(-{$devueltoFmt})</span>";
                        }

                        return $cantidad;
                    })
                    ->html()
                    ->columnSpan(1)
                    ->extraAttributes(['class' => 'text-right tabular-nums text-[13px]']),

                // Total
                TextEntry::make('total')
                    ->hiddenLabel()
                    ->formatStateUsing(fn ($state) => '$ ' . number_format((float) ($state ?? 0), 0, ',', '.'))
                    ->columnSpan(2)
                    ->extraAttributes(['class' => 'text-right tabular-nums font-semibold text-[13px]']),
            ])
            ->columns(12)
            ->contained(false)
            ->extraAttributes(['class' => 'divide-y divide-gray-100'])
            ->columnSpanFull(),
    ])
    ->columnSpanFull()
    ->collapsible(),


        InfoSection::make('Totales')
    ->schema([
        InfoGrid::make(12)->schema([

            // 🔹 Subtotal con descuento aplicado
            TextEntry::make('subtotal')
                ->label('Subtotal')
                ->formatStateUsing(function ($record) {
                    $subtotal = (float)($record->subtotal ?? 0);
                    $descuento = (float)($record->descuento_total ?? 0);
                    $subtotalConDescuento = $subtotal - $descuento;

                    return '$ ' . number_format($subtotalConDescuento, 0, ',', '.');
                })
                ->columnSpan(4),

            // 🔹 IVA total
            TextEntry::make('impuesto_total')
                ->label('IVA')
                ->formatStateUsing(fn ($state) => '$ ' . number_format((float)($state ?? 0), 0, ',', '.'))
                ->columnSpan(4),

            // 🔹 Total general
            TextEntry::make('total')
                ->label('Total')
                ->formatStateUsing(fn ($state) => '$ ' . number_format((float)($state ?? 0), 0, ',', '.'))
                ->columnSpan(4),
        ]),
    ])
    ->collapsible(),
    InfoSection::make('Devoluciones registradas')
    ->schema([
        TextEntry::make('devoluciones_tabla')
            ->hiddenLabel()
            ->state(function ($record) {
                $detalles = \App\Models\DevolucionCompraDetalle::query()
                    ->whereHas('devolucion', fn($q) => $q->where('compra_id', $record->id))
                    ->get();

                if ($detalles->isEmpty()) {
                    return '<div style="text-align:center;padding:12px 0;color:#888;font-style:italic;">No hay devoluciones registradas.</div>';
                }

                // ✅ Ahora con ancho total y diseño responsivo
                $html = '
                <div style="width:100%;display:block;">
                    <table style="width:100%;font-size:13px;border-collapse:collapse;table-layout:fixed;">
                        <thead>
                            <tr style="border-bottom:1px solid #e5e7eb;background-color:#f9fafb;">
                                <th align="left" style="padding:8px 10px;width:15%;color:#374151;font-weight:600;">Código</th>
                                <th align="left" style="padding:8px 10px;width:55%;color:#374151;font-weight:600;">Producto</th>
                                <th align="right" style="padding:8px 10px;width:15%;color:#374151;font-weight:600;">Cant.</th>
                                <th align="right" style="padding:8px 10px;width:15%;color:#374151;font-weight:600;">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                ';

                foreach ($detalles as $d) {
                    $producto = \App\Models\Product::whereRaw("REPLACE(id_producto, '.', '') = ?", [preg_replace('/[^\d]/', '', (string) $d->product_id)])->first();
                    $detalleCompra = \App\Models\CompraDetalle::find($d->compra_detalle_id);

                    $codigo = $producto?->id_producto ?? $detalleCompra?->codigo_ingresado ?? $d->product_id ?? '-';
                    $nombre = $producto?->descripcion_larga ?? $detalleCompra?->nombre_producto ?? '-';
                    $cantidad = number_format($d->cantidad ?? 0, 0, ',', '.');
                    $total = '$ ' . number_format($d->subtotal_con_impuesto ?? 0, 0, ',', '.');

                    $html .= "
                        <tr style='border-bottom:1px solid #f3f4f6;'>
                            <td style=\"padding:8px 10px;color:#111827;word-break:break-word;\">$codigo</td>
                            <td style=\"padding:8px 10px;color:#111827;word-break:break-word;\">$nombre</td>
                            <td align=\"right\" style=\"padding:8px 10px;color:#111827;\">$cantidad</td>
                            <td align=\"right\" style=\"padding:8px 10px;color:#111827;font-weight:600;\">$total</td>
                        </tr>
                    ";
                }

                $html .= '</tbody></table></div>';
                return $html;
            })
            ->html()
            ->columnSpanFull()
            ->extraAttributes([
                'style' => 'width:100%;padding:0;margin:0;display:block;',
            ]),

        TextEntry::make('total_devolucion_general')
            ->hiddenLabel()
            ->state(function ($record) {
                $total = \App\Models\DevolucionCompraDetalle::query()
                    ->whereHas('devolucion', fn($q) => $q->where('compra_id', $record->id))
                    ->sum('subtotal_con_impuesto');
                return $total > 0 ? 'Total devuelto: $ ' . number_format($total, 0, ',', '.') : null;
            })
            ->visible(fn($record) =>
                \App\Models\DevolucionCompraDetalle::whereHas('devolucion', fn($q) => $q->where('compra_id', $record->id))->exists()
            )
            ->columnSpanFull()
            ->extraAttributes([
                'class' => 'text-right text-[13px] font-semibold text-gray-800 pt-2 border-t border-gray-200',
            ]),
    ])
    ->columnSpanFull()
    ->collapsible(),



    ];
}

public static function canEdit(Model $record): bool
{
    return $record->estado === 'borrador';
}

public static function canDelete(Model $record): bool
{
    return $record->estado === 'borrador';
}

public static function tableActions(): array
{
    return [
        \Filament\Tables\Actions\Action::make('ver')
                    ->label('Ver')
                    ->icon('heroicon-o-eye')
                    ->url(fn (Compra $record) =>
                        \App\Filament\Resources\CompraResource\Pages\ViewCompra::getUrl(['record' => $record])
                    )
                    ->visible(fn (Compra $record) => $record->estado !== 'borrador'),

        \Filament\Tables\Actions\EditAction::make()
            ->visible(fn (\App\Models\Compra $r) => $r->estado === 'borrador'),

        \Filament\Tables\Actions\Action::make('anular')
            ->label('Anular')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (\App\Models\Compra $r) => $r->estado === 'confirmada')
            ->requiresConfirmation()
            ->form([
                Forms\Components\Textarea::make('motivo')
                    ->label('Motivo de la anulación')
                    ->required()
                    ->rows(2),
            ])
            ->action(function (\App\Models\Compra $r, array $data) {
                try {
                    \Illuminate\Support\Facades\DB::transaction(fn () => $r->anular($data['motivo']));
                } catch (\RuntimeException $e) {
                    \Filament\Notifications\Notification::make()->title($e->getMessage())->danger()->send();
                    return;
                }

                \Filament\Notifications\Notification::make()->title('Compra anulada')->success()->send();
            }),

        \Filament\Tables\Actions\Action::make('confirmar')
            ->label('Confirmar')
            ->icon('heroicon-o-check')
            ->color('success')
            ->visible(fn (\App\Models\Compra $r) => $r->estado === 'borrador')
            ->requiresConfirmation()
            ->action(function (\App\Models\Compra $r) {
                \Illuminate\Support\Facades\DB::transaction(fn () => $r->confirmar());
                \Filament\Notifications\Notification::make()->title('Compra confirmada')->success()->send();
            }),
    ];
}
protected static function calcularLineaCompra(Get $get, Set $set, bool $forzarUtilidad = false): void
{
    $costo = (float) $get('costo_unitario');
    $desc  = (float) $get('desc_comercial');
    $iva   = (float) $get('iva_pct');
    $util  = (float) $get('utilidad_pct');
    $venta = (float) $get('precio_venta');

    // 1️⃣ Calcular costo con descuento e IVA
    $cDesc = round($costo * (1 - $desc / 100), 2);
    $set('costo_con_desc_input', $cDesc);

    $cIva = round($cDesc * (1 + $iva / 100), 2);
    $set('costo_mas_iva_input', $cIva);

    // 2️⃣ Si cambió la utilidad manualmente → recalcular PV
    if ($forzarUtilidad) {
        if ($util >= 100) {
            $set('precio_venta', $cIva);
            return;
        }

        // Precio = costo total / (1 - utilidad)
        $pv = $cIva / (1 - ($util / 100));
        $set('precio_venta', round($pv, 2));
        return;
    }

    // 3️⃣ Si cambió el precio de venta manualmente → recalcular utilidad real
    if ($venta > 0 && $cIva > 0) {
        // Utilidad real = (PV - costo total) / PV * 100
        $utilReal = (($venta - $cIva) / $venta) * 100;
        $set('utilidad_pct', round($utilReal, 2));
    }
}


public function getTotalCalculadoAttribute(): float
{
    $cantidad = (float)($this->cantidad ?? 0);
    $costo    = (float)($this->costo_unitario ?? 0);
    $desc     = (float)($this->desc_comercial ?? 0);
    $iva      = (float)($this->iva_pct ?? 0);

    // ✔ mismo cálculo que usas al guardar
    $lineaBruta     = $cantidad * $costo;
    $lineaDescuento = $lineaBruta * ($desc / 100);
    $lineaBase      = $lineaBruta - $lineaDescuento;
    $lineaImpuesto  = $lineaBase * ($iva / 100);
    $lineaTotal     = $lineaBase + $lineaImpuesto;

    return $lineaTotal;
}



}
