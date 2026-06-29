<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('facturas', 'dom_costo_domicilio')) {
            DB::statement("ALTER TABLE facturas ADD COLUMN dom_costo_domicilio DECIMAL(10,2) DEFAULT 0 AFTER costo_empaque");
        }
        if (!Schema::hasColumn('facturas', 'dom_costo_desechables')) {
            DB::statement("ALTER TABLE facturas ADD COLUMN dom_costo_desechables DECIMAL(10,2) DEFAULT 0 AFTER dom_costo_domicilio");
        }
    }

    public function down(): void
    {
        foreach (['dom_costo_domicilio', 'dom_costo_desechables'] as $col) {
            if (Schema::hasColumn('facturas', $col)) {
                Schema::table('facturas', fn($t) => $t->dropColumn($col));
            }
        }
    }
};
