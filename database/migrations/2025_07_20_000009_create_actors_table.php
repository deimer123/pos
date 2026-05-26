<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('actors', function (Blueprint $table) {
            $table->id(); // 👈 PK real (para relaciones)

            $table->foreignId('empresa_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // ID externo (NO FK)
            $table->unsignedBigInteger('id_clip_pro');

            $table->tinyInteger('tipo'); // 1=cliente,2=proveedor,3=ambos
            $table->enum('tipo_persona', ['natural', 'juridica'])->nullable();

            $table->unsignedTinyInteger('tipo_documento_id');
            $table->foreign('tipo_documento_id')
                ->references('id')
                ->on('tipos_documento');

            $table->string('identificacion')->nullable();
            $table->string('nombre')->nullable();
            $table->string('razon_social')->nullable();
            $table->string('direccion')->nullable();
            $table->string('telefono')->nullable();
            $table->string('email')->nullable();

            $table->enum('clasificacion', [
                'cliente',
                'proveedor',
                'cliente_proveedor'
            ])->nullable();

            $table->enum('regimen_tributario', [
                'comun',
                'simplificado',
                'especial',
                'otro'
            ])->nullable();

            $table->boolean('responsable_iva')->default(false);

            $table->foreignId('departamento_id')
                ->nullable()
                ->constrained('departamentos');

            $table->foreignId('ciudad_id')
                ->nullable()
                ->constrained('ciudades');

            // Crédito
            $table->boolean('permite_credito')->default(false)->index();
            $table->unsignedSmallInteger('dias_credito')->default(0);
            $table->decimal('limite_credito', 15, 2)->default(0);

            $table->timestamps();

            // 🔐 id_clip_pro es único POR EMPRESA
            $table->unique(
                ['empresa_id', 'id_clip_pro'],
                'actors_empresa_clip_unique'
            );

            $table->index(['empresa_id', 'clasificacion']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('actors');
    }
};
