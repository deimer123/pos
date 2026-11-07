<?php

namespace App\Services\Sucursales;

use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Support\Collection;

// Sucursal con la que esta trabajando el usuario en esta sesion.
//  - El admin de la empresa puede elegir una sucursal o "Todas" (null).
//  - Un empleado solo ve las sucursales que tiene asignadas y nunca "Todas".
//  - Empresa sin sucursales: todo devuelve vacio/null (comportamiento de siempre).
class SucursalActual
{
    public const SESSION_KEY = 'sucursal_actual_id';

    public function usaSucursales(User $user): bool
    {
        return $user->empresaUsaSucursales();
    }

    public function veTodas(User $user): bool
    {
        return $user->tipo_usuario === 'empresa' || $user->hasRole('admin_empresa');
    }

    /** @return Collection<int, Sucursal> */
    public function opciones(User $user): Collection
    {
        if (! $this->usaSucursales($user)) {
            return collect();
        }

        $empresaId = $user->getEmpresaActualId();

        if ($this->veTodas($user)) {
            return Sucursal::deEmpresa($empresaId)->activas()->orderByDesc('es_principal')->orderBy('nombre')->get();
        }

        return $user->sucursalesAsignadas()
            ->where('sucursales.empresa_id', $empresaId)
            ->where('sucursales.activo', true)
            ->orderByDesc('es_principal')
            ->orderBy('nombre')
            ->get();
    }

    // null = "Todas" (solo admin) o empresa sin sucursales.
    public function actual(User $user): ?Sucursal
    {
        $opciones = $this->opciones($user);

        if ($opciones->isEmpty()) {
            return null;
        }

        $id = session(self::SESSION_KEY);

        if ($id === 'todas' && $this->veTodas($user)) {
            return null;
        }

        return $opciones->firstWhere('id', (int) $id) ?? $opciones->first();
    }

    // false si el usuario no puede usar esa sucursal.
    public function establecer(User $user, string|int|null $valor): bool
    {
        if ($valor === 'todas') {
            if (! $this->veTodas($user)) {
                return false;
            }

            session([self::SESSION_KEY => 'todas']);

            return true;
        }

        $sucursal = $this->opciones($user)->firstWhere('id', (int) $valor);

        if (! $sucursal) {
            return false;
        }

        session([self::SESSION_KEY => $sucursal->id]);

        return true;
    }
}
