<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracion_empresas', function (Blueprint $table) {
            $table->boolean('usa_domicilios')->default(false)->after('usa_servicios');
        });

        Schema::create('domicilios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('users')->cascadeOnDelete();

            // Cliente
            $table->unsignedBigInteger('cliente_id')->nullable();
            $table->string('cliente_nombre', 200);
            $table->string('cliente_telefono', 30)->nullable();

            // Dirección
            $table->string('direccion', 300);
            $table->string('barrio', 100)->nullable();
            $table->string('ciudad', 100)->nullable();
            $table->text('referencia')->nullable();

            // Pedido
            $table->text('observaciones')->nullable();
            $table->decimal('valor_domicilio', 12, 2)->default(0);
            $table->decimal('total_pedido', 12, 2)->default(0);

            // Estado: pendiente → asignado → en_camino → entregado → cancelado
            $table->enum('estado', ['pendiente', 'asignado', 'en_camino', 'entregado', 'cancelado'])
                  ->default('pendiente');

            // Repartidor
            $table->foreignId('repartidor_id')->nullable()->constrained('users')->nullOnDelete();

            // Relación con venta/factura (nullable: puede crearse antes de facturar)
            $table->unsignedBigInteger('venta_id')->nullable();
            $table->unsignedBigInteger('factura_id')->nullable();

            // Quién creó el pedido
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('asignado_at')->nullable();
            $table->timestamp('en_camino_at')->nullable();
            $table->timestamp('entregado_at')->nullable();

            $table->timestamps();

            $table->index(['empresa_id', 'estado']);
            $table->index(['empresa_id', 'repartidor_id']);
        });

        // Items del pedido (productos del domicilio antes de facturar)
        Schema::create('domicilio_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domicilio_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('producto_id');
            $table->string('nombre_producto', 200);
            $table->decimal('cantidad', 10, 3)->default(1);
            $table->decimal('precio', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->text('nota')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domicilio_items');
        Schema::dropIfExists('domicilios');

        Schema::table('configuracion_empresas', function (Blueprint $table) {
            $table->dropColumn('usa_domicilios');
        });
    }
};
