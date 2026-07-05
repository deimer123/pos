<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_reservas', function (Blueprint $table) {
            $table->unsignedBigInteger('actor_id')->nullable()->after('habitacion_id');
            $table->index('actor_id');
        });
    }

    public function down(): void
    {
        Schema::table('hotel_reservas', function (Blueprint $table) {
            $table->dropIndex(['actor_id']);
            $table->dropColumn('actor_id');
        });
    }
};
