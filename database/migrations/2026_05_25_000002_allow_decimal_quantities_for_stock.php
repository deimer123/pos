<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE products MODIFY existencias DECIMAL(14,2) NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE kardex MODIFY entrada DECIMAL(14,2) NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE kardex MODIFY salida DECIMAL(14,2) NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE kardex MODIFY stock_anterior DECIMAL(14,2) NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE kardex MODIFY stock_actual DECIMAL(14,2) NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE prefactura_productos MODIFY cantidad DECIMAL(14,2) NOT NULL DEFAULT 1');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE prefactura_productos MODIFY cantidad INT NOT NULL');
        DB::statement('ALTER TABLE kardex MODIFY stock_actual INT NOT NULL');
        DB::statement('ALTER TABLE kardex MODIFY stock_anterior INT NOT NULL');
        DB::statement('ALTER TABLE kardex MODIFY salida INT NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE kardex MODIFY entrada INT NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE products MODIFY existencias INT NOT NULL DEFAULT 0');
    }
};