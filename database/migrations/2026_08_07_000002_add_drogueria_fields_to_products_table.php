<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('lote', 60)->nullable();
            $table->date('fecha_vencimiento')->nullable();
            $table->string('registro_invima', 60)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['lote', 'fecha_vencimiento', 'registro_invima']);
        });
    }
};
