<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Tabla exclusiva de una instalacion SQLite standalone (Turion o Local --
// ver App\Support\PosEdition): guarda si esta instalacion ya se activo con
// un codigo firmado por el droplet. Solo tiene sentido/se consulta en la
// edicion Local (ver EnsureLicenciaLocalActiva), pero corre en todas las
// instalaciones sqlite igual que pending_sync_operations/sync_state, para
// no mantener un esquema distinto por edicion. A lo sumo una fila.
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        Schema::create('activacion_local', function (Blueprint $table) {
            $table->id();
            $table->uuid('licencia_id');
            $table->unsignedBigInteger('empresa_id');
            $table->string('empresa_nombre');
            $table->string('machine_id');
            $table->text('codigo_raw');
            $table->timestamp('activada_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        Schema::dropIfExists('activacion_local');
    }
};
