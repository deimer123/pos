<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Sucursales de una empresa (una empresa es un User con
// tipo_usuario='empresa', por eso empresa_id apunta a users). Tabla nueva:
// no toca ningun dato existente. Solo se llena para las empresas a las que
// el super_admin les active el interruptor usa_sucursales.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sucursales')) {
            return;
        }

        Schema::create('sucursales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('users')->cascadeOnDelete();
            $table->string('nombre', 150);
            $table->string('codigo', 30)->nullable();
            $table->string('direccion', 255)->nullable();
            $table->string('telefono', 50)->nullable();
            $table->boolean('es_principal')->default(false);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            // El nombre no se repite dentro de la misma empresa.
            $table->unique(['empresa_id', 'nombre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sucursales');
    }
};
