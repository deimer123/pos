<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->foreignId('current_team_id')->nullable();
            $table->string('profile_photo_path', 2048)->nullable();
            
            // Campos adicionales para manejo de empresas y empleados
            $table->enum('tipo_usuario', ['empresa', 'empleado'])->default('empresa');
            $table->foreignId('empresa_id')->nullable()->constrained('users')->onDelete('cascade'); // Referencia al usuario empresa
            $table->boolean('activo')->default(true);
            $table->string('telefono')->nullable();
            $table->text('direccion')->nullable();
            
            $table->timestamps();
            
            // Índices para optimizar consultas
            $table->index(['tipo_usuario', 'empresa_id']);
            $table->index('empresa_id');
        });
    }

    public function down(): void {
        Schema::dropIfExists('users');
    }
};
