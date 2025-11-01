<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cajas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->decimal('monto_apertura', 14, 2)->default(0);
            $table->decimal('monto_cierre', 14, 2)->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->string('estado', 20)->default('cerrada'); // 'abierta'|'cerrada'
            $table->timestamps();

            $table->index(['user_id','empresa_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cajas');
    }
};