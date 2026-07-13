<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalogo_producto', function (Blueprint $table) {
            $table->id();
            $table->foreignId('catalogo_id')
                ->constrained('catalogos')
                ->onDelete('cascade');
            $table->foreignId('product_id')
                ->constrained('products', 'id')
                ->onDelete('cascade');
            $table->timestamps();

            $table->unique(['catalogo_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalogo_producto');
    }
};
