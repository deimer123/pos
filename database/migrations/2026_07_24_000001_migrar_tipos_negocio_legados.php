<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Empresas configuradas ANTES de consolidar "Restaurante" y "Bar" en un
// solo tipo "Bar y Restaurante" (ver ConfiguracionEmpresaResource)
// quedaron con un tipo_negocio que ya no existe en la lista de opciones
// -- eso hace que el select se vea vacio, y como el campo esta
// bloqueado despues de configurado, el usuario no lo puede corregir el
// mismo. Se migran esos valores viejos al nuevo.
return new class extends Migration
{
    public function up(): void
    {
        DB::table('configuracion_empresas')
            ->whereIn('tipo_negocio', ['restaurante', 'bar'])
            ->update(['tipo_negocio' => 'bar_restaurante']);
    }

    public function down(): void
    {
        // No reversible de forma segura (no se sabe cual era 'restaurante'
        // y cual era 'bar' antes de fusionarlos).
    }
};
