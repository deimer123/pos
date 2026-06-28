<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Columnas faltantes en facturas
        $facturasCols = [
            'tipo_pedido'     => "ALTER TABLE facturas ADD COLUMN tipo_pedido ENUM('local','domicilio','para_llevar') DEFAULT 'local' AFTER transferencia_obs",
            'costo_empaque'   => "ALTER TABLE facturas ADD COLUMN costo_empaque DECIMAL(10,2) DEFAULT 0 AFTER tipo_pedido",
            'dom_nombre'      => "ALTER TABLE facturas ADD COLUMN dom_nombre VARCHAR(200) NULL AFTER costo_empaque",
            'dom_telefono'    => "ALTER TABLE facturas ADD COLUMN dom_telefono VARCHAR(30) NULL AFTER dom_nombre",
            'dom_direccion'   => "ALTER TABLE facturas ADD COLUMN dom_direccion VARCHAR(300) NULL AFTER dom_telefono",
            'dom_ciudad'      => "ALTER TABLE facturas ADD COLUMN dom_ciudad VARCHAR(100) NULL AFTER dom_direccion",
            'dom_nit'         => "ALTER TABLE facturas ADD COLUMN dom_nit VARCHAR(30) NULL AFTER dom_ciudad",
            'dom_email'       => "ALTER TABLE facturas ADD COLUMN dom_email VARCHAR(150) NULL AFTER dom_nit",
            'dom_razon_social'=> "ALTER TABLE facturas ADD COLUMN dom_razon_social VARCHAR(200) NULL AFTER dom_email",
            'cobro_domicilio' => "ALTER TABLE facturas ADD COLUMN cobro_domicilio ENUM('anticipado','entrega') DEFAULT 'anticipado' AFTER dom_razon_social",
        ];

        foreach ($facturasCols as $col => $sql) {
            if (!$this->columnExists('facturas', $col)) {
                DB::statement($sql);
            }
        }

        // Columnas faltantes en ordenes_mesas
        $ordenesCols = [
            'tipo_pedido'  => "ALTER TABLE ordenes_mesas ADD COLUMN tipo_pedido ENUM('mesa','domicilio','para_llevar') DEFAULT 'mesa'",
            'costo_empaque'=> "ALTER TABLE ordenes_mesas ADD COLUMN costo_empaque DECIMAL(10,2) DEFAULT 0",
            'dom_nombre'   => "ALTER TABLE ordenes_mesas ADD COLUMN dom_nombre VARCHAR(200) NULL",
            'dom_telefono' => "ALTER TABLE ordenes_mesas ADD COLUMN dom_telefono VARCHAR(30) NULL",
            'dom_direccion'=> "ALTER TABLE ordenes_mesas ADD COLUMN dom_direccion VARCHAR(300) NULL",
        ];

        foreach ($ordenesCols as $col => $sql) {
            if (!$this->columnExists('ordenes_mesas', $col)) {
                DB::statement($sql);
            }
        }

        // Columna en configuracion_empresas
        if (!$this->columnExists('configuracion_empresas', 'usa_domicilios')) {
            DB::statement("ALTER TABLE configuracion_empresas ADD COLUMN usa_domicilios TINYINT(1) DEFAULT 0");
        }
    }

    public function down(): void
    {
        $facturasCols = ['tipo_pedido','costo_empaque','dom_nombre','dom_telefono','dom_direccion','dom_ciudad','dom_nit','dom_email','dom_razon_social','cobro_domicilio'];
        foreach ($facturasCols as $col) {
            if ($this->columnExists('facturas', $col)) {
                DB::statement("ALTER TABLE facturas DROP COLUMN {$col}");
            }
        }

        $ordenesCols = ['tipo_pedido','costo_empaque','dom_nombre','dom_telefono','dom_direccion'];
        foreach ($ordenesCols as $col) {
            if ($this->columnExists('ordenes_mesas', $col)) {
                DB::statement("ALTER TABLE ordenes_mesas DROP COLUMN {$col}");
            }
        }

        if ($this->columnExists('configuracion_empresas', 'usa_domicilios')) {
            DB::statement("ALTER TABLE configuracion_empresas DROP COLUMN usa_domicilios");
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        return DB::getSchemaBuilder()->hasColumn($table, $column);
    }
};
