<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('ordenes_mesas', 'dom_observaciones')) {
            DB::statement("ALTER TABLE ordenes_mesas ADD COLUMN dom_observaciones TEXT NULL");
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
