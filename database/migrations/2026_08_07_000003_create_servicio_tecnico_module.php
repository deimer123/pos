<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modulo "Servicio Tecnico de Celulares": mismo concepto que
 * create_taller_module.php (ordenes de trabajo + items), pero en tablas
 * propias -- no comparte taller_ordenes/taller_repuestos a proposito, para
 * no arriesgar el modulo de taller de motos ya en produccion. Los tecnicos
 * SI reutilizan la tabla `mecanicos` existente (ver Mecanico::ROL_TECNICO),
 * junto con liquidaciones_mecanico/mecanico_prestamos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servicio_tecnico_ordenes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('users')->cascadeOnDelete();
            // Igual que taller_ordenes.servidor_id: solo tiene valor en la
            // base de datos local de Turion, referencia al id de esta
            // misma orden en el droplet (ver ServicioTecnicoSyncPull).
            $table->unsignedBigInteger('servidor_id')->nullable();
            $table->unsignedInteger('numero_orden');

            // Cliente
            $table->unsignedBigInteger('cliente_id')->nullable();
            $table->string('cliente_nombre', 200);
            $table->string('cliente_telefono', 30)->nullable();

            // Equipo
            $table->string('marca', 80)->nullable();
            $table->string('modelo', 80)->nullable();
            $table->string('imei_serial', 60)->nullable();
            $table->string('color', 50)->nullable();
            $table->string('clave_desbloqueo', 50)->nullable();

            // Trabajo
            $table->text('diagnostico')->nullable();
            $table->text('observaciones')->nullable();
            $table->text('nota_trabajo')->nullable();

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

            $table->json('fotos')->nullable();
            $table->json('videos')->nullable();

            $table->timestamps();

            $table->unique(['empresa_id', 'numero_orden']);
            $table->index(['empresa_id', 'estado']);
        });

        Schema::create('servicio_tecnico_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orden_id')->constrained('servicio_tecnico_ordenes')->cascadeOnDelete();
            $table->unsignedBigInteger('producto_id')->nullable();
            $table->string('descripcion', 250);
            $table->decimal('cantidad', 10, 3)->default(1);
            $table->decimal('precio_unitario', 12, 2)->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('costo_proveedor', 12, 2)->default(0);
            $table->string('tipo', 20)->default('repuesto');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servicio_tecnico_items');
        Schema::dropIfExists('servicio_tecnico_ordenes');
    }
};
