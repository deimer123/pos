<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_reservas', function (Blueprint $table) {
            $table->string('climatizacion', 20)->nullable()->after('numero_personas');
        });
    }

    public function down(): void
    {
        Schema::table('hotel_reservas', function (Blueprint $table) {
            $table->dropColumn('climatizacion');
        });
    }
};
