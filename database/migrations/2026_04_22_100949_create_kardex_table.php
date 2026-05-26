<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('kardex', function (Blueprint $table) {
    $table->id();

    $table->unsignedBigInteger('empresa_id');
    $table->string('producto_id');

    $table->string('tipo'); // compra, venta, ajuste

    $table->integer('entrada')->default(0);
    $table->integer('salida')->default(0);

    $table->integer('stock_anterior');
    $table->integer('stock_actual');

    $table->string('referencia')->nullable();
    $table->text('detalle')->nullable();

    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kardex');
    }
};
