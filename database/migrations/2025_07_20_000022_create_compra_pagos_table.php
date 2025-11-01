<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('compra_pagos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('compra_id')->constrained('compras')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $t->enum('medio', ['efectivo','transferencia','otro'])->default('efectivo');
            $t->decimal('monto', 15, 2);
            $t->dateTime('fecha');
            $t->string('nota')->nullable();
            $t->string('transferencia_obs')->nullable();

            $t->timestamps();
            $t->index(['compra_id','medio','fecha']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('compra_pagos');
    }
};
