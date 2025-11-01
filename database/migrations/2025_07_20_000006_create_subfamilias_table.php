<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('subfamilias', function (Blueprint $table) {
            $table->id('id_familia2');
            $table->unsignedBigInteger('id_familia1');
            $table->foreignId('empresa_id')->constrained('users')->onDelete('cascade');
            $table->string('nombre');
            $table->timestamps();

            // Si quieres también puedes agregar la relación con familias:
            // $table->foreign('id_familia1')->references('id')->on('familias')->onDelete('cascade');
        });
    }

    public function down(): void {
        Schema::dropIfExists('subfamilias');
    }
};
