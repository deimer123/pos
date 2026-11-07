<?php

namespace App\Services\Grupo;

use App\Models\User;

// Reglas del grupo de empresas (matriz + sucursales). Una sucursal es una
// empresa normal con empresa_matriz_id apuntando a la empresa del dueno.
class GrupoEmpresas
{
    // null = se puede ligar $empresa como sucursal de $matriz; si no, el
    // motivo en texto para mostrarselo al super_admin.
    public function motivoNoPuedeSerSucursal(User $empresa, ?User $matriz): ?string
    {
        if ($matriz === null) {
            return null; // quitar la liga siempre es valido
        }

        if (! $empresa->esEmpresa() || ! $matriz->esEmpresa()) {
            return 'Solo las empresas pueden ser matriz o sucursal.';
        }

        if ($empresa->id === $matriz->id) {
            return 'Una empresa no puede ser sucursal de sí misma.';
        }

        if ($matriz->empresa_matriz_id !== null) {
            return 'La empresa matriz elegida ya es sucursal de otra; no se permiten cadenas.';
        }

        if ($empresa->exists && $empresa->sucursales()->exists()) {
            return 'Esta empresa ya tiene sucursales; no puede ser sucursal de otra.';
        }

        if ($empresa->tipo_edicion !== 'online' || $matriz->tipo_edicion !== 'online') {
            return 'Las sucursales solo están disponibles entre empresas de la edición Online.';
        }

        return null;
    }

    // Validacion de lo que se esta guardando en el formulario de empresas:
    // la edicion elegida y la matriz elegida (null = independiente). Sirve
    // para empresas nuevas (new User) y existentes.
    public function motivoGuardado(User $empresa, string $edicion, ?int $matrizId): ?string
    {
        if ($matrizId === null) {
            if ($edicion !== 'online' && $empresa->exists && $empresa->sucursales()->exists()) {
                return 'Esta empresa tiene sucursales, que solo existen en la edición Online.';
            }

            return null;
        }

        $matriz = User::find($matrizId);

        if (! $matriz) {
            return 'La empresa matriz elegida no existe.';
        }

        $candidata = (clone $empresa)->forceFill(['tipo_edicion' => $edicion]);

        return $this->motivoNoPuedeSerSucursal($candidata, $matriz);
    }

    // Empresas que se pueden elegir como matriz en el formulario.
    public function opcionesMatriz(?User $empresa = null): array
    {
        return User::query()
            ->where('tipo_usuario', 'empresa')
            ->where('tipo_edicion', 'online')
            ->whereNull('empresa_matriz_id')
            ->role('admin_empresa')
            ->when($empresa?->exists, fn ($q) => $q->where('users.id', '!=', $empresa->id))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    // Ids de todas las empresas del grupo (matriz + sucursales) al que
    // pertenece $empresa. Una empresa independiente devuelve solo su id.
    public function idsDelGrupo(User $empresa): array
    {
        $matrizId = $empresa->empresa_matriz_id ?? $empresa->id;

        return User::query()
            ->where('tipo_usuario', 'empresa')
            ->where(fn ($q) => $q->where('id', $matrizId)->orWhere('empresa_matriz_id', $matrizId))
            ->orderBy('id')
            ->pluck('id')
            ->all();
    }
}
