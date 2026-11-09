<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Traslados de mercancia entre empresas de un mismo grupo (sucursales).
// Tablas NUEVAS: no toca ninguna tabla existente. El stock sale de la empresa
// de origen al enviar y entra a la de destino solo al confirmar la recepcion.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('traslados')) {
            Schema::create('traslados', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('origen_id')->index();
                $table->unsignedBigInteger('destino_id')->index();
                $table->string('estado', 20)->default('enviado')->index(); // enviado, recibido, rechazado, cancelado
                $table->text('observacion')->nullable();
                $table->unsignedBigInteger('enviado_por')->nullable();
                $table->unsignedBigInteger('resuelto_por')->nullable();
                $table->timestamp('enviado_at')->nullable();
                $table->timestamp('resuelto_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('traslado_items')) {
            Schema::create('traslado_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('traslado_id')->constrained('traslados')->cascadeOnDelete();
                $table->unsignedBigInteger('producto_origen_id');   // id_producto en la empresa de origen
                $table->unsignedBigInteger('producto_destino_id')->nullable(); // se llena al recibir
                $table->string('nombre');
                $table->decimal('cantidad', 15, 2);
                $table->decimal('costo_unitario', 15, 2)->default(0);
                $table->decimal('precio_venta', 15, 2)->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('traslado_items');
        Schema::dropIfExists('traslados');
    }
};
