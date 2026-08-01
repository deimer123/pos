<?php

namespace App\Services\LocalLicense;

use App\Exceptions\LocalLicense\LicenciaInvalidaException;

/**
 * Firma códigos de activación para la edición Local -- SOLO se usa en el
 * droplet (app/Filament/Pages/EmitirLicenciaLocal.php, comando
 * pos:licencia:emitir), nunca en una instalación cliente: exige
 * config('pos.license.private_key'), que no debe existir fuera del
 * droplet. Ver ValidadorLicencia para el lado que sí corre en el cliente.
 */
class FirmadorLicencia
{
    public function firmar(CodigoActivacion $codigo): string
    {
        $privateKeyB64 = config('pos.license.private_key');

        if (! $privateKeyB64) {
            throw new LicenciaInvalidaException(
                'No hay una llave privada de licencias configurada (POS_LOCAL_LICENSE_PRIVATE_KEY). '
                .'Generar un par con "php artisan pos:licencia:generar-llaves".'
            );
        }

        $privateKey = base64_decode($privateKeyB64, true);

        if ($privateKey === false || strlen($privateKey) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
            throw new LicenciaInvalidaException('La llave privada de licencias configurada no es válida.');
        }

        $payloadJson = json_encode(
            $codigo->toPayloadArray(),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );

        $firma = sodium_crypto_sign_detached($payloadJson, $privateKey);

        return sprintf(
            'POSLOC%d-%s.%s',
            $codigo->formatVersion,
            Base64Url::encode($payloadJson),
            Base64Url::encode($firma)
        );
    }
}
