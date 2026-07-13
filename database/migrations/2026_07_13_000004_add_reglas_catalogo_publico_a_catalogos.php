<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalogos', function (Blueprint $table) {
            $table->boolean('mostrar_foto')->default(true)->after('tipo_seleccion');
            $table->boolean('mostrar_precio')->default(true)->after('mostrar_foto');
            $table->boolean('mostrar_disponibilidad')->default(false)->after('mostrar_precio');
            $table->boolean('mostrar_stock_positivo')->default(true)->after('mostrar_disponibilidad');
            $table->boolean('mostrar_stock_negativo')->default(true)->after('mostrar_stock_positivo');
        });
    }

    public function down(): void
    {
        Schema::table('catalogos', function (Blueprint $table) {
            $table->dropColumn([
                'mostrar_foto',
                'mostrar_precio',
                'mostrar_disponibilidad',
                'mostrar_stock_positivo',
                'mostrar_stock_negativo',
            ]);
        });
    }
};
