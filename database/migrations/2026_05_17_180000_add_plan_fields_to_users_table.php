<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedSmallInteger('plan_meses')->nullable()->after('activo');
            $table->date('plan_started_at')->nullable()->after('plan_meses');
            $table->date('plan_ends_at')->nullable()->after('plan_started_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'plan_meses',
                'plan_started_at',
                'plan_ends_at',
            ]);
        });
    }
};
