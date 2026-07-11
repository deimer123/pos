<?php
// filepath: c:\laragon\www\posapp\app\Livewire\PosProductos.php

namespace App\Livewire;

use App\Models\ConfiguracionEmpresa;
use App\Models\Product;
use Livewire\Component;

class PosProductos extends Component
{
    public array $empresaContexto = [
        'tipo_negocio' => 'tienda',
        'usa_peso' => false,
        'usa_variantes' => false,
        'usa_recetas' => false,
        'usa_servicios' => false,
        'nombre_empresa' => null,
        'puede_ver_stock' => true,
    ];

    private function textoUtf8($valor): string
    {
        $texto = (string) ($valor ?? '');

        if ($texto === '') {
            return '';
        }

        if (function_exists('mb_check_encoding') && mb_check_encoding($texto, 'UTF-8')) {
            return $texto;
        }

        if (function_exists('mb_convert_encoding')) {
            return mb_convert_encoding($texto, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
        }

        return iconv('Windows-1252', 'UTF-8//IGNORE', $texto) ?: '';
    }
    public $search = '';
    public $filtroTipo = '';
    public $mostrarModal = false;
    public $mostrarModalProductoManual = false;
    public $productoTemporal = [
        'nombre' => '',
        'precio' => '',
    ];
    
    protected $rules = [
        'productoTemporal.nombre' => 'required|string|min:2',
        'productoTemporal.precio' => 'required|numeric|min:1',
    ];

    public function mount()
    {
        $this->mostrarModalProductoManual = false;
        $this->productoTemporal = [
            'nombre' => '',
            'precio' => '',
        ];
        $this->cargarContextoEmpresa();
    }

    protected $listeners = ['limpiar-input-busqueda' => 'limpiarBusqueda', 'agregarManual'];

    public function render()
    {
        $user = auth()->user();
        
        // ✅ OBTENER EL empresa_id CORRECTO
        $empresaId = $this->getEmpresaId($user);
        
        $busqueda = '%' . str_replace(' ', '%', $this->search) . '%';

        $query = Product::query()
            ->where('empresa_id', $empresaId)
            ->where('id_producto', '!=', '10001')
            ->when($this->filtroTipo, function ($q) {
                if ($this->filtroTipo === 'receta') {
                    $q->whereHas('recetas', fn($r) => $r->where('activo', true));
                } elseif ($this->filtroTipo === 'combo') {
                    $q->whereHas('combos');
                } else {
                    $q->where('tipo_producto', $this->filtroTipo);
                }
            })
            ->when($this->search, function ($q) use ($busqueda) {
                $q->where(function ($q) use ($busqueda) {
                    $q->where('descripcion_larga', 'like', $busqueda)
                        ->orWhere('id_producto', 'like', $busqueda)
                        ->orWhereHas('alternateCodes', function ($subq) use ($busqueda) {
                            $subq->where('code', 'like', $busqueda);
                        });
                });
            })
            ->orderByRaw('existencias > 0 DESC') // 🟢 primero los que tienen stock
            ->orderByDesc('existencias')         // 🔽 luego por mayor cantidad
            ->with(['alternateCodes', 'mecanico'])
            ->withCount('variantes')
            ->take(40)
            ->get()
            ->map(function ($product) {
                $product->descripcion_larga = $this->textoUtf8($product->descripcion_larga);
                $product->foto = $this->textoUtf8($product->foto);

                return $product;
            });

        return view('livewire.pos-productos', [
            'products' => $query,
            'empresaContexto' => $this->empresaContexto,
        ]);
    }

    public function agregarAlCarrito($idProducto)
{
    $user = auth()->user();
    $empresaId = $this->getEmpresaId($user);

    $producto = Product::where('id_producto', $idProducto)
        ->where('empresa_id', $empresaId)
        ->first();

    if (!$producto) {
        session()->flash('error', 'Producto no encontrado o no autorizado.');
        return;
    }

    $this->dispatch('productoAgregado', $producto->id_producto);
}

public function updatedSearch($value)
{
    if ($value === '10001') {
        $this->mostrarModalProductoManual = true;
        $this->dispatch('limpiar-input-busqueda');
        $this->search = '';
        return;
    }

    $user = auth()->user();
    $empresaId = $this->getEmpresaId($user);
    
    $producto = Product::where('empresa_id', $empresaId)
        ->where(function ($q) use ($value) {
            $q->where('id_producto', $value)
                ->orWhereHas('alternateCodes', function ($subq) use ($value) {
                    $subq->where('code', $value);
                });
        })
        ->first();

    if ($producto) {
        // ✅ ENVIAR SOLO EL id_producto como parámetro directo
       $this->dispatch('productoAgregado', $producto->id_producto);
        $this->dispatch('limpiar-input-busqueda');
        $this->search = '';
    }
}
    
    public function limpiarBusqueda()
    {
        $this->search = '';
    }

    public function agregarProductoTemporal()
    {
        $this->validate(
            [
                'productoTemporal.nombre' => 'required|string|min:2',
                'productoTemporal.precio' => 'required|numeric|min:1',
            ],
            [
                'productoTemporal.nombre.required' => 'El nombre del producto es obligatorio.',
                'productoTemporal.nombre.min' => 'El nombre debe tener al menos 2 caracteres.',
                'productoTemporal.precio.required' => 'El precio del producto es obligatorio.',
                'productoTemporal.precio.numeric' => 'El precio debe ser un número válido.',
                'productoTemporal.precio.min' => 'El precio debe ser mayor a 0.',
            ]
        );

        $nombre = trim($this->productoTemporal['nombre']);
        $precio = floatval($this->productoTemporal['precio']);

        $this->dispatch('agregarManual', [
            'codigo' => '10001',
            'nombre' => $nombre,
            'precio' => $precio,
        ])->to('carrito-venta');

        $this->mostrarModalProductoManual = false;
        $this->reset('productoTemporal');
    }

    // ✅ MÉTODO HELPER PARA OBTENER EL empresa_id CORRECTO
    private function getEmpresaId($user)
    {
        // Si es admin de empresa, usar su propio ID
        if ($user->hasRole('admin_empresa')) {
            return $user->id;
        }

        // Para cajero, vendedor u otro empleado, usar el empresa_id asignado
        if (!empty($user->empresa_id)) {
            return $user->empresa_id;
        }

        // Fallback: usar el ID del usuario si no hay empresa_id
        return $user->id;
    }

    private function cargarContextoEmpresa(): void
    {
        $user = auth()->user();

        if (! $user) {
            return;
        }

        $empresaId = $this->getEmpresaId($user);
        $config = ConfiguracionEmpresa::query()
            ->where('empresa_id', $empresaId)
            ->first();

        if (! $config) {
            return;
        }

        $this->empresaContexto = [
            'tipo_negocio' => (string) ($config->tipo_negocio ?: 'tienda'),
            'usa_peso' => (bool) $config->usa_peso,
            'usa_variantes' => (bool) $config->usa_variantes,
            'usa_recetas' => (bool) $config->usa_recetas,
            'usa_servicios' => (bool) $config->usa_servicios || (bool) $config->usa_taller,
            'nombre_empresa' => $this->textoUtf8($config->nombre_empresa),
            'puede_ver_stock' => $user->hasRole('admin_empresa') || (bool) ($config->permite_ver_stock_no_admin ?? true),
        ];
    }
}
