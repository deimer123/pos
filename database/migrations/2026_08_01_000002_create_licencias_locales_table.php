<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Historial de codigos de activacion emitidos para la edicion Local (ver
// App\Filament\Pages\EmitirLicenciaLocal, App\Services\LocalLicense).
// Vive solo en el droplet, para soporte/auditoria -- el cliente nunca
// vuelve a llamar aca despues de activarse, asi que esto NO es una fuente
// de verdad para el cliente, solo un registro de lo que se emitio.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('licencias_locales', function (Blueprint $table) {
            $table->id();
            $table->uuid('licencia_id')->unique();
            $table->foreignId('empresa_id')->constrained('users')->cascadeOnDelete();
            $table->string('machine_id');
            $table->string('machine_label')->nullable();
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->text('codigo_emitido');
            $table->timestamp('issued_at');
            $table->timestamp('revocado_at')->nullable();
            $table->text('notas')->nullable();
            $table->timestamps();

            $table->unique(['empresa_id', 'machine_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('licencias_locales');
    }
};
