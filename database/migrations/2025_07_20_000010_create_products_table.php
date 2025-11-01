<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('products', function (Blueprint $table) {
            // Clave primaria técnica autoincremental
            $table->id(); 
            
            // Campo de negocio (código del producto por empresa)
            $table->unsignedBigInteger('id_producto');
            
            $table->foreignId('empresa_id')->constrained('users')->onDelete('cascade');

            $table->unsignedBigInteger('id_familia1');
            $table->unsignedBigInteger('id_familia2');

            $table->string('descripcion_larga');

            // Solo como referencia, sin foreign
            $table->unsignedBigInteger('id_proveedor')->nullable();

            $table->decimal('iva_compra', 5, 2)->default(0);
            $table->decimal('iva_venta', 5, 2)->default(0);
            $table->integer('existencias')->default(0);

            $table->decimal('precio_costo', 15, 2)->nullable();
            $table->decimal('precio_venta1', 15, 2)->nullable();
            $table->decimal('utilidad1', 6, 2)->nullable();

            $table->decimal('precio_costo_anterior', 15, 2)->nullable();
            $table->decimal('precio_venta_anterior', 15, 2)->nullable();
            $table->decimal('descuento_comercial', 5, 2)->default(0);
            $table->decimal('costo_iva', 15, 2)->nullable();
            $table->decimal('precio_con_descuento', 15, 2)->nullable();

            $table->unsignedBigInteger('id_unidad_de_medida')->default(0);
            $table->string('foto')->nullable();

            $table->timestamps();
            
            // Índices para optimizar consultas
            $table->unique(['id_producto', 'empresa_id']); // id_producto único por empresa
            $table->unique(['descripcion_larga', 'empresa_id']); // nombre único por empresa
            $table->index('empresa_id');
        });
    }

    public function down(): void {
        Schema::dropIfExists('products');
    }
};
