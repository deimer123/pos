<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recetas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('nombre', 150)->nullable();
            $table->decimal('rendimiento', 10, 3)->default(1);
            $table->string('unidad_rendimiento', 20)->default('unidad');
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['empresa_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recetas');
    }
};
