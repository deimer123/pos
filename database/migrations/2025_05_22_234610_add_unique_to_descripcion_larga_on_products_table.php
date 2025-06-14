<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Añade índice UNIQUE sobre la columna descripcion_larga
            $table->unique('descripcion_larga');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Elimina el índice UNIQUE al revertir la migración
            $table->dropUnique(['descripcion_larga']);
        });
    }
};
