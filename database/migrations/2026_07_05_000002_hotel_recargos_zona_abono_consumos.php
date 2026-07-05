<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracion_empresas', function (Blueprint $table) {
            // Hora en la que "empieza el día" para el hotel (ej: 14:00). Se usa
            // para calcular cuántas noches lleva un huésped cuando no se
            // definió una fecha de salida al reservar.
            $table->time('hotel_hora_inicio_dia')->default('14:00:00')->after('usa_hotel');
        });

        Schema::table('hotel_habitaciones', function (Blueprint $table) {
            $table->string('zona', 60)->nullable()->after('numero');
            // Recargo fijo por noche (no por persona) cuando la habitación
            // tiene aire/ventilador.
            $table->decimal('recargo_aire', 12, 2)->default(0)->after('precios_por_persona');
            $table->decimal('recargo_ventilador', 12, 2)->default(0)->after('recargo_aire');
        });

        Schema::table('hotel_reservas', function (Blueprint $table) {
            // La fecha de salida puede quedar en blanco cuando el huésped no
            // sabe cuándo se va; las noches se calculan al hacer check-out.
            $table->date('fecha_checkout')->nullable()->change();

            $table->decimal('abono_monto', 12, 2)->default(0)->after('precio_noche');
            $table->string('abono_medio_pago', 20)->nullable()->after('abono_monto');
        });

        // Productos/consumos extra agregados a una reserva mientras el
        // huésped está hospedado (ej. minibar), para cobrarlos junto con el
        // hospedaje al hacer check-out. Es el equivalente a taller_repuestos.
        Schema::create('hotel_reserva_consumos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reserva_id')->constrained('hotel_reservas')->cascadeOnDelete();
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
        Schema::dropIfExists('hotel_reserva_consumos');

        Schema::table('hotel_reservas', function (Blueprint $table) {
            $table->dropColumn(['abono_monto', 'abono_medio_pago']);
            $table->date('fecha_checkout')->nullable(false)->change();
        });

        Schema::table('hotel_habitaciones', function (Blueprint $table) {
            $table->dropColumn(['zona', 'recargo_aire', 'recargo_ventilador']);
        });

        Schema::table('configuracion_empresas', function (Blueprint $table) {
            $table->dropColumn('hotel_hora_inicio_dia');
        });
    }
};
