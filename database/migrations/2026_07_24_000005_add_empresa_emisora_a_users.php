<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Para poder generarle a una empresa una factura por el cobro de su plan,
// hace falta que UNA empresa dentro del sistema actue como "emisora"
// (la empresa propia del super_admin/dueño del sistema, con su propio
// NIT). Solo puede haber una marcada a la vez -- se controla desde
// EmpresaResource.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('es_empresa_emisora')->default(false)->after('valor_plan_total');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('es_empresa_emisora');
        });
    }
};
