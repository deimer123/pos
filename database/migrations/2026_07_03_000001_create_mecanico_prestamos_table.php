<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mecanico_prestamos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->index();
            $table->unsignedBigInteger('mecanico_id');
            $table->decimal('monto', 14, 2);
            $table->date('fecha');
            $table->text('nota')->nullable();
            $table->string('estado', 20)->default('pendiente'); // pendiente | descontado
            $table->unsignedBigInteger('liquidacion_id')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->timestamps();

            $table->foreign('mecanico_id')->references('id')->on('mecanicos')->cascadeOnDelete();
            $table->foreign('liquidacion_id')->references('id')->on('liquidaciones_mecanico')->nullOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mecanico_prestamos');
    }
};
