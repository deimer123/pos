<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Columna exclusiva de la base de datos local de Turion (SQLite): guarda
// el id que esa prefactura tiene en el droplet -- tanto si se creo alla y
// se bajo al sincronizar, como si se creo en Turion y ya se subio. Sirve
// para saber si una prefactura local ya tiene contraparte en el droplet
// (actualizarla al bajar/subir) y para detectar cuando una ya se facturo
// o se borro alla (deja de aparecer en la lista que se baja al
// sincronizar, y se borra tambien aqui).
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        Schema::table('prefacturas', function (Blueprint $table) {
            $table->unsignedBigInteger('servidor_id')->nullable()->after('id');
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        Schema::table('prefacturas', function (Blueprint $table) {
            $table->dropColumn('servidor_id');
        });
    }
};
