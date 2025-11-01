<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('factura_detalles', function (Blueprint $table) {
            $table->id();

            // Cabecera
            $table->foreignId('factura_id')->constrained('facturas')->cascadeOnDelete();

            // Ítem vendido (no obligamos FK porque muchos usan clave externa custom)
            $table->unsignedBigInteger('producto_id')->index();

            // Snapshot de la línea
            $table->string('descripcion_larga', 255)->nullable();
            $table->decimal('cantidad', 14, 2);
            $table->decimal('devuelto_cantidad', 14, 2)->default(0); // ← cantidad devuelta acumulada
            $table->decimal('precio', 14, 2);
            $table->decimal('subtotal', 14, 2);
            $table->decimal('descuento', 14, 2)->default(0);

            $table->timestamps();

            $table->index(['factura_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('factura_detalles');
    }
};
