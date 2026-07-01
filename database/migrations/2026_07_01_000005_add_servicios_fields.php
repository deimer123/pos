<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracion_empresas', function (Blueprint $table) {
            $table->decimal('porcentaje_empresa_servicios', 5, 2)->default(0)->after('usa_servicios');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->string('tipo_servicio')->nullable()->after('tipo_producto');
        });

        Schema::table('factura_detalles', function (Blueprint $table) {
            $table->string('tipo_servicio')->nullable()->after('descuento');
            $table->decimal('porcentaje_empresa', 5, 2)->nullable()->after('tipo_servicio');
        });
    }

    public function down(): void
    {
        Schema::table('configuracion_empresas', function (Blueprint $table) {
            $table->dropColumn('porcentaje_empresa_servicios');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('tipo_servicio');
        });

        Schema::table('factura_detalles', function (Blueprint $table) {
            $table->dropColumn(['tipo_servicio', 'porcentaje_empresa']);
        });
    }
};
