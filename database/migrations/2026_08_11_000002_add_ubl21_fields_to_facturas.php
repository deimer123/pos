<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->string('ubl21_document_number')->nullable()->after('factus_validated_at');
            $table->string('ubl21_cufe')->nullable()->after('ubl21_document_number');
            $table->text('ubl21_qr')->nullable()->after('ubl21_cufe');
            $table->string('ubl21_status')->nullable()->after('ubl21_qr');
            $table->json('ubl21_response')->nullable()->after('ubl21_status');
            $table->string('ubl21_pdf_url')->nullable()->after('ubl21_response');
            $table->string('ubl21_xml_url')->nullable()->after('ubl21_pdf_url');
            $table->timestamp('ubl21_validated_at')->nullable()->after('ubl21_xml_url');
        });
    }

    public function down(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->dropColumn([
                'ubl21_document_number',
                'ubl21_cufe',
                'ubl21_qr',
                'ubl21_status',
                'ubl21_response',
                'ubl21_pdf_url',
                'ubl21_xml_url',
                'ubl21_validated_at',
            ]);
        });
    }
};
