<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// SQLite no soporta dropForeign() (necesitaria reconstruir la tabla entera
// a mano); se deja como no-op ahi -- es solo un ajuste de onDelete=cascade
// sobre una llave foranea ya existente, no afecta la estructura de datos
// que necesita la base de datos local de Turion.
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('prefactura_productos', function (Blueprint $table) {
            $table->dropForeign(['producto_id']);
            $table->foreign('producto_id')
                ->references('id_producto')
                ->on('products')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('prefactura_productos', function (Blueprint $table) {
            $table->dropForeign(['producto_id']);
            $table->foreign('producto_id')
                ->references('id_producto')
                ->on('products');
        });
    }
};
