<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('producto_variantes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('codigo', 50)->nullable();
            $table->string('nombre', 150);
            $table->json('atributos')->nullable();
            $table->decimal('peso', 10, 3)->nullable();
            $table->decimal('precio_extra', 15, 2)->default(0);
            $table->decimal('costo_extra', 15, 2)->default(0);
            $table->decimal('stock', 10, 3)->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['empresa_id', 'product_id', 'codigo']);
            $table->index(['empresa_id', 'product_id', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('producto_variantes');
    }
};
