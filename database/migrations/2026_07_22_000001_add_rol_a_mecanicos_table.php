<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Generaliza la tabla "mecanicos" (y todo el motor de liquidacion que ya
// usa taller) para que tambien sirva de roster de vendedores (tiendas/
// ropa/carniceria) y meseros (bar y restaurante) -- sin tocar la tabla
// ni el flujo existente de talleres, solo se le agregan columnas
// nuevas con default seguro ('mecanico') para no romper datos actuales.
//
// - "rol": distingue que tipo de colaborador es este registro.
// - "user_id": solo aplica a vendedor/mesero (SI tienen login, a
//   diferencia del mecanico de taller que es un registro puramente
//   administrativo) -- permite resolver automaticamente "que colaborador
//   es el cajero que esta logueado ahora mismo" al facturar.
// - "porcentaje_comision": % individual de ESE colaborador (para
//   vendedor/mesero, el % vive en la persona, no en el producto como
//   pasa con los servicios de taller).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mecanicos', function (Blueprint $table) {
            $table->string('rol', 20)->default('mecanico')->after('empresa_id');
            $table->unsignedBigInteger('user_id')->nullable()->after('rol');
            $table->decimal('porcentaje_comision', 5, 2)->nullable()->after('user_id');

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('mecanicos', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn(['rol', 'user_id', 'porcentaje_comision']);
        });
    }
};
