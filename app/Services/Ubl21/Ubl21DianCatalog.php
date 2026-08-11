<?php

namespace App\Services\Ubl21;

/**
 * Catalogos estandar de la DIAN que usa este proveedor (UBL 2.1), para
 * traducir los IDs internos del sistema a los codigos que la DIAN espera.
 * Compartido entre Ubl21InvoicePayloadBuilder y Ubl21CreditNotePayloadBuilder.
 */
class Ubl21DianCatalog
{
    /**
     * tipos_documento.id (1..11, ver TiposDocumentoSeeder) -> codigo DIAN de
     * tipo de documento de identificacion (catalogo oficial 1.3.6...).
     */
    public static function tipoDocumentoIdentificacion(int $tipoDocumentoIdInterno): string
    {
        return match ($tipoDocumentoIdInterno) {
            1 => '11', // Registro civil
            2 => '12', // Tarjeta de identidad
            3 => '13', // Cedula de ciudadania
            4 => '21', // Tarjeta de extranjeria
            5 => '22', // Cedula de extranjeria
            6 => '31', // NIT
            7 => '41', // Pasaporte
            8 => '42', // Documento de identificacion extranjero
            9 => '47', // PEP
            10 => '50', // NIT de otro pais
            11 => '91', // NUIP
            default => '13',
        };
    }

    // Mismo criterio que ya usa CarritoVenta/Factus: solo se distingue
    // efectivo/transferencia con codigo propio, cualquier otro medio se
    // manda como "otro" (10 = efectivo, catalogo DIAN de medios de pago).
    public static function medioPago(string $medioPago): string
    {
        return match (strtolower($medioPago)) {
            'transferencia' => '42',
            default => '10',
        };
    }
}
