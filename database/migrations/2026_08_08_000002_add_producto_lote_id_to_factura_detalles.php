<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Calco de 2026_07_20_000001_add_producto_variante_id_to_factura_detalles.php.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('factura_detalles', function (Blueprint $table) {
            $table->unsignedBigInteger('producto_lote_id')->nullable()->after('producto_variante_id');
        });
    }

    public function down(): void
    {
        Schema::table('factura_detalles', function (Blueprint $table) {
            $table->dropColumn('producto_lote_id');
        });
    }
};
