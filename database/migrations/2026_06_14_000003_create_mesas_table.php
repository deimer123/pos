<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mesas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('users')->cascadeOnDelete();
            $table->string('codigo', 20);
            $table->string('nombre', 100);
            $table->string('zona', 100)->nullable();
            $table->unsignedSmallInteger('capacidad')->nullable();
            // 'lista' se agrego despues via 2026_06_21_000001_add_lista_to_mesas_estado;
            // se incluye aqui directamente para que una instalacion nueva contra SQLite
            // (base de datos local de Turion) quede con el CHECK constraint correcto
            // desde el arranque, ya que esa migracion posterior no puede alterar un
            // enum existente en SQLite (ver nota en esa migracion).
            $table->enum('estado', ['libre', 'ocupada', 'reservada', 'cerrada', 'lista'])->default('libre');
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['empresa_id', 'codigo']);
            $table->index(['empresa_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mesas');
    }
};
