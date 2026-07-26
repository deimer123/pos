<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Columna exclusiva de la base de datos local de Turion (SQLite) -- ver
// 2026_07_25_191109_add_servidor_id_to_prefacturas_table.php, mismo
// proposito pero para reservas de hotel (HotelSyncPull).
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        Schema::table('hotel_reservas', function (Blueprint $table) {
            $table->unsignedBigInteger('servidor_id')->nullable()->after('id');
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        Schema::table('hotel_reservas', function (Blueprint $table) {
            $table->dropColumn('servidor_id');
        });
    }
};
