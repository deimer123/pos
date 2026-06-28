<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ordenes_mesas', function (Blueprint $table) {
            $table->enum('tipo_pedido', ['mesa', 'domicilio', 'para_llevar'])->default('mesa')->after('observaciones');
            $table->decimal('costo_empaque', 10, 2)->default(0)->after('tipo_pedido');
            $table->string('dom_nombre', 200)->nullable()->after('costo_empaque');
            $table->string('dom_telefono', 30)->nullable()->after('dom_nombre');
            $table->string('dom_direccion', 300)->nullable()->after('dom_telefono');
        });
    }

    public function down(): void
    {
        Schema::table('ordenes_mesas', function (Blueprint $table) {
            $table->dropColumn(['tipo_pedido', 'costo_empaque', 'dom_nombre', 'dom_telefono', 'dom_direccion']);
        });
    }
};
