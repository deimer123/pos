<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Tabla exclusiva de la base de datos local de Turion: guarda el estado
// del emparejamiento de esta terminal y la marca de tiempo de la ultima
// vez que se pulso "Sincronizar"/"Subir". Siempre tiene, a lo sumo, una
// fila (terminal de una sola caja por negocio).
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        Schema::create('sync_state', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->string('empresa_nombre')->nullable();
            $table->text('terminal_token')->nullable();
            $table->string('servidor_url')->nullable();
            $table->timestamp('ultima_sincronizacion_at')->nullable();
            $table->timestamp('ultima_subida_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        Schema::dropIfExists('sync_state');
    }
};
