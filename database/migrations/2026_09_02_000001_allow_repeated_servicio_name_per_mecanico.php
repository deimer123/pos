<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// El mismo servicio (ej. "Cambio de pantalla" o "Cambio de aceite") lo
// puede ejecutar mas de un mecanico/tecnico -- cada uno con su propia fila
// en products (su propio precio y % de ganancia) -- asi que el nombre ya no
// puede ser unico solo por empresa, debe permitir repetirse mientras el
// mecanico_id sea distinto (o ambos nulos para servicios a terceros /
// productos normales, que siguen protegidos por la validacion de la app en
// ProductResource y ServicioResource).
return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['descripcion_larga', 'empresa_id']);
            $table->unique(['descripcion_larga', 'empresa_id', 'mecanico_id']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['descripcion_larga', 'empresa_id', 'mecanico_id']);
            $table->unique(['descripcion_larga', 'empresa_id']);
        });
    }
};
