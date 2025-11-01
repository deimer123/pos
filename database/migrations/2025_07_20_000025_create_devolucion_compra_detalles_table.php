<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devolucion_compra_detalles', function (Blueprint $table) {
            $table->id();

            // 🔹 Referencia a la devolución
            $table->unsignedBigInteger('devolucion_compra_id')->nullable()->index();

            // 🔹 Referencia al detalle de compra (sin FK rígida)
            $table->unsignedBigInteger('compra_detalle_id')->nullable()->index();

            // 🔹 Código del producto multiempresa
            $table->string('product_id', 50)->nullable()->index();

            // 🔹 Nombre del producto (opcional, para trazabilidad)
            $table->string('nombre_producto', 255)->nullable();

            // 🔹 Datos base
            $table->decimal('cantidad', 15, 2)->default(0);
            $table->decimal('costo_unitario', 15, 2)->default(0);

            // 🔹 Impuestos
            $table->decimal('porcentaje_impuesto', 10, 2)->default(0);
            $table->decimal('valor_impuesto', 15, 2)->default(0);

            // 🔹 Subtotales y total
            $table->decimal('subtotal_sin_impuesto', 15, 2)->default(0);
            $table->decimal('subtotal_con_impuesto', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->virtualAs('subtotal_con_impuesto');

            $table->timestamps();

            $table->index(['devolucion_compra_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devolucion_compra_detalles');
    }
};
