<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Arma el paquete de datos que una terminal de Turion necesita para
 * emparejarse o refrescar su catalogo local: la empresa y sus empleados
 * (con contraseña ya hasheada, para poder loguearse sin internet), roles
 * y permisos, catalogo de productos completo (con codigos alternos,
 * combos, recetas y variantes), configuracion de la empresa, mesas,
 * habitaciones de hotel y mecanicos de taller.
 *
 * Deliberadamente NO incluye historico de facturas/kardex/ordenes/reservas:
 * eso es transaccional y se crea localmente desde cero en cada terminal
 * (ver Fase 4 del plan de Turion) -- este comando solo exporta el estado
 * "de catalogo" que hace falta para operar, tal como esta HOY en el droplet.
 */
class PosExportCatalog extends Command
{
    protected $signature = 'pos:export-catalog
        {empresa : ID de la empresa (users.id con tipo_usuario=empresa)}
        {--output= : Ruta del archivo .json de salida (por defecto storage/app/pos-exports/empresa-{id}.json)}';

    protected $description = 'Arma el paquete JSON de catalogo/emparejamiento que Turion descarga para operar sin conexion.';

    public function handle(): int
    {
        $empresaId = (int) $this->argument('empresa');

        $empresa = DB::table('users')
            ->where('id', $empresaId)
            ->where('tipo_usuario', 'empresa')
            ->first();

        if (! $empresa) {
            $this->error("No existe una empresa con id {$empresaId} (tipo_usuario=empresa).");

            return self::FAILURE;
        }

        $productIds = DB::table('products')
            ->where('empresa_id', $empresaId)
            ->pluck('id');

        $userIds = DB::table('users')
            ->where(fn ($q) => $q->where('id', $empresaId)->orWhere('empresa_id', $empresaId))
            ->pluck('id');

        $paquete = [
            'version' => 1,
            'generado_at' => now()->toIso8601String(),
            'empresa_id' => $empresaId,

            'users' => DB::table('users')->whereIn('id', $userIds)->get(),
            'roles' => DB::table('roles')->get(),
            'permissions' => DB::table('permissions')->get(),
            'role_has_permissions' => DB::table('role_has_permissions')->get(),
            'model_has_roles' => DB::table('model_has_roles')
                ->whereIn('model_id', $userIds)
                ->where('model_type', \App\Models\User::class)
                ->get(),
            'model_has_permissions' => DB::table('model_has_permissions')
                ->whereIn('model_id', $userIds)
                ->where('model_type', \App\Models\User::class)
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

        $json = json_encode($paquete, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $output = $this->option('output') ?: storage_path("app/pos-exports/empresa-{$empresaId}.json");

        if (! is_dir(dirname($output))) {
            mkdir(dirname($output), 0755, true);
        }

        file_put_contents($output, $json);

        $this->info(sprintf(
            'Paquete exportado a %s (%s KB, %d productos, %d usuarios).',
            $output,
            number_format(strlen($json) / 1024, 1),
            $productIds->count(),
            $userIds->count(),
        ));

        return self::SUCCESS;
    }
}
