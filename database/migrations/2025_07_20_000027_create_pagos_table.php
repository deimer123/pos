<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagos', function (Blueprint $table) {
            $table->id();

            // Relaciones principales
            $table->unsignedBigInteger('empresa_id')->nullable()->index();
            $table->foreignId('compra_id')->constrained('compras')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->nullable()->index();

            // Datos del pago
            $table->date('fecha')->nullable();
            $table->decimal('monto', 15, 2)->default(0);
            $table->string('metodo_pago', 100)->nullable(); // Efectivo, transferencia, etc.
            $table->string('referencia', 150)->nullable(); // Nº de comprobante o nota
            $table->text('observaciones')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos');
    }
};
