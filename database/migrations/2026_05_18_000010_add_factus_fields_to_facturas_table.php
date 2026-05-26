<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->string('factus_reference_code')->nullable()->after('tipo_factura');
            $table->string('factus_bill_id')->nullable()->after('factus_reference_code');
            $table->string('factus_number')->nullable()->after('factus_bill_id');
            $table->string('factus_cufe')->nullable()->after('factus_number');
            $table->string('factus_status')->nullable()->after('factus_cufe');
            $table->json('factus_response')->nullable()->after('factus_status');
            $table->timestamp('factus_validated_at')->nullable()->after('factus_response');
        });
    }

    public function down(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->dropColumn([
                'factus_reference_code',
                'factus_bill_id',
                'factus_number',
                'factus_cufe',
                'factus_status',
                'factus_response',
                'factus_validated_at',
            ]);
        });
    }
};
