<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Causa raiz real del bug de "tipo_negocio se guarda en Tienda/Almacen sin
// que nadie lo elija": EmpresaResource::saveFactusConfig() crea la fila de
// configuracion_empresas al crear la empresa (para poder guardar NIT/Factus
// desde el mismo formulario), pero nunca incluye 'tipo_negocio' en el
// insert. Como la columna tenia ->default('tienda') a nivel de base de
// datos, MySQL lo rellenaba solo en cuanto se omitia del INSERT -- mucho
// antes de que el admin_empresa llegara a ver el wizard de configuracion.
//
// Al quitar el default y dejarla nullable, esa fila queda con
// tipo_negocio=NULL hasta que el admin_empresa lo elija de verdad -- el
// campo del formulario ya trata null como "sin definir" (filled() = false),
// asi que sigue editable en vez de aparecer bloqueado.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracion_empresas', function (Blueprint $table) {
            $table->string('tipo_negocio', 30)->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('configuracion_empresas', function (Blueprint $table) {
            $table->string('tipo_negocio', 30)->default('tienda')->change();
        });
    }
};
