<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('actors', function (Blueprint $table) {
            $table->id(); // Clave primaria real (recomendada)
            $table->unsignedBigInteger('id_clip_pro')->unique(); // Código externo

            $table->tinyInteger('tipo'); // 1 = cliente, 3 = proveedor

            $table->enum('tipo_persona', ['natural', 'juridica'])->nullable(); // Natural o Jurídica
            $table->unsignedTinyInteger('tipo_documento_id'); // primero se crea la columna
$table->foreign('tipo_documento_id')->references('id')->on('tipos_documento'); // luego se hace la foreign key
            $table->string('identificacion')->nullable();

            $table->string('nombre')->nullable();
            $table->string('razon_social')->nullable();
            $table->string('direccion')->nullable();
            $table->string('telefono')->nullable();
            $table->string('email')->nullable();

            $table->enum('clasificacion', ['cliente', 'proveedor'])->nullable();

            $table->enum('regimen_tributario', [
                'comun',
                'simplificado',
                'especial',
                'otro'
            ])->nullable();

            $table->boolean('responsable_iva')->default(false);
            $table->foreignId('ciudad_id')->nullable()->constrained('ciudades');
            $table->foreignId('departamento_id')->nullable()->constrained('departamentos');

            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('actors');
    }
};
