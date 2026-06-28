<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->enum('tipo_pedido', ['local', 'domicilio', 'para_llevar'])->default('local')->after('transferencia_obs');
            $table->decimal('costo_empaque', 10, 2)->default(0)->after('tipo_pedido');
            $table->string('dom_nombre', 200)->nullable()->after('costo_empaque');
            $table->string('dom_telefono', 30)->nullable()->after('dom_nombre');
            $table->string('dom_direccion', 300)->nullable()->after('dom_telefono');
            $table->string('dom_ciudad', 100)->nullable()->after('dom_direccion');
            $table->string('dom_nit', 30)->nullable()->after('dom_ciudad');
            $table->string('dom_email', 150)->nullable()->after('dom_nit');
            $table->string('dom_razon_social', 200)->nullable()->after('dom_email');
            $table->enum('cobro_domicilio', ['anticipado', 'entrega'])->default('anticipado')->after('dom_razon_social');
        });
    }

    public function down(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->dropColumn([
                'tipo_pedido', 'costo_empaque',
                'dom_nombre', 'dom_telefono', 'dom_direccion', 'dom_ciudad',
                'dom_nit', 'dom_email', 'dom_razon_social', 'cobro_domicilio',
            ]);
        });
    }
};
