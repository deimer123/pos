<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('products', 'cuenta_contable_id')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('cuenta_contable_id')
                ->nullable()
                ->after('empresa_id')
                ->index();
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('products', 'cuenta_contable_id')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            try {
                $table->dropForeign(['cuenta_contable_id']);
            } catch (\Throwable $e) {
                // Si la FK no existe, seguimos con el drop de la columna.
            }

            $table->dropColumn('cuenta_contable_id');
        });
    }
};
