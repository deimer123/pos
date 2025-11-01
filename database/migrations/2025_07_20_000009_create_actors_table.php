<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('actors', function (Blueprint $table) {
            $table->id();

            $table->foreignId('empresa_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('id_clip_pro')->unique();

            $table->tinyInteger('tipo'); // 1=cliente,2=proveedor,3=ambos (o como lo manejes)
            $table->enum('tipo_persona', ['natural', 'juridica'])->nullable();

            $table->unsignedTinyInteger('tipo_documento_id');
            $table->foreign('tipo_documento_id')->references('id')->on('tipos_documento');

            $table->string('identificacion')->nullable();
            $table->string('nombre')->nullable();
            $table->string('razon_social')->nullable();
            $table->string('direccion')->nullable();
            $table->string('telefono')->nullable();
            $table->string('email')->nullable();

            $table->enum('clasificacion', ['cliente', 'proveedor', 'cliente_proveedor'])->nullable();
            $table->enum('regimen_tributario', ['comun', 'simplificado', 'especial', 'otro'])->nullable();
            $table->boolean('responsable_iva')->default(false);

            $table->foreignId('ciudad_id')->nullable()->constrained('ciudades');
            $table->foreignId('departamento_id')->nullable()->constrained('departamentos');

            // ⬇️ Campos de crédito
            $table->boolean('permite_credito')->default(false)->index();
            $table->unsignedSmallInteger('dias_credito')->default(0);          // tiempo (días)
            $table->decimal('limite_credito', 15, 2)->default(0);              // monto/cupo

            $table->timestamps();

            // Índices útiles
            $table->index(['empresa_id', 'clasificacion']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('actors');
    }
};
