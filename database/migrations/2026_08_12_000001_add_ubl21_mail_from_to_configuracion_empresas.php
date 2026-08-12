<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Remitente propio por empresa para el correo de factura electronica del
// proveedor alterno (Ubl21FacturaElectronicaMail) -- sin esto, todas las
// empresas comparten el mismo MAIL_FROM_ADDRESS/MAIL_FROM_NAME global del
// .env. Si una empresa no configura esto, se sigue usando el global (ver
// Ubl21FacturaElectronicaMail::envelope()).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracion_empresas', function (Blueprint $table) {
            $table->string('ubl21_mail_from_address')->nullable()->after('ubl21_credit_note_resolution_number');
            $table->string('ubl21_mail_from_name')->nullable()->after('ubl21_mail_from_address');
        });
    }

    public function down(): void
    {
        Schema::table('configuracion_empresas', function (Blueprint $table) {
            $table->dropColumn(['ubl21_mail_from_address', 'ubl21_mail_from_name']);
        });
    }
};
