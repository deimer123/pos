<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('precio_costo_anterior', 15, 2)->nullable();
            $table->decimal('precio_venta_anterior', 15, 2)->nullable();
            $table->decimal('descuento_comercial', 5, 2)->default(0);
            // Si quieres persistir el costo con IVA en BD:
            $table->decimal('costo_iva', 15, 2)->nullable();
             $table->decimal('precio_con_descuento', 15, 2)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
            'precio_costo_anterior',
            'precio_venta_anterior',
            'descuento_comercial',
            'costo_iva',
            'precio_con_descuento',
        ]);
        });
    }
};
