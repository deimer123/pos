<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('devoluciones', function (Blueprint $table) {
            $table->id();

            // empresa dueña del documento (en tu app empresas están en users)
            $table->foreignId('empresa_id')->constrained('users')->cascadeOnDelete();

            // factura origen
            $table->foreignId('factura_id')->constrained('facturas')->cascadeOnDelete();

            // cliente opcional
            $table->foreignId('cliente_id')->nullable()->constrained('actors')->nullOnDelete();

            // usuario que hace la devolución (logueado)
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->dateTime('fecha')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->decimal('total', 14, 2)->default(0);
            $table->text('observaciones')->nullable();

            $table->timestamps();

            // Índices útiles
            $table->index(['empresa_id', 'fecha']);
            $table->index(['factura_id']);
            $table->index(['cliente_id']);
            $table->index(['user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devoluciones');
    }
};
