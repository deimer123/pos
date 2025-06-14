<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Product;
use Illuminate\Support\Collection;

class PosProductos extends Component
{
    public $search = '';

    public function render()
    {
        $query = Product::query()
    ->when($this->search, function ($q) {
        $busqueda = '%' . str_replace(' ', '%', $this->search) . '%';

        $q->where(function ($q) use ($busqueda) {
            $q->where('descripcion_larga', 'like', $busqueda)
              ->orWhere('id_producto', 'like', $busqueda)
              ->orWhereHas('alternateCodes', function ($subq) use ($busqueda) {
                  $subq->where('code', 'like', $busqueda);
              });
        });
    })
    ->orderByRaw('existencias > 0 DESC') // 👈 stock mayor que cero primero
    ->orderByDesc('existencias')
    ->with('alternateCodes')
    ->take(40)
    ->get();


        return view('livewire.pos-productos', [
            'products' => $query,
        ]);
    }


    public function agregarAlCarrito($id)
{
    $this->dispatch('productoAgregado', id: $id);
}
    
}
