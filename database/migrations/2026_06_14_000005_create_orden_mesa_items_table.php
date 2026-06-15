<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orden_mesa_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('orden_mesa_id')->constrained('ordenes_mesas')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('cantidad', 10, 3)->default(1);
            $table->decimal('precio_unitario', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->enum('estado_cocina', ['pendiente', 'enviado', 'preparando', 'listo', 'entregado', 'cancelado'])->default('pendiente');
            $table->boolean('requiere_cocina')->default(false);
            $table->text('nota_cocina')->nullable();
            $table->dateTime('enviado_cocina_en')->nullable();
            $table->timestamps();

            $table->index(['empresa_id', 'orden_mesa_id', 'estado_cocina']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orden_mesa_items');
    }
};
