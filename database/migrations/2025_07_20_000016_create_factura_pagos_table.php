<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('factura_pagos', function (Blueprint $table) {
            $table->id();

            // Relación con facturas
            $table->foreignId('factura_id')
                ->constrained('facturas')
                ->cascadeOnDelete();

            // Datos del pago
            $table->string('medio_pago', 30)->nullable(); // efectivo, transferencia, tarjeta, etc.
            $table->decimal('monto', 14, 2);
            $table->dateTime('fecha'); // se asigna con now() en el modelo/método
            $table->foreignId('user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->string('nota', 150)->nullable();

            // 👇 NUEVO: observación específica para transferencias
            $table->string('transferencia_obs')->nullable();

            $table->timestamps();

            $table->index(['factura_id', 'fecha']);
            $table->index(['medio_pago']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('factura_pagos');
    }
};
