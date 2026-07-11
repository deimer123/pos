<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracion_empresas', function (Blueprint $table) {
            $table->decimal('descuento_maximo_permitido', 5, 2)->nullable()->default(100)->after('mostrar_iva_separado');
            $table->boolean('permite_ver_stock_no_admin')->default(true)->after('descuento_maximo_permitido');
        });

        // El sistema siempre permitió vender en negativo aunque esta columna
        // ya existía en false para todas las empresas. Al conectar el flag de
        // verdad, preservamos ese comportamiento histórico para no bloquear
        // ventas en empresas que ya están operando.
        DB::table('configuracion_empresas')->update(['permite_stock_negativo' => true]);
    }

    public function down(): void
    {
        Schema::table('configuracion_empresas', function (Blueprint $table) {
            $table->dropColumn(['descuento_maximo_permitido', 'permite_ver_stock_no_admin']);
        });
    }
};
