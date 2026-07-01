<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('taller_ordenes', function (Blueprint $table) {
            $table->json('fotos')->nullable()->after('observaciones');
        });

        Schema::table('taller_repuestos', function (Blueprint $table) {
            $table->enum('tipo', ['repuesto', 'mano_obra', 'tercero'])->default('repuesto')->after('subtotal');
            $table->decimal('costo_proveedor', 12, 2)->default(0)->after('tipo');
        });
    }

    public function down(): void
    {
        Schema::table('taller_ordenes', function (Blueprint $table) {
            $table->dropColumn('fotos');
        });

        Schema::table('taller_repuestos', function (Blueprint $table) {
            $table->dropColumn(['tipo', 'costo_proveedor']);
        });
    }
};
