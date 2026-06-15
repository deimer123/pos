<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receta_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('receta_id')->constrained('recetas')->cascadeOnDelete();
            $table->foreignId('ingrediente_product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('cantidad', 10, 3);
            $table->string('unidad', 20)->default('unidad');
            $table->decimal('merma', 10, 3)->default(0);
            $table->timestamps();

            $table->index(['empresa_id', 'receta_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receta_items');
    }
};
