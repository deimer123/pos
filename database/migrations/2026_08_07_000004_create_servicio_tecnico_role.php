<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('roles')
            ->where('name', 'servicio_tecnico')
            ->where('guard_name', 'web')
            ->exists();

        if (! $exists) {
            DB::table('roles')->insert([
                'name'       => 'servicio_tecnico',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('roles')->where('name', 'servicio_tecnico')->where('guard_name', 'web')->delete();
    }
};
