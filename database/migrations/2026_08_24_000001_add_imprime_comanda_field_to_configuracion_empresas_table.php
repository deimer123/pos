<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracion_empresas', function (Blueprint $table) {
            $table->boolean('imprime_comanda_en_lugar_de_cocina')->default(false)->after('usa_cocina');
        });
    }

    public function down(): void
    {
        Schema::table('configuracion_empresas', function (Blueprint $table) {
            $table->dropColumn('imprime_comanda_en_lugar_de_cocina');
        });
    }
};
