<?php

namespace App\Filament\Pages;

use App\Models\Product;
use App\Models\ProductoVariante;
use App\Support\BarTenderExport;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ImprimirEtiquetas extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-tag';
    protected static ?string $navigationGroup = 'Inventario';
    protected static ?string $navigationLabel = 'Imprimir Etiquetas';
    protected static ?string $title = 'Imprimir Etiquetas (BarTender)';
    protected static string $view = 'filament.pages.imprimir-etiquetas';

    public ?array $data = [];

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasAnyRole(['admin_empresa', 'digitador']);
    }

    public function mount(): void
    {
        $this->form->fill([
            'tamano_bartender' => '2x50x25',
            'mostrar_precio_bartender' => 'con_precio',
            'lineas' => [
                ['product_id' => null, 'cantidad' => 1],
            ],
        ]);
    }

    public function form(Form $form): Form
    {
        $empresaId = auth()->user()->getEmpresaActualId();

        return $form
            ->schema([
                Select::make('tamano_bartender')
                    ->label('Tamano de etiqueta')
                    ->options([
                        '2x50x25' => '2 columnas - 50 x 25 mm',
                        '3x32x25' => '3 columnas - 32 x 25 mm',
                    ])
                    ->default('2x50x25')
                    ->required(),

                Select::make('mostrar_precio_bartender')
                    ->label('Precio')
                    ->options([
                        'con_precio' => 'Con precio',
                        'sin_precio' => 'Sin precio',
                    ])
                    ->default('con_precio')
                    ->required(),

                Repeater::make('lineas')
                    ->label('Productos')
                    ->schema([
                        Select::make('product_id')
                            ->label('Producto (codigo o nombre)')
                            ->searchable()
                            ->getSearchResultsUsing(function (string $search) use ($empresaId) {
                                return Product::query()
                                    ->where('empresa_id', $empresaId)
                                    ->where(function ($query) use ($search) {
                                        $query->where('id_producto', 'like', "%{$search}%")
                                            ->orWhere('descripcion_larga', 'like', "%{$search}%");
                                    })
                                    ->limit(30)
                                    ->get()
                                    ->mapWithKeys(fn ($p) => [$p->id_producto => "{$p->id_producto} - {$p->descripcion_larga}"]);
                            })
                            ->getOptionLabelUsing(function ($value) use ($empresaId) {
                                $producto = Product::where('empresa_id', $empresaId)
                                    ->where('id_producto', $value)
                                    ->first();

                                return $producto ? "{$producto->id_producto} - {$producto->descripcion_larga}" : $value;
                            })
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('producto_variante_id', null))
                            ->required(),

                        Select::make('producto_variante_id')
                            ->label('Variante (talla/color)')
                            ->visible(fn (Get $get) => static::productoTieneVariantes($get))
                            ->options(fn (Get $get) => static::opcionesVariantes($get))
                            ->required(fn (Get $get) => static::productoTieneVariantes($get))
                            ->validationMessages(['required' => 'Selecciona la variante (talla/color).'])
                            ->columnSpanFull(),

                        TextInput::make('cantidad')
                            ->label('Cantidad')
                            ->numeric()
                            ->minValue(1)
                            ->default(1)
                            ->required(),
                    ])
                    ->columns(2)
                    ->addActionLabel('Agregar producto')
                    ->defaultItems(1)
                    ->reorderable(false)
                    ->required(),
            ])
            ->statePath('data');
    }

    /* ----------------- Variantes (talla/color) ----------------- */
    protected static function productoTieneVariantes(Get $get): bool
    {
        $codigo = (string) ($get('product_id') ?? '');
        if ($codigo === '') {
            return false;
        }

        $producto = Product::where('empresa_id', auth()->user()->getEmpresaActualId())
            ->where('id_producto', $codigo)
            ->first();

        if (! $producto) {
            return false;
        }

        return ProductoVariante::where('product_id', $producto->id)->where('activo', true)->exists();
    }

    protected static function opcionesVariantes(Get $get): array
    {
        $codigo = (string) ($get('product_id') ?? '');
        if ($codigo === '') {
            return [];
        }

        $producto = Product::where('empresa_id', auth()->user()->getEmpresaActualId())
            ->where('id_producto', $codigo)
            ->first();

        if (! $producto) {
            return [];
        }

        return ProductoVariante::where('product_id', $producto->id)
            ->where('activo', true)
            ->orderBy('nombre')
            ->pluck('nombre', 'id')
            ->toArray();
    }

    public function generar()
    {
        $data = $this->form->getState();
        $empresaId = auth()->user()->getEmpresaActualId();

        $lineas = [];
        $codigosNoEncontrados = [];

        foreach ($data['lineas'] ?? [] as $linea) {
            $producto = Product::where('empresa_id', $empresaId)
                ->where('id_producto', $linea['product_id'] ?? null)
                ->first();

            if (! $producto) {
                $codigosNoEncontrados[] = $linea['product_id'] ?? '?';
                continue;
            }

            // 🎨 Si se eligio una variante puntual, el codigo de barras es
            // el de la variante (el que va impreso/escaneado), no el del
            // producto padre -- si esa variante no tiene codigo propio, se
            // cae al del producto para no imprimir un codigo vacio.
            $variante = ! empty($linea['producto_variante_id'])
                ? ProductoVariante::where('id', $linea['producto_variante_id'])
                    ->where('product_id', $producto->id)
                    ->first()
                : null;

            $lineas[] = [
                'product_id'      => $variante?->codigo ?: $producto->id_producto,
                'nombre_producto' => $variante ? $producto->descripcion_larga . ' - ' . $variante->nombre : $producto->descripcion_larga,
                'cantidad'        => $linea['cantidad'] ?? 1,
                'precio_venta'    => $producto->precio_venta1,
            ];
        }

        if ($codigosNoEncontrados) {
            Notification::make()
                ->title('Algunos codigos no se encontraron')
                ->body(implode(', ', $codigosNoEncontrados))
                ->warning()
                ->send();
        }

        if (! $lineas) {
            Notification::make()
                ->title('Agrega al menos un producto valido')
                ->danger()
                ->send();

            return null;
        }

        return BarTenderExport::generarBat(
            $lineas,
            $data['tamano_bartender'] ?? '2x50x25',
            $data['mostrar_precio_bartender'] ?? 'con_precio',
            'manual_' . now()->format('YmdHis'),
        );
    }
}
