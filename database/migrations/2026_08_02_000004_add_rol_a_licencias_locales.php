<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Rol que va firmado dentro del propio codigo (App\Services\LocalLicense\
// CodigoActivacion::rol) -- esta columna es solo para que el super_admin
// vea/filtre facil desde el listado que emite (App\Filament\Pages\
// EmitirLicenciaLocal), la fuente de verdad real es el codigo firmado.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('licencias_locales', function (Blueprint $table) {
            $table->string('rol', 20)->default('servidor')->after('machine_label');
        });
    }

    public function down(): void
    {
        Schema::table('licencias_locales', function (Blueprint $table) {
            $table->dropColumn('rol');
        });
    }
};
