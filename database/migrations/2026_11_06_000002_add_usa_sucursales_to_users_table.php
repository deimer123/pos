<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Interruptor del super_admin: solo las empresas con usa_sucursales=true
// manejan sucursales. Queda en users (junto a tipo_edicion, plan y limites)
// porque lo decide el super_admin, no el admin de la empresa. Todas las
// empresas existentes quedan en false, o sea, funcionan exactamente igual
// que hoy.
//
// Sin ->after(): la columna se agrega al final de la tabla, que es el caso
// en el que MySQL 8 no necesita reconstruirla.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'usa_sucursales')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('usa_sucursales')->default(false);
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'usa_sucursales')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('usa_sucursales');
        });
    }
};
