<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('tipo_producto', 20)->default('producto')->after('descripcion_larga');
            $table->string('vende_por', 20)->default('unidad')->after('tipo_producto');
            $table->boolean('maneja_inventario')->default(true)->after('vende_por');
            $table->boolean('permite_fraccion')->default(false)->after('maneja_inventario');
            $table->boolean('requiere_cocina')->default(false)->after('permite_fraccion');
            $table->decimal('peso_base', 10, 3)->nullable()->after('requiere_cocina');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'tipo_producto',
                'vende_por',
                'maneja_inventario',
                'permite_fraccion',
                'requiere_cocina',
                'peso_base',
            ]);
        });
    }
};
