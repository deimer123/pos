<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('factura_pagos', function (Blueprint $table) {
            if (! Schema::hasColumn('factura_pagos', 'vendedor_id')) {
                $table->foreignId('vendedor_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('factura_pagos', 'cajero_id')) {
                $table->foreignId('cajero_id')->nullable()->after('vendedor_id')->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('factura_pagos', function (Blueprint $table) {
            if (Schema::hasColumn('factura_pagos', 'cajero_id')) {
                $table->dropConstrainedForeignId('cajero_id');
            }

            if (Schema::hasColumn('factura_pagos', 'vendedor_id')) {
                $table->dropConstrainedForeignId('vendedor_id');
            }
        });
    }
};
