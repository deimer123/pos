<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Bug real encontrado en auditoria: si el carrito tenia un item de una
// variante (talla/color) o lote puntual y se guardaba como prefactura
// (boton "Guardar"), esa asociacion se perdia -- al recargar la prefactura
// y facturarla, el stock se descontaba del total del producto en vez de la
// variante/lote especifico, igual que el bug ya corregido para "Agregar sin
// escanear" en el POS (ver commit del selector de lote/variante).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prefactura_productos', function (Blueprint $table) {
            $table->unsignedBigInteger('producto_variante_id')->nullable()->after('producto_id');
            $table->unsignedBigInteger('producto_lote_id')->nullable()->after('producto_variante_id');
        });
    }

    public function down(): void
    {
        Schema::table('prefactura_productos', function (Blueprint $table) {
            $table->dropColumn(['producto_variante_id', 'producto_lote_id']);
        });
    }
};
