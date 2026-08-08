<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Calco de 2026_06_14_000008_create_producto_variantes_table.php: mismo
// mecanismo de stock/codigo propios por combinacion, aca por lote en vez
// de talla/color -- ver App\Models\ProductoVariante y el plan de
// "Drogueria: lotes con stock y vencimiento propios".
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('producto_lotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('codigo', 50)->nullable();
            $table->string('lote', 100);
            $table->date('fecha_vencimiento');
            $table->decimal('stock', 10, 3)->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['empresa_id', 'product_id', 'codigo']);
            $table->unique(['product_id', 'lote']);
            $table->index(['empresa_id', 'product_id', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('producto_lotes');
    }
};
