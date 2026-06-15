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
            $table->enum('estado', ['libre', 'ocupada', 'reservada', 'cerrada'])->default('libre');
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
