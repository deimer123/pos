<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// La "empresa emisora" de las facturas de plan ya no es una empresa
// aparte que hay que crear y marcar -- es la propia cuenta del
// super_admin (con la que inicia sesion), configurada con su
// ConfiguracionEmpresa como cualquier otra empresa (ver boton "Configurar
// datos de facturación" en el listado de empresas y App\Services\Ventas\
// FacturarPlanService). Este flag ya no se usa en ningun lado.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('es_empresa_emisora');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('es_empresa_emisora')->default(false)->after('valor_plan_total');
        });
    }
};
