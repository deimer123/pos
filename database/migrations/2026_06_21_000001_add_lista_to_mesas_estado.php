<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ALTER TABLE...MODIFY COLUMN es sintaxis exclusiva de MySQL, pero no se puede
// reemplazar por Schema::table()->change() porque Laravel no soporta cambiar
// columnas enum via doctrine/dbal en NINGUN motor (ChangeColumn::getDoctrineColumnType
// no sabe mapear el tipo "enum" a un tipo Doctrine, sin importar el driver).
// En SQLite esto es un no-op: create_mesas_table ya incluye 'lista' en el
// enum desde el arranque para instalaciones nuevas (base de datos local de
// Turion), asi que no hace falta (ni se puede) alterar el CHECK constraint
// despues.
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE mesas MODIFY COLUMN estado ENUM('libre', 'ocupada', 'reservada', 'cerrada', 'lista') NOT NULL DEFAULT 'libre'");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE mesas MODIFY COLUMN estado ENUM('libre', 'ocupada', 'reservada', 'cerrada') NOT NULL DEFAULT 'libre'");
    }
};
