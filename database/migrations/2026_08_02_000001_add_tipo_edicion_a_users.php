<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Que edicion del sistema tiene contratada esta empresa: 'online' (solo
// droplet, el default de siempre), 'hibrida' (Online + Turion, puede
// emparejar/descargar Sistema POS Offline) o 'local' (edicion standalone
// por licencia con codigo -- ver EmitirLicenciaLocal/LicenciaLocal). Se
// controla desde EmpresaResource (solo super_admin).
//
// 'local' ademas bloquea el login normal de esa empresa (y sus empleados)
// al droplet (ver User::puedeIngresarPorPlan()): un cliente Local no tiene
// cuenta usable en el droplet, su unica interfaz es la app de escritorio
// activada con codigo -- esta fila solo existe como ancla para emitirle
// licencias y llevar la cuenta de a quien se le vendio que.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('tipo_edicion', ['online', 'hibrida', 'local'])
                ->default('online')
                ->after('es_empresa_emisora');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('tipo_edicion');
        });
    }
};
