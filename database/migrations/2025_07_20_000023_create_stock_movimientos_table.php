<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('stock_movimientos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('product_id')->constrained('products')->cascadeOnDelete();

            $t->enum('tipo', ['compra','venta','devolucion_compra','devolucion_venta','ajuste']);
            $t->nullableMorphs('referencia'); // referencia_type, referencia_id

            $t->decimal('cantidad', 15, 2);
            $t->decimal('costo_unitario', 15, 2)->default(0);
            $t->decimal('costo_total', 15, 2)->default(0);

            $t->dateTime('fecha');

            $t->timestamps();
            $t->index(['empresa_id','product_id','tipo']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('stock_movimientos');
    }
};
