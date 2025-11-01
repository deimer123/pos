<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('configuracion_empresas', function (Blueprint $table) {
            $table->id();
            // Cambiar de user_id a empresa_id
            $table->foreignId('empresa_id')
                ->unique()
                ->constrained('users')
                ->onDelete('cascade');
            $table->string('nombre_empresa');
            $table->string('representante_legal');
            $table->string('telefono')->nullable();
            $table->string('direccion')->nullable();
            $table->string('nit')->nullable();
            $table->string('lema')->nullable();
            $table->string('logo')->nullable();
            // Facturación electrónica
            $table->string('prefijo')->nullable();
            $table->integer('rango_desde')->nullable();
            $table->integer('rango_hasta')->nullable();
            $table->integer('rango_actual')->nullable();
            $table->string('numero_resolucion')->nullable();
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_fin')->nullable();
            $table->string('llave')->nullable();
            $table->boolean('expirado')->default(false);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('configuracion_empresas');
    }
};
