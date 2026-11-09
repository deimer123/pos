<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Grupo de empresas (sucursales): una sucursal es una empresa normal ligada
// a la empresa del dueño ("matriz"). NULL = empresa independiente, como
// todas las que existen hoy, asi que ninguna fila ni flujo actual cambia.
// Sin llave foranea ni ->after(), igual que las demas migraciones aditivas;
// la integridad (matriz = empresa, sin cadenas) la cuida GrupoEmpresas.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users') || Schema::hasColumn('users', 'empresa_matriz_id')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('empresa_matriz_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'empresa_matriz_id')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_empresa_matriz_id_index');
            $table->dropColumn('empresa_matriz_id');
        });
    }
};
