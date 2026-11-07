<?php

namespace App\Filament\Pages;

use App\Models\Product;
use App\Models\User;
use App\Services\Grupo\GrupoEmpresas;
use Filament\Pages\Page;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\WithPagination;

// Inventario del grupo (matriz + sucursales), SOLO LECTURA: una fila por
// producto (emparejado por nombre exacto, que es unico dentro de cada empresa)
// y una columna de existencias por empresa del grupo. No muestra nada de
// empresas que no son del grupo y no permite editar.
class InventarioGrupo extends Page
{
    use WithPagination;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';
    protected static ?string $navigationGroup = 'Reportes';
    protected static ?string $navigationLabel = 'Inventario del grupo';
    protected static ?string $title = 'Inventario del grupo';
    protected static ?string $slug = 'inventario-del-grupo';
    protected static ?int $navigationSort = 2;
    protected static string $view = 'filament.pages.inventario-grupo';

    public string $buscar = '';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user
            && $user->hasRole('admin_empresa')
            && count(app(GrupoEmpresas::class)->idsDelGrupo($user->empresaPrincipal())) > 1;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function updatedBuscar(): void
    {
        $this->resetPage();
    }

    // [id => nombre] de las empresas del grupo, la propia primero.
    public function empresasDelGrupo(): array
    {
        $propia = auth()->user()->getEmpresaActualId();
        $ids = app(GrupoEmpresas::class)->idsDelGrupo(auth()->user()->empresaPrincipal());
        $nombres = User::whereIn('id', $ids)->pluck('name', 'id')->all();

        $ordenados = [$propia => $nombres[$propia] ?? 'Mi empresa'];

        foreach ($ids as $id) {
            if ($id !== $propia) {
                $ordenados[$id] = $nombres[$id] ?? "Empresa {$id}";
            }
        }

        return $ordenados;
    }

    public function filas(array $empresas): LengthAwarePaginator
    {
        $ids = array_map('intval', array_keys($empresas));

        $query = Product::query()
            ->whereIn('empresa_id', $ids)
            ->when(trim($this->buscar) !== '', fn ($q) => $q->where('descripcion_larga', 'like', '%' . trim($this->buscar) . '%'))
            ->selectRaw('MIN(descripcion_larga) as nombre');

        foreach ($ids as $id) {
            $query->selectRaw("SUM(CASE WHEN empresa_id = {$id} THEN existencias END) as existencias_{$id}");
            $query->selectRaw("MAX(CASE WHEN empresa_id = {$id} THEN precio_venta1 END) as precio_{$id}");
        }

        return $query
            ->groupByRaw('TRIM(descripcion_larga)')
            ->orderByRaw('TRIM(descripcion_larga)')
            ->paginate(25);
    }

    protected function getViewData(): array
    {
        $empresas = $this->empresasDelGrupo();

        return [
            'empresas' => $empresas,
            'propia' => auth()->user()->getEmpresaActualId(),
            'filas' => $this->filas($empresas),
        ];
    }
}
