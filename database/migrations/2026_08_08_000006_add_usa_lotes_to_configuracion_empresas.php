<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Generaliza "maneja lotes" (antes amarrado a tipo_negocio=drogueria) a un
// toggle universal, igual que usa_variantes -- cualquier tienda/viveres
// puede activarlo, no solo droguería.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracion_empresas', function (Blueprint $table) {
            $table->boolean('usa_lotes')->default(false)->after('usa_variantes');
        });

        // Las empresas de droguería que ya existen quedan con el toggle
        // prendido de una vez -- antes el mecanismo de lotes estaba
        // amarrado directo a tipo_negocio=drogueria, asi que todas ya lo
        // usaban de hecho.
        DB::table('configuracion_empresas')->where('tipo_negocio', 'drogueria')->update(['usa_lotes' => true]);
    }

    public function down(): void
    {
        Schema::table('configuracion_empresas', function (Blueprint $table) {
            $table->dropColumn('usa_lotes');
        });
    }
};
