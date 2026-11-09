<?php

namespace App\Filament\Pages;

use App\Services\Grupo\ConsultaInventarioGrupo;
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
        return app(ConsultaInventarioGrupo::class)->empresas(auth()->user());
    }

    public function filas(array $empresas): LengthAwarePaginator
    {
        return app(ConsultaInventarioGrupo::class)->query($empresas, $this->buscar)->paginate(25);
    }

    protected function getViewData(): array
    {
        $empresas = $this->empresasDelGrupo();

        return [
            'empresas' => $empresas,
            'propia' => auth()->user()->getEmpresaActualId(),
            'filas' => $this->filas($empresas),
            'verStock' => true,
        ];
    }
}
