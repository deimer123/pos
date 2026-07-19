<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operaciones_offline_sincronizadas', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('tipo');
            $table->foreignId('empresa_id')
                ->constrained('users')
                ->onDelete('cascade');
            $table->unsignedBigInteger('resultado_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operaciones_offline_sincronizadas');
    }
};
