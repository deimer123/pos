<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('ordenes_mesas', 'dom_costo_domicilio')) {
            Schema::table('ordenes_mesas', function (Blueprint $table) {
                $table->decimal('dom_costo_domicilio', 10, 2)->default(0);
            });
        }
        if (!Schema::hasColumn('ordenes_mesas', 'dom_costo_desechables')) {
            Schema::table('ordenes_mesas', function (Blueprint $table) {
                $table->decimal('dom_costo_desechables', 10, 2)->default(0);
            });
        }
    }

    public function down(): void
    {
        foreach (['dom_costo_domicilio', 'dom_costo_desechables'] as $col) {
            if (Schema::hasColumn('ordenes_mesas', $col)) {
                Schema::table('ordenes_mesas', fn($t) => $t->dropColumn($col));
            }
        }
    }
};
