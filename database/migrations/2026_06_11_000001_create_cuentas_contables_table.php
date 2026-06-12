<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cuentas_contables')) {
            return;
        }

        Schema::create('cuentas_contables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->nullable()->index();
            $table->string('codigo', 30)->index();
            $table->string('nombre');
            $table->string('tipo', 50)->nullable();
            $table->string('categoria', 50)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['empresa_id', 'codigo']);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('cuentas_contables')) {
            Schema::drop('cuentas_contables');
        }
    }
};
