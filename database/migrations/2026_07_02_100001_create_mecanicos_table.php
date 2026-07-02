<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mecanicos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id');
            $table->string('nombre');
            $table->string('cedula')->nullable();
            $table->string('telefono')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->foreign('empresa_id')->references('id')->on('empresas')->cascadeOnDelete();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->unsignedBigInteger('mecanico_id')->nullable()->after('porcentaje_empresa');
            $table->string('tercero_nombre')->nullable()->after('mecanico_id');
            $table->foreign('mecanico_id')->references('id')->on('mecanicos')->nullOnDelete();
        });

        Schema::table('factura_detalles', function (Blueprint $table) {
            $table->unsignedBigInteger('mecanico_id')->nullable()->after('porcentaje_empresa');
            $table->foreign('mecanico_id')->references('id')->on('mecanicos')->nullOnDelete();
        });

        Schema::create('liquidaciones_mecanico', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('mecanico_id');
            $table->date('fecha_desde');
            $table->date('fecha_hasta');
            $table->decimal('total_servicios', 14, 2)->default(0);
            $table->decimal('porcentaje_mecanico', 5, 2)->default(0);
            $table->decimal('monto_mecanico', 14, 2)->default(0);
            $table->string('estado', 20)->default('pendiente'); // pendiente | pagado
            $table->date('fecha_pago')->nullable();
            $table->string('medio_pago', 50)->nullable();
            $table->text('notas')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->timestamps();
            $table->foreign('empresa_id')->references('id')->on('empresas')->cascadeOnDelete();
            $table->foreign('mecanico_id')->references('id')->on('mecanicos')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('liquidacion_mecanico_detalles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('liquidacion_id');
            $table->unsignedBigInteger('factura_detalle_id');
            $table->decimal('subtotal_servicio', 14, 2);
            $table->decimal('monto_mecanico', 14, 2);
            $table->timestamps();
            $table->foreign('liquidacion_id')->references('id')->on('liquidaciones_mecanico')->cascadeOnDelete();
            $table->foreign('factura_detalle_id')->references('id')->on('factura_detalles')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('liquidacion_mecanico_detalles');
        Schema::dropIfExists('liquidaciones_mecanico');
        Schema::table('factura_detalles', function (Blueprint $table) {
            $table->dropForeign(['mecanico_id']);
            $table->dropColumn('mecanico_id');
        });
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['mecanico_id']);
            $table->dropColumn(['mecanico_id', 'tercero_nombre']);
        });
        Schema::dropIfExists('mecanicos');
    }
};
