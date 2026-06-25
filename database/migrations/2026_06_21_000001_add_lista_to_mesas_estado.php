<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE mesas MODIFY COLUMN estado ENUM('libre', 'ocupada', 'reservada', 'cerrada', 'lista') NOT NULL DEFAULT 'libre'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE mesas MODIFY COLUMN estado ENUM('libre', 'ocupada', 'reservada', 'cerrada') NOT NULL DEFAULT 'libre'");
    }
};
