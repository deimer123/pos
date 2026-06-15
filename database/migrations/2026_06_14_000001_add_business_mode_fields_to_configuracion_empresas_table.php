<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracion_empresas', function (Blueprint $table) {
            $table->string('tipo_negocio', 30)->default('tienda')->after('logo');
            $table->boolean('usa_mesas')->default(false)->after('tipo_negocio');
            $table->boolean('usa_cocina')->default(false)->after('usa_mesas');
            $table->boolean('usa_recetas')->default(false)->after('usa_cocina');
            $table->boolean('usa_variantes')->default(false)->after('usa_recetas');
            $table->boolean('usa_peso')->default(false)->after('usa_variantes');
            $table->boolean('usa_servicios')->default(false)->after('usa_peso');
            $table->boolean('permite_stock_negativo')->default(false)->after('usa_servicios');
            $table->boolean('imprime_ticket')->default(true)->after('permite_stock_negativo');
            $table->boolean('mostrar_iva_separado')->default(false)->after('imprime_ticket');
        });
    }

    public function down(): void
    {
        Schema::table('configuracion_empresas', function (Blueprint $table) {
            $table->dropColumn([
                'tipo_negocio',
                'usa_mesas',
                'usa_cocina',
                'usa_recetas',
                'usa_variantes',
                'usa_peso',
                'usa_servicios',
                'permite_stock_negativo',
                'imprime_ticket',
                'mostrar_iva_separado',
            ]);
        });
    }
};
