<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::create('gastos', function (Blueprint $table) {

            // ID técnico
            $table->id();

            // ID negocio por empresa
            $table->unsignedBigInteger('id_gasto');

            // Multiempresa
            $table->foreignId('empresa_id')
                ->constrained('users')
                ->onDelete('cascade');

            $table->enum('tipo', ['entrada', 'salida'])->default('salida');

            // Información principal
            $table->string('categoria');
            $table->string('descripcion');

            // Dinero
            $table->decimal('monto', 15, 2);

            // Fecha gasto
            $table->date('fecha');

            // Método pago
            $table->string('metodo_pago')->nullable();

            // Observaciones
            $table->text('observacion')->nullable();

            // Usuario que registró
            $table->unsignedBigInteger('created_by')->nullable();

            $table->foreignId('caja_id')
                ->nullable()
                ->constrained('cajas')
                ->nullOnDelete();

            $table->timestamps();

            // Índices
            $table->unique(['id_gasto', 'empresa_id']);

            $table->index('empresa_id');
            $table->index('fecha');
            $table->index('categoria');
            $table->index(['empresa_id', 'tipo']);
            $table->index(['caja_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gastos');
    }
};
