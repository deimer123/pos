<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Activar taller en configuración empresa
        Schema::table('configuracion_empresas', function (Blueprint $table) {
            $table->boolean('usa_taller')->default(false)->after('usa_domicilios');
        });

        Schema::create('taller_ordenes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('numero_orden');

            // Cliente
            $table->unsignedBigInteger('cliente_id')->nullable();
            $table->string('cliente_nombre', 200);
            $table->string('cliente_telefono', 30)->nullable();

            // Vehículo
            $table->string('placa', 20);
            $table->string('marca', 80)->nullable();
            $table->string('modelo', 80)->nullable();
            $table->string('color', 50)->nullable();
            $table->unsignedInteger('km_ingreso')->nullable();

            // Trabajo
            $table->text('diagnostico')->nullable();
            $table->text('observaciones')->nullable();

            // Estado: pendiente → en_proceso → listo → entregado | cancelado
            $table->enum('estado', ['pendiente', 'en_proceso', 'listo', 'entregado', 'cancelado'])
                  ->default('pendiente');

            // Factura vinculada al cerrar
            $table->unsignedBigInteger('factura_id')->nullable();

            // Mesa virtual usada para facturar
            $table->unsignedBigInteger('mesa_id')->nullable();

            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('fecha_entrega_estimada')->nullable();
            $table->timestamp('entregado_at')->nullable();

            $table->timestamps();

            $table->unique(['empresa_id', 'numero_orden']);
            $table->index(['empresa_id', 'estado']);
        });

        Schema::create('taller_repuestos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orden_id')->constrained('taller_ordenes')->cascadeOnDelete();
            $table->unsignedBigInteger('producto_id')->nullable();
            $table->string('descripcion', 250);
            $table->decimal('cantidad', 10, 3)->default(1);
            $table->decimal('precio_unitario', 12, 2)->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taller_repuestos');
        Schema::dropIfExists('taller_ordenes');

        Schema::table('configuracion_empresas', function (Blueprint $table) {
            $table->dropColumn('usa_taller');
        });
    }
};
