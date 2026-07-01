<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('taller_ordenes', function (Blueprint $table) {
            $table->text('nota_trabajo')->nullable()->after('fotos');
        });
    }

    public function down(): void
    {
        Schema::table('taller_ordenes', function (Blueprint $table) {
            $table->dropColumn('nota_trabajo');
        });
    }
};
