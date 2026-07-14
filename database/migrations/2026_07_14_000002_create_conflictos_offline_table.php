<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conflictos_offline', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->string('tipo');
            $table->foreignId('empresa_id')
                ->constrained('users')
                ->onDelete('cascade');
            $table->json('payload')->nullable();
            $table->text('error')->nullable();
            $table->string('estado')->default('pendiente'); // pendiente | resuelto
            $table->foreignId('resuelto_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resuelto_en')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conflictos_offline');
    }
};
