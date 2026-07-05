<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // El precio del hospedaje ya no es un único valor "por persona": cada
        // habitación define un precio de noche distinto según cuántas
        // personas la ocupen (1, 2, 3...), reflejando por ejemplo que una
        // habitación con aire/ventilador se puede cobrar más que una sin.
        Schema::table('hotel_habitaciones', function (Blueprint $table) {
            $table->json('precios_por_persona')->nullable()->after('tiene_ventilador');
        });

        Schema::table('hotel_reservas', function (Blueprint $table) {
            $table->decimal('precio_noche', 12, 2)->default(0)->after('numero_personas');
        });

        Schema::table('hotel_habitaciones', function (Blueprint $table) {
            $table->dropColumn('precio_persona_noche');
        });

        Schema::table('hotel_reservas', function (Blueprint $table) {
            $table->dropColumn('precio_persona_noche');
        });
    }

    public function down(): void
    {
        Schema::table('hotel_habitaciones', function (Blueprint $table) {
            $table->decimal('precio_persona_noche', 12, 2)->default(0);
            $table->dropColumn('precios_por_persona');
        });

        Schema::table('hotel_reservas', function (Blueprint $table) {
            $table->decimal('precio_persona_noche', 12, 2)->default(0);
            $table->dropColumn('precio_noche');
        });
    }
};
