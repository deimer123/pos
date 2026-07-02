<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('liquidaciones_mecanico', function (Blueprint $table) {
            $table->decimal('prestamos_descontados', 14, 2)->default(0)->after('monto_mecanico');
            $table->decimal('monto_neto', 14, 2)->default(0)->after('prestamos_descontados');
        });
    }

    public function down(): void
    {
        Schema::table('liquidaciones_mecanico', function (Blueprint $table) {
            $table->dropColumn(['prestamos_descontados', 'monto_neto']);
        });
    }
};
