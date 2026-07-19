<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Codigos de emparejamiento de una terminal de Turion: se generan desde el
// panel de administracion (Filament), duran poco y son de un solo uso --
// Turion los cambia por un token de Sanctum permanente al emparejar
// (ver PairingController::bootstrap()). Vive solo en el droplet.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pairing_codes', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 12)->unique();
            $table->foreignId('empresa_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expira_at');
            $table->timestamp('usado_at')->nullable();
            $table->string('terminal_nombre')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pairing_codes');
    }
};
