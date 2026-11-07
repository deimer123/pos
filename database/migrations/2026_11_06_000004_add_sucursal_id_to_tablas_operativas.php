<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Prepara las tablas operativas para el modulo de sucursales: cada una
// recibe un sucursal_id NULLABLE e indexado. NULL significa "comportamiento
// de siempre" (empresa sin sucursales), asi que ninguna fila existente ni
// ningun flujo actual cambia. Las tablas hijas (detalles, pagos) no llevan
// la columna: heredan la sucursal de su registro padre.
//
// A proposito:
//  - Sin llave foranea: agregarla en una tabla grande obliga a MySQL a
//    reconstruirla y bloquea las escrituras; la integridad la cuida la app.
//  - Sin ->after(): la columna se agrega al final de la tabla, que es el
//    caso en el que MySQL 8 no necesita reconstruirla.
//  - Se salta lo que ya exista, para poder reintentar sin problema si algo
//    se corta a la mitad (MySQL no revierte los cambios de estructura).
return new class extends Migration
{
    private const TABLAS = [
        // Ventas
        'facturas',
        'nota_creditos',
        'prefacturas',
        'devoluciones',
        // Compras
        'compras',
        'devolucion_compras',
        // Dinero
        'cajas',
        'gastos',
        // Inventario
        'ajustes_inventario',
        'stock_movimientos',
        'kardex',
    ];

    public function up(): void
    {
        foreach (self::TABLAS as $tabla) {
            if (! Schema::hasTable($tabla) || Schema::hasColumn($tabla, 'sucursal_id')) {
                continue;
            }

            Schema::table($tabla, function (Blueprint $table) {
                $table->unsignedBigInteger('sucursal_id')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLAS as $tabla) {
            if (! Schema::hasTable($tabla) || ! Schema::hasColumn($tabla, 'sucursal_id')) {
                continue;
            }

            Schema::table($tabla, function (Blueprint $table) use ($tabla) {
                $table->dropIndex($tabla . '_sucursal_id_index');
                $table->dropColumn('sucursal_id');
            });
        }
    }
};
