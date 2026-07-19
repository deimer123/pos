<?php

namespace App\Services\Turion;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Arma el paquete de datos que una terminal de Turion necesita para
 * emparejarse o refrescar su catalogo local. Usado tanto por el comando
 * "php artisan pos:export-catalog" (exportacion manual a archivo) como por
 * PairingController::bootstrap() (descarga directa via API al emparejar).
 *
 * Deliberadamente no incluye historico de facturas/kardex/ordenes/reservas:
 * eso es transaccional y se crea localmente desde cero en cada terminal.
 */
class CatalogoExporter
{
    public function paraEmpresa(int $empresaId): array
    {
        $productIds = DB::table('products')->where('empresa_id', $empresaId)->pluck('id');

        $userIds = DB::table('users')
            ->where(fn ($q) => $q->where('id', $empresaId)->orWhere('empresa_id', $empresaId))
            ->pluck('id');

        return [
            'version' => 1,
            'generado_at' => now()->toIso8601String(),
            'empresa_id' => $empresaId,

            'users' => DB::table('users')->whereIn('id', $userIds)->get(),
            'roles' => DB::table('roles')->get(),
            'permissions' => DB::table('permissions')->get(),
            'role_has_permissions' => DB::table('role_has_permissions')->get(),
            'model_has_roles' => DB::table('model_has_roles')
                ->whereIn('model_id', $userIds)
                ->where('model_type', User::class)
                ->get(),
            'model_has_permissions' => DB::table('model_has_permissions')
                ->whereIn('model_id', $userIds)
                ->where('model_type', User::class)
                ->get(),

            'configuracion_empresas' => DB::table('configuracion_empresas')->where('empresa_id', $empresaId)->get(),
            'cuentas_contables' => DB::table('cuentas_contables')->where('empresa_id', $empresaId)->get(),
            'actors' => DB::table('actors')->where('empresa_id', $empresaId)->get(),

            'products' => DB::table('products')->where('empresa_id', $empresaId)->get(),
            'alternate_codes' => DB::table('alternate_codes')->whereIn('product_id', $productIds)->get(),
            'product_combos' => DB::table('product_combos')->where('empresa_id', $empresaId)->get(),
            'producto_variantes' => DB::table('producto_variantes')->where('empresa_id', $empresaId)->get(),
            'recetas' => DB::table('recetas')->where('empresa_id', $empresaId)->get(),
            'receta_items' => DB::table('receta_items')->where('empresa_id', $empresaId)->get(),

            'mesas' => DB::table('mesas')->where('empresa_id', $empresaId)->get(),
            'hotel_habitaciones' => DB::table('hotel_habitaciones')->where('empresa_id', $empresaId)->get(),
            'mecanicos' => DB::table('mecanicos')->where('empresa_id', $empresaId)->get(),
        ];
    }
}
