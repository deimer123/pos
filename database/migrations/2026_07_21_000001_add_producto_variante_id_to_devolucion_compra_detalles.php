<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devolucion_compra_detalles', function (Blueprint $table) {
            $table->unsignedBigInteger('producto_variante_id')->nullable()->after('product_id');
        });
    }

    public function down(): void
    {
        Schema::table('devolucion_compra_detalles', function (Blueprint $table) {
            $table->dropColumn('producto_variante_id');
        });
    }
};
