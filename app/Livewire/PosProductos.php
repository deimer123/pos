<?php
// filepath: c:\laragon\www\posapp\app\Livewire\PosProductos.php

namespace App\Livewire;

use App\Models\Product;
use Livewire\Component;

class PosProductos extends Component
{
    public $search = '';
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
    }

    protected $listeners = ['limpiar-input-busqueda' => 'limpiarBusqueda', 'agregarManual'];

    public function render()
    {
        $user = auth()->user();
        
        // ✅ OBTENER EL empresa_id CORRECTO
        $empresaId = $this->getEmpresaId($user);
        
        $busqueda = '%' . str_replace(' ', '%', $this->search) . '%';

        $query = Product::query()
            // ✅ FILTRAR POR empresa_id (NO por $user->id)
            ->where('empresa_id', $empresaId)
            ->where('id_producto', '!=', '10001') // excluimos código temporal
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
            ->with('alternateCodes')
            ->take(40)
            ->get();

        return view('livewire.pos-productos', [
            'products' => $query,
        ]);
    }

    public function agregarAlCarrito($idProducto)
{
    $user = auth()->user();
    $empresaId = $this->getEmpresaId($user);
    
    $producto = Product::where('id_producto', $idProducto)
        ->where('empresa_id', $empresaId)
        ->first();

    if ($producto) {
        // ✅ ENVIAR SOLO EL id_producto como parámetro directo
        $this->dispatch('productoAgregado', $producto->id_producto);
    } else {
        session()->flash('error', 'Producto no encontrado o no autorizado.');
    }
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
        $this->dispatch('agregarProductoAlCarrito', $producto->id_producto);
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
        // Si es admin de empresa (tiene productos), usar su ID
        if ($user->hasRole('admin_empresa')) {
            return $user->id;
        }
        
        // Si es vendedor, usar el empresa_id del campo
        if ($user->hasRole('vendedor') && !empty($user->empresa_id)) {
            return $user->empresa_id;
        }
        
        // Fallback: usar el ID del usuario
        return $user->id;
    }
}