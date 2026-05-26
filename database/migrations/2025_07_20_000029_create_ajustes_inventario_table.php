<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ajustes_inventario', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('usuario_id');

            $table->enum('tipo', [
                'ajuste',
                'inventario_nuevo',
            ]);

            $table->text('observacion');

            // ✅ CAMPO CLAVE PARA BORRADORES
            $table->enum('estado', [
                'borrador',
                'confirmado',
            ])->default('confirmado');

            $table->timestamps();

            // (Opcional pero recomendado)
            $table->index('empresa_id');
            $table->index('usuario_id');
            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ajustes_inventario');
    }
};
