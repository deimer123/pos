<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Hasta ahora activacion_local tenia a lo sumo una fila (la del propio
// equipo). Con "varios terminales contra un servidor local" pasa a tener
// una fila por cada equipo activado contra ESTE servidor: la propia
// ('servidor') y una por cada terminal emparejado ('cliente') -- ver
// EmparejarTerminalLocalController. Default 'servidor' para que las filas
// ya existentes (creadas antes de este campo) sigan siendo correctas.
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        Schema::table('activacion_local', function (Blueprint $table) {
            $table->string('rol', 20)->default('servidor')->after('machine_id');
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        Schema::table('activacion_local', function (Blueprint $table) {
            $table->dropColumn('rol');
        });
    }
};
