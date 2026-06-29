<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('ordenes_mesas', 'dom_costo_domicilio')) {
            DB::statement("ALTER TABLE ordenes_mesas ADD COLUMN dom_costo_domicilio DECIMAL(10,2) DEFAULT 0");
        }
        if (!Schema::hasColumn('ordenes_mesas', 'dom_costo_desechables')) {
            DB::statement("ALTER TABLE ordenes_mesas ADD COLUMN dom_costo_desechables DECIMAL(10,2) DEFAULT 0");
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
