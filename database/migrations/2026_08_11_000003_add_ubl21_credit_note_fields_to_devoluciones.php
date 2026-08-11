<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devoluciones', function (Blueprint $table) {
            $table->string('ubl21_credit_note_number')->nullable()->after('factus_credit_note_validated_at');
            $table->string('ubl21_credit_note_cufe')->nullable()->after('ubl21_credit_note_number');
            $table->text('ubl21_credit_note_qr')->nullable()->after('ubl21_credit_note_cufe');
            $table->string('ubl21_credit_note_status')->nullable()->after('ubl21_credit_note_qr');
            $table->json('ubl21_credit_note_response')->nullable()->after('ubl21_credit_note_status');
            $table->string('ubl21_credit_note_pdf_url')->nullable()->after('ubl21_credit_note_response');
            $table->string('ubl21_credit_note_xml_url')->nullable()->after('ubl21_credit_note_pdf_url');
            $table->timestamp('ubl21_credit_note_validated_at')->nullable()->after('ubl21_credit_note_xml_url');
        });
    }

    public function down(): void
    {
        Schema::table('devoluciones', function (Blueprint $table) {
            $table->dropColumn([
                'ubl21_credit_note_number',
                'ubl21_credit_note_cufe',
                'ubl21_credit_note_qr',
                'ubl21_credit_note_status',
                'ubl21_credit_note_response',
                'ubl21_credit_note_pdf_url',
                'ubl21_credit_note_xml_url',
                'ubl21_credit_note_validated_at',
            ]);
        });
    }
};
