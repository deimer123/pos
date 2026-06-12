<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('cuenta_contable_id')
                ->nullable()
                ->after('empresa_id')
                ->index();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['cuenta_contable_id']);
            $table->dropColumn('cuenta_contable_id');
        });
    }
};
