<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Reescrita con Schema::table() (antes usaba DB::statement con
// "ALTER TABLE ... ADD COLUMN ... AFTER", sintaxis exclusiva de MySQL) para
// poder correr esta migracion tambien contra SQLite. El ->after() se
// mantiene: MySQL lo respeta, SQLite simplemente lo ignora y agrega la
// columna al final -- inofensivo.
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('facturas', 'tipo_pedido')) {
            Schema::table('facturas', function (Blueprint $table) {
                $table->enum('tipo_pedido', ['local', 'domicilio', 'para_llevar'])->default('local')->after('transferencia_obs');
            });
        }

        if (!Schema::hasColumn('facturas', 'costo_empaque')) {
            Schema::table('facturas', function (Blueprint $table) {
                $table->decimal('costo_empaque', 10, 2)->default(0)->after('tipo_pedido');
            });
        }

        if (!Schema::hasColumn('facturas', 'dom_nombre')) {
            Schema::table('facturas', function (Blueprint $table) {
                $table->string('dom_nombre', 200)->nullable()->after('costo_empaque');
            });
        }

        if (!Schema::hasColumn('facturas', 'dom_telefono')) {
            Schema::table('facturas', function (Blueprint $table) {
                $table->string('dom_telefono', 30)->nullable()->after('dom_nombre');
            });
        }

        if (!Schema::hasColumn('facturas', 'dom_direccion')) {
            Schema::table('facturas', function (Blueprint $table) {
                $table->string('dom_direccion', 300)->nullable()->after('dom_telefono');
            });
        }

        if (!Schema::hasColumn('facturas', 'dom_ciudad')) {
            Schema::table('facturas', function (Blueprint $table) {
                $table->string('dom_ciudad', 100)->nullable()->after('dom_direccion');
            });
        }

        if (!Schema::hasColumn('facturas', 'dom_nit')) {
            Schema::table('facturas', function (Blueprint $table) {
                $table->string('dom_nit', 30)->nullable()->after('dom_ciudad');
            });
        }

        if (!Schema::hasColumn('facturas', 'dom_email')) {
            Schema::table('facturas', function (Blueprint $table) {
                $table->string('dom_email', 150)->nullable()->after('dom_nit');
            });
        }

        if (!Schema::hasColumn('facturas', 'dom_razon_social')) {
            Schema::table('facturas', function (Blueprint $table) {
                $table->string('dom_razon_social', 200)->nullable()->after('dom_email');
            });
        }

        if (!Schema::hasColumn('facturas', 'cobro_domicilio')) {
            Schema::table('facturas', function (Blueprint $table) {
                $table->enum('cobro_domicilio', ['anticipado', 'entrega'])->default('anticipado')->after('dom_razon_social');
            });
        }

        if (!Schema::hasColumn('ordenes_mesas', 'tipo_pedido')) {
            Schema::table('ordenes_mesas', function (Blueprint $table) {
                $table->enum('tipo_pedido', ['mesa', 'domicilio', 'para_llevar'])->default('mesa');
            });
        }

        if (!Schema::hasColumn('ordenes_mesas', 'costo_empaque')) {
            Schema::table('ordenes_mesas', function (Blueprint $table) {
                $table->decimal('costo_empaque', 10, 2)->default(0);
            });
        }

        if (!Schema::hasColumn('ordenes_mesas', 'dom_nombre')) {
            Schema::table('ordenes_mesas', function (Blueprint $table) {
                $table->string('dom_nombre', 200)->nullable();
            });
        }

        if (!Schema::hasColumn('ordenes_mesas', 'dom_telefono')) {
            Schema::table('ordenes_mesas', function (Blueprint $table) {
                $table->string('dom_telefono', 30)->nullable();
            });
        }

        if (!Schema::hasColumn('ordenes_mesas', 'dom_direccion')) {
            Schema::table('ordenes_mesas', function (Blueprint $table) {
                $table->string('dom_direccion', 300)->nullable();
            });
        }

        if (!Schema::hasColumn('configuracion_empresas', 'usa_domicilios')) {
            Schema::table('configuracion_empresas', function (Blueprint $table) {
                $table->boolean('usa_domicilios')->default(false);
            });
        }
    }

    public function down(): void
    {
        $facturasCols = ['tipo_pedido', 'costo_empaque', 'dom_nombre', 'dom_telefono', 'dom_direccion', 'dom_ciudad', 'dom_nit', 'dom_email', 'dom_razon_social', 'cobro_domicilio'];
        foreach ($facturasCols as $col) {
            if (Schema::hasColumn('facturas', $col)) {
                Schema::table('facturas', fn (Blueprint $table) => $table->dropColumn($col));
            }
        }

        $ordenesCols = ['tipo_pedido', 'costo_empaque', 'dom_nombre', 'dom_telefono', 'dom_direccion'];
        foreach ($ordenesCols as $col) {
            if (Schema::hasColumn('ordenes_mesas', $col)) {
                Schema::table('ordenes_mesas', fn (Blueprint $table) => $table->dropColumn($col));
            }
        }

        if (Schema::hasColumn('configuracion_empresas', 'usa_domicilios')) {
            Schema::table('configuracion_empresas', fn (Blueprint $table) => $table->dropColumn('usa_domicilios'));
        }
    }
};
