<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Igual que 2026_11_06_000004 pero para las tablas de mesas/cocina: cada una
// recibe un sucursal_id NULLABLE e indexado, sin llave foranea y sin ->after().
// NULL = comportamiento de siempre. Se salta lo que ya exista.
return new class extends Migration
{
    private const TABLAS = ['mesas', 'ordenes_mesas', 'orden_mesa_items'];

    public function up(): void
    {
        foreach (self::TABLAS as $tabla) {
            if (! Schema::hasTable($tabla) || Schema::hasColumn($tabla, 'sucursal_id')) {
                continue;
            }

            Schema::table($tabla, function (Blueprint $table) {
                $table->unsignedBigInteger('sucursal_id')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLAS as $tabla) {
            if (! Schema::hasTable($tabla) || ! Schema::hasColumn($tabla, 'sucursal_id')) {
                continue;
            }

            Schema::table($tabla, function (Blueprint $table) use ($tabla) {
                $table->dropIndex($tabla . '_sucursal_id_index');
                $table->dropColumn('sucursal_id');
            });
        }
    }
};
