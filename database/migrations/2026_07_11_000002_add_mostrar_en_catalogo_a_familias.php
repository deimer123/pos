<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('familias', function (Blueprint $table) {
            $table->boolean('mostrar_en_catalogo')->default(true)->after('nombre');
        });

        Schema::table('subfamilias', function (Blueprint $table) {
            $table->boolean('mostrar_en_catalogo')->default(true)->after('nombre');
        });
    }

    public function down(): void
    {
        Schema::table('familias', function (Blueprint $table) {
            $table->dropColumn('mostrar_en_catalogo');
        });

        Schema::table('subfamilias', function (Blueprint $table) {
            $table->dropColumn('mostrar_en_catalogo');
        });
    }
};
