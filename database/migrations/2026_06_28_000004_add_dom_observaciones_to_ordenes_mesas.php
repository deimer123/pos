<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Reescrita con Schema::table() (antes usaba DB::statement, aunque para
// esta columna en particular no tenia sintaxis MySQL-only real -- se deja
// consistente con el resto de migraciones de esta tanda).
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('ordenes_mesas', 'dom_observaciones')) {
            Schema::table('ordenes_mesas', function (Blueprint $table) {
                $table->text('dom_observaciones')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('ordenes_mesas', 'dom_observaciones')) {
            Schema::table('ordenes_mesas', function ($table) {
                $table->dropColumn('dom_observaciones');
            });
        }
    }
};
