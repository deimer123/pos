<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracion_empresas', function (Blueprint $table) {
            $table->boolean('catalogo_mostrar_precio')->default(true)->after('catalogo_mostrar_disponibilidad');
            $table->boolean('catalogo_mostrar_stock_positivo')->default(true)->after('catalogo_mostrar_precio');
            $table->boolean('catalogo_mostrar_stock_negativo')->default(true)->after('catalogo_mostrar_stock_positivo');
        });

        Schema::table('configuracion_empresas', function (Blueprint $table) {
            $table->dropColumn('catalogo_filtro_stock');
        });
    }

    public function down(): void
    {
        Schema::table('configuracion_empresas', function (Blueprint $table) {
            $table->string('catalogo_filtro_stock')->default('todos');
            $table->dropColumn(['catalogo_mostrar_precio', 'catalogo_mostrar_stock_positivo', 'catalogo_mostrar_stock_negativo']);
        });
    }
};
