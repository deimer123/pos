<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracion_empresas', function (Blueprint $table) {
            $table->unsignedBigInteger('factus_credit_note_numbering_range_id')->nullable()->after('factus_numbering_range_id');
        });

        Schema::table('devoluciones', function (Blueprint $table) {
            $table->string('factus_credit_note_reference_code')->nullable()->after('observaciones');
            $table->string('factus_credit_note_id')->nullable()->after('factus_credit_note_reference_code');
            $table->string('factus_credit_note_number')->nullable()->after('factus_credit_note_id');
            $table->string('factus_credit_note_cude')->nullable()->after('factus_credit_note_number');
            $table->string('factus_credit_note_status')->nullable()->after('factus_credit_note_cude');
            $table->json('factus_credit_note_response')->nullable()->after('factus_credit_note_status');
            $table->timestamp('factus_credit_note_validated_at')->nullable()->after('factus_credit_note_response');
        });
    }

    public function down(): void
    {
        Schema::table('devoluciones', function (Blueprint $table) {
            $table->dropColumn([
                'factus_credit_note_reference_code',
                'factus_credit_note_id',
                'factus_credit_note_number',
                'factus_credit_note_cude',
                'factus_credit_note_status',
                'factus_credit_note_response',
                'factus_credit_note_validated_at',
            ]);
        });

        Schema::table('configuracion_empresas', function (Blueprint $table) {
            $table->dropColumn('factus_credit_note_numbering_range_id');
        });
    }
};
