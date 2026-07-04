<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracion_empresas', function (Blueprint $table) {
            $table->boolean('usa_hotel')->default(false)->after('usa_taller');
        });

        Schema::create('hotel_habitaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('users')->cascadeOnDelete();
            $table->string('numero', 20);
            $table->unsignedTinyInteger('camas_dobles')->default(0);
            $table->unsignedTinyInteger('camas_sencillas')->default(0);
            $table->boolean('tiene_aire')->default(false);
            $table->boolean('tiene_ventilador')->default(false);
            $table->decimal('precio_persona_noche', 12, 2)->default(0);
            $table->boolean('activa')->default(true);
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->unique(['empresa_id', 'numero']);
        });

        Schema::create('hotel_reservas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('habitacion_id')->constrained('hotel_habitaciones')->cascadeOnDelete();
            $table->unsignedInteger('numero_reserva');

            $table->string('huesped_nombre', 200);
            $table->string('huesped_telefono', 30)->nullable();
            $table->string('huesped_documento', 50)->nullable();
            $table->unsignedTinyInteger('numero_personas')->default(1);

            $table->date('fecha_checkin');
            $table->date('fecha_checkout');
            $table->timestamp('checkin_real_at')->nullable();
            $table->timestamp('checkout_real_at')->nullable();

            $table->decimal('precio_persona_noche', 12, 2)->default(0);

            $table->enum('estado', ['reservada', 'checkin', 'checkout', 'cancelada'])
                  ->default('reservada');

            $table->unsignedBigInteger('factura_id')->nullable();
            $table->text('observaciones')->nullable();
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['empresa_id', 'numero_reserva']);
            $table->index(['empresa_id', 'estado']);
            $table->index(['habitacion_id', 'fecha_checkin', 'fecha_checkout']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_reservas');
        Schema::dropIfExists('hotel_habitaciones');

        Schema::table('configuracion_empresas', function (Blueprint $table) {
            $table->dropColumn('usa_hotel');
        });
    }
};
