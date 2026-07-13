<?php

namespace App\Livewire;

use App\Models\Catalogo;
use App\Models\ConfiguracionEmpresa;
use App\Models\Familia;
use App\Models\Product;
use Livewire\Component;

class CatalogoPublico extends Component
{
    public string $slug;
    public ?int $familiaActiva = null;
    public string $busqueda = '';

    public function mount(string $slug): void
    {
        $this->slug = $slug;
    }

    public function getCatalogoProperty(): ?Catalogo
    {
        return Catalogo::where('slug', $this->slug)->where('activo', true)->first();
    }

    public function getConfigProperty(): ConfiguracionEmpresa
    {
        if ($this->catalogo) {
            return ConfiguracionEmpresa::where('empresa_id', $this->catalogo->empresa_id)->firstOrFail();
        }

        return ConfiguracionEmpresa::where('slug', $this->slug)->firstOrFail();
    }

    public function title(): string
    {
        $titulo = 'Catálogo de Productos - ' . ($this->config->nombre_empresa ?? '');

        return $this->catalogo ? $titulo . ' - ' . $this->catalogo->nombre : $titulo;
    }

    public function getProductosProperty()
    {
        $palabras = array_filter(preg_split('/\s+/', trim($this->busqueda)));

        return Product::where('empresa_id', $this->config->empresa_id)
            ->where('mostrar_en_catalogo', true)
            ->whereHas('familia1', fn ($q) => $q->where('mostrar_en_catalogo', true))
            ->where(function ($q) {
                $q->whereNull('id_familia2')
                    ->orWhereHas('subfamilia', fn ($qq) => $qq->where('mostrar_en_catalogo', true));
            })
            ->when($this->catalogo, fn ($q) => $q->whereHas('catalogos', fn ($qq) => $qq->where('catalogos.id', $this->catalogo->id)))
            ->when(! $this->config->catalogo_mostrar_stock_positivo && $this->config->catalogo_mostrar_stock_negativo, fn ($q) => $q->where('existencias', '<=', 0))
            ->when($this->config->catalogo_mostrar_stock_positivo && ! $this->config->catalogo_mostrar_stock_negativo, fn ($q) => $q->where('existencias', '>', 0))
            ->when(! $this->config->catalogo_mostrar_stock_positivo && ! $this->config->catalogo_mostrar_stock_negativo, fn ($q) => $q->whereRaw('1 = 0'))
            ->when(! empty($palabras), function ($q) use ($palabras) {
                foreach ($palabras as $palabra) {
                    $q->where('descripcion_larga', 'like', '%' . $palabra . '%');
                }
            })
            ->with(['familia1', 'subfamilia'])
            ->orderBy('descripcion_larga')
            ->get()
            ->groupBy('id_familia1');
    }

    public function getFamiliasProperty()
    {
        return Familia::where('empresa_id', $this->config->empresa_id)
            ->whereIn('id', $this->productos->keys())
            ->orderBy('nombre')
            ->get();
    }

    public function getProductosFiltradosProperty()
    {
        if (! $this->familiaActiva) {
            return $this->productos;
        }

        return $this->productos->only([$this->familiaActiva]);
    }

    public function render()
    {
        return view('livewire.catalogo-publico')->layout('layouts.catalogo');
    }
}
