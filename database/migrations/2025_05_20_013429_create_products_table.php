<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('products', function (Blueprint $table) {
            $table->unsignedBigInteger('id_producto')->primary(); // Se mantiene el ID heredado del Excel

            $table->unsignedBigInteger('id_familia1');
            $table->unsignedBigInteger('id_familia2');

            $table->string('descripcion_larga');

            // Ya no se establece como foreign key, solo es un valor de referencia
            $table->unsignedBigInteger('id_proveedor')->nullable();

            $table->decimal('iva_compra', 5, 2)->default(0);
            $table->decimal('iva_venta', 5, 2)->default(0);
            $table->integer('existencias')->default(0);

            $table->decimal('precio_costo', 15, 2)->nullable();
            $table->decimal('precio_venta1', 15, 2)->nullable();
            $table->decimal('utilidad1', 6, 2)->nullable();

            $table->unsignedBigInteger('id_unidad_de_medida')->default(0);
            $table->string('foto')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('products');
    }
};
