<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracion_empresas', function (Blueprint $table) {
            $table->string('catalogo_filtro_stock')->default('todos')->after('permite_ver_stock_no_admin');
            $table->boolean('catalogo_mostrar_disponibilidad')->default(false)->after('catalogo_filtro_stock');
        });
    }

    public function down(): void
    {
        Schema::table('configuracion_empresas', function (Blueprint $table) {
            $table->dropColumn(['catalogo_filtro_stock', 'catalogo_mostrar_disponibilidad']);
        });
    }
};
