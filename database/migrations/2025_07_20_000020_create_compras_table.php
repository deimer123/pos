<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compras', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('empresa_id')->index();

            // ✅ FK correcta
            $table->foreignId('proveedor_id')
                ->constrained('actors')
                ->cascadeOnDelete();

            $table->unsignedBigInteger('user_id')->nullable()->index();

            $table->string('estado', 50)->default('borrador')->index();
            $table->string('tipo_pago', 50)->nullable();

            $table->date('fecha')->nullable();
            $table->date('fecha_vencimiento')->nullable();

            $table->string('numero_factura', 100)->nullable()->index();

            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('descuento_total', 15, 2)->default(0);
            $table->decimal('impuesto_total', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('saldo', 15, 2)->default(0);

            $table->text('nota')->nullable();

            $table->timestamp('confirmada_at')->nullable();
            $table->timestamp('anulada_at')->nullable();
            $table->string('motivo_anulacion', 255)->nullable();

            $table->boolean('devuelta_total')->default(false);
            $table->timestamp('devuelta_at')->nullable();
            $table->string('motivo_devol', 255)->nullable();

            $table->timestamps();

            $table->index(['empresa_id', 'proveedor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compras');
    }
};
