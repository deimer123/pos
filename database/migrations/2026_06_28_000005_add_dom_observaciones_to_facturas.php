<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('facturas', 'dom_observaciones')) {
            Schema::table('facturas', function (Blueprint $table) {
                $table->text('dom_observaciones')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('facturas', 'dom_observaciones')) {
            Schema::table('facturas', function ($table) {
                $table->dropColumn('dom_observaciones');
            });
        }
    }
};
