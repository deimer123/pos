<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Columna exclusiva de la base de datos local de Turion (SQLite): igual
// que prefacturas.servidor_id, guarda el id real que ese cliente tiene en
// el droplet. Mientras sea null, el cliente se creo offline y todavia no
// se ha subido -- CatalogoImportador::sembrar() lo protege de que
// "Sincronizar" lo borre al reemplazar el catalogo con lo del servidor.
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        Schema::table('actors', function (Blueprint $table) {
            $table->unsignedBigInteger('servidor_id')->nullable()->after('id');
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        Schema::table('actors', function (Blueprint $table) {
            $table->dropColumn('servidor_id');
        });
    }
};
