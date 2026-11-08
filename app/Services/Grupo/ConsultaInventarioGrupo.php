<?php

namespace App\Services\Grupo;

use App\Models\ConfiguracionEmpresa;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

// Consulta de SOLO LECTURA del inventario de las empresas del grupo (matriz +
// sucursales). Las empresas salen siempre del usuario autenticado en el
// servidor, nunca de datos que mande el navegador.
class ConsultaInventarioGrupo
{
    public function __construct(private GrupoEmpresas $grupo)
    {
    }

    public function idsDelGrupo(User $user): array
    {
        return $this->grupo->idsDelGrupo($user->empresaPrincipal());
    }

    // true solo si el usuario pertenece a un grupo con mas de una empresa.
    public function disponible(User $user): bool
    {
        return count($this->idsDelGrupo($user)) > 1;
    }

    // Misma regla que el POS: el admin siempre ve el stock; los demas segun
    // la configuracion de su empresa (permite_ver_stock_no_admin).
    public function puedeVerStock(User $user): bool
    {
        if ($user->hasRole('admin_empresa')) {
            return true;
        }

        $config = ConfiguracionEmpresa::where('empresa_id', $user->getEmpresaActualId())->first();

        return (bool) ($config?->permite_ver_stock_no_admin ?? true);
    }

    // [id => nombre] de las empresas del grupo, la propia primero.
    public function empresas(User $user): array
    {
        $propia = $user->getEmpresaActualId();
        $ids = $this->idsDelGrupo($user);
        $nombres = User::whereIn('id', $ids)->pluck('name', 'id')->all();

        $ordenados = [$propia => $nombres[$propia] ?? 'Mi empresa'];

        foreach ($ids as $id) {
            if ($id !== $propia) {
                $ordenados[$id] = $nombres[$id] ?? "Empresa {$id}";
            }
        }

        return $ordenados;
    }

    // Una fila por producto (nombre exacto, unico por empresa) con
    // existencias_{id} y precio_{id} de cada empresa. Solo esas dos cifras.
    public function query(array $empresas, string $buscar = ''): Builder
    {
        $ids = array_map('intval', array_keys($empresas));

        $query = Product::query()
            ->whereIn('empresa_id', $ids)
            ->when(trim($buscar) !== '', fn ($q) => $q->where('descripcion_larga', 'like', '%' . trim($buscar) . '%'))
            ->selectRaw('MIN(descripcion_larga) as nombre');

        foreach ($ids as $id) {
            $query->selectRaw("SUM(CASE WHEN empresa_id = {$id} THEN existencias END) as existencias_{$id}");
            $query->selectRaw("MAX(CASE WHEN empresa_id = {$id} THEN precio_venta1 END) as precio_{$id}");
        }

        return $query
            ->groupByRaw('TRIM(descripcion_larga)')
            ->orderByRaw('TRIM(descripcion_larga)');
    }
}
