<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('session_id', 255)->nullable()->after('active_tab_id');
            $table->timestamp('last_login_at')->nullable()->after('session_id');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');
            $table->text('last_user_agent')->nullable()->after('last_login_ip');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['session_id', 'last_login_at', 'last_login_ip', 'last_user_agent']);
        });
    }
};
