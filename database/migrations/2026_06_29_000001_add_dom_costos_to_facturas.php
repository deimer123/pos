<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('facturas', 'dom_costo_domicilio')) {
            Schema::table('facturas', function (Blueprint $table) {
                $table->decimal('dom_costo_domicilio', 10, 2)->default(0)->after('costo_empaque');
            });
        }
        if (!Schema::hasColumn('facturas', 'dom_costo_desechables')) {
            Schema::table('facturas', function (Blueprint $table) {
                $table->decimal('dom_costo_desechables', 10, 2)->default(0)->after('dom_costo_domicilio');
            });
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
