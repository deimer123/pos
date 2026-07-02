<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('porcentaje_empresa', 5, 2)->nullable()->after('tipo_servicio');
        });

        Schema::table('configuracion_empresas', function (Blueprint $table) {
            $table->dropColumn('porcentaje_empresa_servicios');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('porcentaje_empresa');
        });

        Schema::table('configuracion_empresas', function (Blueprint $table) {
            $table->decimal('porcentaje_empresa_servicios', 5, 2)->default(0)->after('usa_servicios');
        });
    }
};
