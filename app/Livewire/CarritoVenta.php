<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Product;

class CarritoVenta extends Component
{
    public $carrito = [];

    protected $listeners = ['productoAgregado' => 'agregarProducto'];

    public function agregarProducto($id)
    {
        $producto = Product::find($id);

        if (!$producto) return;

        if (isset($this->carrito[$id])) {
            $this->carrito[$id]['cantidad']++;
        } else {
            $this->carrito[$id] = [
                'id' => $producto->id_producto,
                'nombre' => $producto->descripcion_larga,
                'precio' => $producto->precio_venta1,
                'cantidad' => 1,
                'total' => $producto->precio_venta1,
            ];
        }

        $this->actualizarTotales();
    }

    public function actualizarCantidad($id, $cantidad)
    {
        if (isset($this->carrito[$id])) {
            $this->carrito[$id]['cantidad'] = max(1, intval($cantidad));
            $this->actualizarTotales();
        }
    }

    public function eliminarProducto($id)
    {
        unset($this->carrito[$id]);
    }

    public function actualizarTotales()
    {
        foreach ($this->carrito as $id => $item) {
            $this->carrito[$id]['total'] = $item['cantidad'] * $item['precio'];
        }
    }

    public function render()
    {
        $totalGeneral = collect($this->carrito)->sum('total');

        return view('livewire.carrito-venta', [
            'totalGeneral' => $totalGeneral,
        ]);
    }
}

