<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('mostrar_en_catalogo')->default(true)->after('foto');
            $table->text('descripcion_catalogo')->nullable()->after('mostrar_en_catalogo');
        });

        Schema::table('configuracion_empresas', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('nombre_empresa');
        });

        // Backfill: generar slug para empresas ya configuradas, para que el
        // catálogo público funcione de inmediato sin que tengan que volver
        // a guardar su configuración.
        DB::table('configuracion_empresas')->whereNull('slug')->get()->each(function ($row) {
            DB::table('configuracion_empresas')->where('id', $row->id)->update([
                'slug' => Str::slug($row->nombre_empresa) . '-' . $row->empresa_id,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['mostrar_en_catalogo', 'descripcion_catalogo']);
        });

        Schema::table('configuracion_empresas', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }
};
