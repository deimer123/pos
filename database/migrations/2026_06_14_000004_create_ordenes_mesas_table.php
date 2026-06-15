<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ordenes_mesas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('mesa_id')->constrained('mesas')->cascadeOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('cliente_id')->nullable()->index();
            $table->unsignedBigInteger('consecutivo')->nullable();
            $table->enum('estado', ['abierta', 'en_preparacion', 'lista', 'facturada', 'cancelada', 'cerrada'])->default('abierta');
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('impuestos', 15, 2)->default(0);
            $table->decimal('descuento', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->text('observaciones')->nullable();
            $table->dateTime('abierta_en')->nullable();
            $table->dateTime('cerrada_en')->nullable();
            $table->timestamps();

            $table->unique(['empresa_id', 'consecutivo']);
            $table->index(['empresa_id', 'mesa_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ordenes_mesas');
    }
};
