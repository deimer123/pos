<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ordenes_mesas', function (Blueprint $table) {
            $table->unsignedSmallInteger('numero_cocina_dia')->nullable()->after('observaciones');
            $table->boolean('entregada')->default(false)->after('numero_cocina_dia');
        });
    }

    public function down(): void
    {
        Schema::table('ordenes_mesas', function (Blueprint $table) {
            $table->dropColumn(['numero_cocina_dia', 'entregada']);
        });
    }
};
