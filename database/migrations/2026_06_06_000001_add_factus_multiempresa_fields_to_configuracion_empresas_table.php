<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracion_empresas', function (Blueprint $table) {
            $table->boolean('factus_enabled')->default(false)->after('activo');
            $table->string('factus_environment')->default('sandbox')->after('factus_enabled');
            $table->string('factus_base_url')->nullable()->after('factus_environment');
            $table->string('factus_username')->nullable()->after('factus_base_url');
            $table->text('factus_password')->nullable()->after('factus_username');
            $table->string('factus_client_id')->nullable()->after('factus_password');
            $table->text('factus_client_secret')->nullable()->after('factus_client_id');
            $table->unsignedBigInteger('factus_numbering_range_id')->nullable()->after('factus_client_secret');
            $table->boolean('factus_send_email')->default(false)->after('factus_numbering_range_id');
            $table->timestamp('factus_synced_at')->nullable()->after('factus_send_email');
        });
    }

    public function down(): void
    {
        Schema::table('configuracion_empresas', function (Blueprint $table) {
            $table->dropColumn([
                'factus_enabled',
                'factus_environment',
                'factus_base_url',
                'factus_username',
                'factus_password',
                'factus_client_id',
                'factus_client_secret',
                'factus_numbering_range_id',
                'factus_send_email',
                'factus_synced_at',
            ]);
        });
    }
};
