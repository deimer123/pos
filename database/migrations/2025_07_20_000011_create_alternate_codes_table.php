<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alternate_codes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('empresa_id')
                ->constrained('users')
                ->onDelete('cascade');

            $table->foreignId('product_id')
                ->constrained('products', 'id') // Usamos id, como corresponde
                ->cascadeOnDelete();

            $table->string('code');

            $table->timestamps();

            // ✅ Cambio importante: clave única por producto, código y empresa
            $table->unique(['product_id', 'code', 'empresa_id'], 'alternate_codes_unique_combination');

            // Índices adicionales
            $table->index('product_id');
            $table->index('empresa_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alternate_codes');
    }
};
