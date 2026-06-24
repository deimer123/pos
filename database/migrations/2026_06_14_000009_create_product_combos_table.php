<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('product_combos')) {
            return;
        }

        Schema::create('product_combos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('product_id');
            $table->string('nombre', 150)->nullable();
            $table->decimal('cantidad_minima', 10, 2)->default(1);
            $table->decimal('precio_combo', 12, 2);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->index(['empresa_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_combos');
    }
};
