<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('subfamilias', function (Blueprint $table) {
            $table->id('id_familia2'); // Puedes usar id si prefieres
            $table->unsignedBigInteger('id_familia1'); // solo referencia
            $table->string('nombre');
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('subfamilias');
    }
};
