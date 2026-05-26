<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nota_creditos', function (Blueprint $table) {
            $table->id();

            // 🔹 Multiempresa
            $table->unsignedBigInteger('empresa_id')->nullable()->index();

            // 🔹 Relación con compra original
            $table->foreignId('compra_id')
                ->nullable()
                ->constrained('compras')
                ->nullOnDelete();

            // 🔹 Usuario que generó la NC
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // 🔹 Número de la nota crédito (NC-000001)
            $table->string('numero', 100)
                ->nullable()
                ->index();

            // 🔹 Total devuelto
            $table->decimal('total', 15, 2)
                ->default(0);

            // 🔹 Motivo
            $table->text('motivo')->nullable();

            // 🔹 Estado de la nota crédito
            $table->enum('estado', ['pendiente', 'cobrada', 'anulada'])
                ->default('pendiente')
                ->index();

            // 🔹 Proveedor / empresa que debe la NC
            $table->string('empresa_deudora')->nullable();

            // 🔹 Fecha en que se cobró
            $table->dateTime('fecha_cobro')->nullable();

            // 🔹 Fechas de creación / actualización
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nota_creditos');
    }
};
