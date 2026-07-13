<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalogos', function (Blueprint $table) {
            $table->string('tipo_seleccion')->default('detallado')->after('activo');
        });

        Schema::create('catalogo_familia', function (Blueprint $table) {
            $table->id();
            $table->foreignId('catalogo_id')
                ->constrained('catalogos')
                ->onDelete('cascade');
            $table->foreignId('familia_id')
                ->constrained('familias', 'id')
                ->onDelete('cascade');
            $table->timestamps();

            $table->unique(['catalogo_id', 'familia_id']);
        });

        Schema::create('catalogo_subfamilia', function (Blueprint $table) {
            $table->id();
            $table->foreignId('catalogo_id')
                ->constrained('catalogos')
                ->onDelete('cascade');
            $table->foreignId('subfamilia_id')
                ->constrained('subfamilias', 'id_familia2')
                ->onDelete('cascade');
            $table->timestamps();

            $table->unique(['catalogo_id', 'subfamilia_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalogo_subfamilia');
        Schema::dropIfExists('catalogo_familia');

        Schema::table('catalogos', function (Blueprint $table) {
            $table->dropColumn('tipo_seleccion');
        });
    }
};
