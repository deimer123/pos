<?php

namespace App\Support;

class VariantCodeGenerator
{
    /**
     * Sugiere un codigo de variante a partir del codigo del producto y su
     * talla/color (ej. producto "10004", talla "M", color "AZUL" ->
     * "10004MAZ"). Si ese codigo ya esta en uso (segun $codigosEnUso) le
     * agrega un sufijo numerico hasta encontrar uno libre -- asi dos
     * variantes con la misma talla pero distinto color (o viceversa) no
     * chocan.
     */
    public static function sugerir(string $idProducto, string $talla, string $color, array $codigosEnUso = []): string
    {
        $base = trim($idProducto) . self::limpiar($talla) . substr(self::limpiar($color), 0, 3);

        if ($base === '') {
            $base = trim($idProducto) . 'V';
        }

        $enUso = array_map(fn ($c) => strtoupper(trim((string) $c)), $codigosEnUso);

        $codigo = $base;
        $sufijo = 1;

        while (in_array(strtoupper($codigo), $enUso, true)) {
            $sufijo++;
            $codigo = $base . $sufijo;
        }

        return $codigo;
    }

    private static function limpiar(string $valor): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $valor) ?? '');
    }
}
