<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedSmallInteger('max_vendedores')->default(1)->after('plan_ends_at');
            $table->unsignedSmallInteger('max_cajeros')->default(1)->after('max_vendedores');
            $table->unsignedSmallInteger('max_digitadores')->default(0)->after('max_cajeros');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'max_vendedores',
                'max_cajeros',
                'max_digitadores',
            ]);
        });
    }
};
