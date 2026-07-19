<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Tabla exclusiva de la base de datos local de Turion (no existe en el
// droplet): cola de lo que el cajero hizo sin conexion -- ventas, items y
// facturaciones de mesa/taller/hotel -- pendiente de subir al servidor
// con el boton "Subir". No tiene sentido en el droplet (que ya es el
// servidor), por eso es un no-op fuera de SQLite.
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        Schema::create('pending_sync_operations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            // venta | mesa_item | mesa_facturar | taller_crear | taller_item |
            // taller_facturar | hotel_crear | hotel_item | hotel_facturar
            $table->string('tipo');
            $table->json('payload');
            $table->string('estado')->default('pendiente'); // pendiente | sincronizado | error
            $table->text('error')->nullable();
            $table->unsignedBigInteger('resultado_id')->nullable();
            $table->timestamp('sincronizado_at')->nullable();
            $table->timestamps();

            $table->index(['estado', 'created_at']);
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        Schema::dropIfExists('pending_sync_operations');
    }
};
