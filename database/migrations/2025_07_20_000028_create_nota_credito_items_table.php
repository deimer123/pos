<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nota_credito_items', function (Blueprint $table) {
            $table->id();

            // 🔹 Nota crédito principal
            $table->foreignId('nota_credito_id')
                ->constrained('nota_creditos')
                ->cascadeOnDelete();

            // 🔹 Detalle de devolución asociado (solo trazabilidad)
            $table->unsignedBigInteger('devolucion_detalle_id')->nullable()->index();

            // 🔹 Producto multiempresa (ID lógico usado en tu POS)
            $table->string('product_id', 50)->nullable()->index();

            // 🔹 Datos económicos
            $table->decimal('cantidad', 15, 2)->default(0);
            $table->decimal('costo_unitario', 15, 2)->default(0);

            // 🔹 Impuestos calculados
            $table->decimal('valor_impuesto', 15, 2)->default(0);
            $table->decimal('subtotal_sin_impuesto', 15, 2)->default(0);
            $table->decimal('subtotal_con_impuesto', 15, 2)->default(0);

            $table->timestamps();

            // 🔹 Índices adicionales
            $table->index(['nota_credito_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nota_credito_items');
    }
};
