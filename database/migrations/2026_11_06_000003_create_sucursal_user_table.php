<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Asignacion de usuarios a sucursales. Es una tabla pivote (y no una
// columna en users) porque un administrador de sucursal puede tener varias
// sucursales autorizadas, mientras que un vendedor o cajero tiene una sola.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sucursal_user')) {
            return;
        }

        Schema::create('sucursal_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['sucursal_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sucursal_user');
    }
};
