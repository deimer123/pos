<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('devolucion_detalles', function (Blueprint $table) {
            $table->id();

            // cabecera
            $table->foreignId('devolucion_id')->constrained('devoluciones')->cascadeOnDelete();

            // referencia al detalle original de la factura
            $table->foreignId('factura_detalle_id')->constrained('factura_detalles')->cascadeOnDelete();

            // snapshot de datos del ítem
            $table->unsignedBigInteger('producto_id');
            $table->string('descripcion_larga', 255)->nullable();

            $table->decimal('cantidad', 14, 2);
            $table->decimal('precio', 14, 2);
            $table->decimal('subtotal', 14, 2);

            $table->timestamps();

            $table->index(['devolucion_id']);
            $table->index(['factura_detalle_id']);
            $table->index(['producto_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devolucion_detalles');
    }
};
