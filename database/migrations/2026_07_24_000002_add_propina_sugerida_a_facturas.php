<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// La propina es una sugerencia informativa para el cliente, NO se suma al
// total de la factura ni entra a comisiones/contabilidad de la empresa
// (es plata que el cliente le da directo al mesero si quiere). Se guarda
// aparte solo para poder imprimirla en el ticket.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->decimal('propina_sugerida', 12, 2)->nullable()->after('costo_empaque');
        });
    }

    public function down(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->dropColumn('propina_sugerida');
        });
    }
};
