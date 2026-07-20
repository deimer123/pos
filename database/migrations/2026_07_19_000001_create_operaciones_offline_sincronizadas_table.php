<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ya existia en el droplet de un trabajo anterior (creada antes de
        // que esta migracion quedara registrada en la tabla "migrations"),
        // asi que se protege con hasTable() para que correr "migrate" no
        // truene con "table already exists" y bloquee las migraciones
        // nuevas de Turion que vienen despues en el mismo lote.
        if (Schema::hasTable('operaciones_offline_sincronizadas')) {
            return;
        }

        Schema::create('operaciones_offline_sincronizadas', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('tipo');
            $table->foreignId('empresa_id')
                ->constrained('users')
                ->onDelete('cascade');
            $table->unsignedBigInteger('resultado_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operaciones_offline_sincronizadas');
    }
};
