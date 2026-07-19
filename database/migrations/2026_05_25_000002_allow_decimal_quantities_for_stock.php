<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Reescrita con Schema::table()->change() (antes usaba DB::statement con
// "ALTER TABLE ... MODIFY", sintaxis exclusiva de MySQL) para poder correr
// esta migracion tambien contra SQLite (necesario para la base de datos
// local de Turion). Requiere doctrine/dbal, ya presente en composer.lock.
return new class extends Migration
{
    public function up(): void
    {
        // SQLite hace ->change() reconstruyendo la tabla entera (copia a una
        // tabla temporal y la reemplaza); con las llaves foraneas activas
        // eso choca contra tablas que referencian a "products" (ej.
        // prefactura_productos) durante la reconstruccion. Se desactivan
        // solo mientras dura este cambio puntual.
        Schema::withoutForeignKeyConstraints(function () {
            Schema::table('products', function (Blueprint $table) {
                $table->decimal('existencias', 14, 2)->default(0)->change();
            });

            Schema::table('kardex', function (Blueprint $table) {
                $table->decimal('entrada', 14, 2)->default(0)->change();
                $table->decimal('salida', 14, 2)->default(0)->change();
                $table->decimal('stock_anterior', 14, 2)->default(0)->change();
                $table->decimal('stock_actual', 14, 2)->default(0)->change();
            });

            Schema::table('prefactura_productos', function (Blueprint $table) {
                $table->decimal('cantidad', 14, 2)->default(1)->change();
            });
        });
    }

    public function down(): void
    {
        Schema::withoutForeignKeyConstraints(function () {
            Schema::table('prefactura_productos', function (Blueprint $table) {
                $table->integer('cantidad')->default(1)->change();
            });

            Schema::table('kardex', function (Blueprint $table) {
                $table->integer('stock_actual')->default(0)->change();
                $table->integer('stock_anterior')->default(0)->change();
                $table->integer('salida')->default(0)->change();
                $table->integer('entrada')->default(0)->change();
            });

            Schema::table('products', function (Blueprint $table) {
                $table->integer('existencias')->default(0)->change();
            });
        });
    }
};
