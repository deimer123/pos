<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ajuste_inventario_detalles', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('ajuste_inventario_id');

            // 🔑 Código real del producto (10003, 10004, etc.)
            $table->string('producto_id');

            // 👇 IMPORTANTE: permitir null porque Filament guarda primero
            $table->integer('cantidad_anterior')->nullable();
            $table->integer('cantidad_nueva');
            $table->integer('diferencia')->nullable();

            $table->timestamps();

            $table->foreign('ajuste_inventario_id')
                ->references('id')
                ->on('ajustes_inventario')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ajuste_inventario_detalles');
    }
};