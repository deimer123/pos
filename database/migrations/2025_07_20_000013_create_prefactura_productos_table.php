<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prefactura_productos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('empresa_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('prefactura_id')->constrained()->onDelete('cascade');

            $table->unsignedBigInteger('producto_id');
            $table->foreign('producto_id')->references('id_producto')->on('products');

            $table->string('descripcion_larga')->nullable();
            $table->integer('cantidad');
            $table->decimal('precio_unitario', 12, 2);
            $table->decimal('subtotal', 12, 2);
            $table->decimal('descuento', 5, 2)->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prefactura_productos');
    }
};
