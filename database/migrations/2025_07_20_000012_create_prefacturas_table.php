<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prefacturas', function (Blueprint $table) {
            $table->id();

            $table->foreignId('empresa_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('cliente_id')->nullable()->constrained('actors')->nullOnDelete();
             // VENDEDOR
            $table->foreignId('vendedor_id')->nullable()->constrained('users')->nullOnDelete();

            // CAJERO
            $table->foreignId('cajero_id')->nullable()->constrained('users')->nullOnDelete();

            $table->text('observaciones')->nullable();
            $table->string('estado')->default('borrador'); // 'borrador', 'vendida'

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prefacturas');
    }
};
