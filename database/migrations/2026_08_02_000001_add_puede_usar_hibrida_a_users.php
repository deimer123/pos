<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Controla si una empresa tiene habilitada la edicion Hibrida (Turion --
// sincroniza con el droplet). Antes de esto, cualquier admin_empresa veia
// los botones "Emparejar equipo offline"/"Descargar Sistema POS Offline"
// sin importar si el super_admin habia decidido venderle esa edicion. Se
// controla desde EmpresaResource (solo super_admin); EditConfiguracionEmpresa
// oculta esos botones si esta en false. Default false: hay que habilitarla
// a proposito por empresa, no viene activada de entrada.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('puede_usar_hibrida')->default(false)->after('es_empresa_emisora');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('puede_usar_hibrida');
        });
    }
};
