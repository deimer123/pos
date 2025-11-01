<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('facturas', function (Blueprint $table) {
            $table->id();

            // FKs
            $table->foreignId('empresa_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('actors')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete(); // quien creó la factura

            // Datos principales
            $table->enum('tipo_factura', ['salida', 'electronica'])->default('salida');
            $table->enum('tipo_pago', ['contado', 'credito'])->default('contado');
            $table->enum('medio_pago', ['efectivo', 'transferencia', 'otro'])->nullable(); // sólo aplica en contado

            // Observación específica para transferencia (sin AFTER)
            $table->string('transferencia_obs')->nullable();

            // Fechas
            $table->dateTime('fecha')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->dateTime('fecha_compra')->nullable();
            $table->dateTime('fecha_pago')->nullable();
            $table->date('fecha_vencimiento')->nullable();

            // Valores
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('saldo', 15, 2)->default(0);
            $table->enum('estado_pago', ['pendiente','parcial','pagada','vencida'])->default('pendiente');

            // Observaciones y bandera de devoluciones
            $table->text('observaciones')->nullable();
            $table->boolean('devuelta_total')->default(false);

            $table->timestamps();

            // Índices útiles
            $table->index(['empresa_id', 'cliente_id']);
            $table->index(['tipo_pago', 'estado_pago']);
            $table->index(['fecha', 'fecha_vencimiento']);
            $table->index(['devuelta_total']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facturas');
    }
};
