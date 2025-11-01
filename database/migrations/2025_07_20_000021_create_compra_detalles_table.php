<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compra_detalles', function (Blueprint $table) {
            $table->id();

            // Relación con compra
            $table->foreignId('compra_id')->constrained('compras')->cascadeOnDelete();

            // Código del producto (multiempresa, string)
            $table->string('product_id', 50)->nullable()->index();

            // Referencias y datos del producto
            $table->string('codigo_ingresado', 100)->nullable()->index();
            $table->string('nombre_producto', 255)->nullable();

            // Cantidades y costos
            $table->decimal('cantidad', 15, 2)->default(1);
            $table->decimal('costo_unitario', 15, 2)->default(0);

            // Descuentos e impuestos
            $table->decimal('desc_comercial', 10, 2)->default(0);
            $table->decimal('descuento_pct', 10, 2)->default(0);
            $table->decimal('iva_pct', 10, 2)->default(0);

            // Precios y utilidad
            $table->decimal('precio_venta_act', 15, 2)->default(0);
            $table->decimal('utilidad_pct', 10, 2)->nullable();
            $table->decimal('precio_venta', 15, 2)->default(0);

            // Totales
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('impuesto', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);

            // Devoluciones
            $table->decimal('devuelto_cantidad', 15, 2)->default(0);

            $table->timestamps();

            $table->index(['compra_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compra_detalles');
    }
};
