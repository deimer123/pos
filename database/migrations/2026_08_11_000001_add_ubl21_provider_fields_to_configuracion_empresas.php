<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Segundo proveedor de facturacion electronica, distinto a Factus (API DIAN
// UBL 2.1 propia, ver app/Services/Ubl21). "factura_electronica_proveedor"
// es el selector nuevo: por ahora solo lo usan las empresas que
// explicitamente elijan 'ubl21' -- las que ya tienen factus_enabled=true
// siguen exactamente igual (ver FacturarVentaService::proveedorFacturaElectronica()),
// no se les toca nada.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracion_empresas', function (Blueprint $table) {
            $table->string('factura_electronica_proveedor')->nullable()->after('factus_synced_at');

            // Autenticacion simple: un solo Bearer token (no OAuth2 como
            // Factus), devuelto al registrar la empresa en el proveedor.
            $table->text('ubl21_api_token')->nullable()->after('factura_electronica_proveedor');
            $table->string('ubl21_base_url')->nullable()->after('ubl21_api_token');
            $table->string('ubl21_environment')->default('habilitacion')->after('ubl21_base_url');

            // Solo de referencia (no se envian en cada factura, pero sirven
            // para mostrar en el admin y para re-registrar/depurar).
            $table->string('ubl21_nit')->nullable()->after('ubl21_environment');
            $table->string('ubl21_dv')->nullable()->after('ubl21_nit');

            // Resolucion de facturas (type_document_id=1)
            $table->string('ubl21_prefix')->nullable()->after('ubl21_dv');
            $table->string('ubl21_resolution_number')->nullable()->after('ubl21_prefix');
            $table->unsignedBigInteger('ubl21_numbering_from')->nullable()->after('ubl21_resolution_number');
            $table->unsignedBigInteger('ubl21_numbering_to')->nullable()->after('ubl21_numbering_from');

            // Resolucion de notas credito (type_document_id=4), si el
            // proveedor exige una distinta a la de facturas.
            $table->string('ubl21_credit_note_prefix')->nullable()->after('ubl21_numbering_to');
            $table->string('ubl21_credit_note_resolution_number')->nullable()->after('ubl21_credit_note_prefix');
        });
    }

    public function down(): void
    {
        Schema::table('configuracion_empresas', function (Blueprint $table) {
            $table->dropColumn([
                'factura_electronica_proveedor',
                'ubl21_api_token',
                'ubl21_base_url',
                'ubl21_environment',
                'ubl21_nit',
                'ubl21_dv',
                'ubl21_prefix',
                'ubl21_resolution_number',
                'ubl21_numbering_from',
                'ubl21_numbering_to',
                'ubl21_credit_note_prefix',
                'ubl21_credit_note_resolution_number',
            ]);
        });
    }
};
