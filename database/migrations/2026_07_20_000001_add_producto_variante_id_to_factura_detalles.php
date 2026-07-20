<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Igual que "producto_id" en esta misma tabla, sin FK real a proposito
// (ver comentario en la migracion original de factura_detalles): algunas
// lineas sinteticas (empaque, abono) usan valores que no corresponden a
// un producto real.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('factura_detalles', function (Blueprint $table) {
            $table->unsignedBigInteger('producto_variante_id')->nullable()->after('producto_id');
        });
    }

    public function down(): void
    {
        Schema::table('factura_detalles', function (Blueprint $table) {
            $table->dropColumn('producto_variante_id');
        });
    }
};
