<?php
// filepath: c:\laragon\www\posapp\app\Livewire\PosProductos.php

namespace App\Livewire;

use App\Models\ConfiguracionEmpresa;
use App\Models\Product;
use App\Models\ProductoVariante;
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
        'permite_stock_negativo' => false,
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

    protected $listeners = ['agregarManual'];

    /**
     * La lista de productos ya no se consulta aqui: el navegador la busca
     * localmente sobre el catalogo cacheado (ver resources/js/pos-catalogo-offline.js
     * y GET /pos/catalogo.json). Este componente solo sigue manejando
     * "Agregar al carrito", el producto manual y su modal.
     */
    public function render()
    {
        return view('livewire.pos-productos', [
            'empresaContexto' => $this->empresaContexto,
        ]);
    }

    public function abrirModalProductoManual()
    {
        $this->mostrarModalProductoManual = true;
    }

    public function agregarAlCarrito($idProducto, $varianteId = null, $loteId = null)
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

    if ($varianteId) {
        $variante = ProductoVariante::where('id', $varianteId)
            ->where('product_id', $producto->id)
            ->where('empresa_id', $empresaId)
            ->where('activo', true)
            ->first();

        if (!$variante) {
            $this->dispatch('error', 'Variante no encontrada o no autorizada.');
            return;
        }

        if ($variante->stock <= 0 && ! $this->empresaContexto['permite_stock_negativo']) {
            $this->dispatch('error', 'Esa variante no tiene stock disponible.');
            return;
        }
    }

    if ($loteId) {
        $lote = \App\Models\ProductoLote::where('id', $loteId)
            ->where('product_id', $producto->id)
            ->where('empresa_id', $empresaId)
            ->where('activo', true)
            ->first();

        if (!$lote) {
            $this->dispatch('error', 'Lote no encontrado o no autorizado.');
            return;
        }

        if ($lote->stock <= 0 && ! $this->empresaContexto['permite_stock_negativo']) {
            $this->dispatch('error', 'Ese lote no tiene stock disponible.');
            return;
        }
    }

    $this->dispatch('productoAgregado', $producto->id_producto, $varianteId, $loteId);
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
            'permite_stock_negativo' => (bool) $config->permite_stock_negativo,
        ];
    }
}
