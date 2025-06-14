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
        Schema::create('detalle_ventas', function (Blueprint $table) {
    $table->id();
    $table->foreignId('venta_id')->constrained('ventas')->onDelete('cascade');

    $table->unsignedBigInteger('producto_id');
    $table->foreign('producto_id')->references('id_producto')->on('products')->onDelete('cascade');

    $table->integer('cantidad');
    $table->decimal('precio_unitario', 10, 2);
    $table->decimal('subtotal', 10, 2);
    
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('detalle_ventas');
    }
};
