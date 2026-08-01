<?php

namespace App\Services\LocalLicense;

use App\Exceptions\LocalLicense\LicenciaInvalidaException;
use App\Exceptions\LocalLicense\LicenciaOtraMaquinaException;

/**
 * Valida códigos de activación de la edición Local -- SOLO necesita la
 * llave pública (config('pos.license.public_key')), nunca llama al
 * droplet. Ver FirmadorLicencia para el lado que emite el código (solo
 * corre en el droplet).
 */
class ValidadorLicencia
{
    /**
     * Solo valida forma (prefijo, versión de formato, decodificación,
     * campos requeridos) -- NO verifica la firma ni la máquina. Útil para
     * mostrar de qué empresa es un código antes de confirmar la
     * activación. Para activar de verdad, usar verificar().
     */
    public function parsear(string $codigo): CodigoActivacion
    {
        return $this->descomponer($codigo)['codigo'];
    }

    /**
     * Validación completa: forma + firma criptográfica + que el código
     * sea para esta máquina. Es lo único que debe usarse antes de dar por
     * activada una instalación.
     */
    public function verificar(string $codigo, string $machineIdActual): CodigoActivacion
    {
        ['payloadJson' => $payloadJson, 'firma' => $firma, 'codigo' => $activacion] = $this->descomponer($codigo);

        $publicKeyB64 = config('pos.license.public_key');

        if (! $publicKeyB64) {
            throw new LicenciaInvalidaException('Esta instalación no tiene configurada la llave pública de licencias.');
        }

        $publicKey = base64_decode($publicKeyB64, true);

        if ($publicKey === false || strlen($publicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            throw new LicenciaInvalidaException('La llave pública de licencias configurada no es válida.');
        }

        if (! sodium_crypto_sign_verify_detached($firma, $payloadJson, $publicKey)) {
            throw new LicenciaInvalidaException('La firma del código de activación no es válida.');
        }

        if (! hash_equals($activacion->machineId, $machineIdActual)) {
            throw new LicenciaOtraMaquinaException('Este código de activación fue emitido para otra máquina.');
        }

        return $activacion;
    }

    /**
     * @return array{payloadJson: string, firma: string, codigo: CodigoActivacion}
     */
    private function descomponer(string $codigo): array
    {
        $codigo = trim($codigo);

        if (! preg_match('/^POSLOC(\d+)-([A-Za-z0-9_-]+)\.([A-Za-z0-9_-]+)$/', $codigo, $m)) {
            throw new LicenciaInvalidaException('El código de activación no tiene un formato reconocido.');
        }

        [, $version, $payloadB64, $firmaB64] = $m;

        $formatoEsperado = (int) config('pos.license.format_version', 1);

        if ((int) $version !== $formatoEsperado) {
            throw new LicenciaInvalidaException(
                "Este código usa una versión de formato ({$version}) que esta versión de la app no soporta."
            );
        }

        $payloadJson = Base64Url::decode($payloadB64);
        $firma = Base64Url::decode($firmaB64);

        if ($payloadJson === false || $firma === false) {
            throw new LicenciaInvalidaException('El código de activación está corrupto (no se pudo decodificar).');
        }

        $payload = json_decode($payloadJson, true);

        if (! is_array($payload)) {
            throw new LicenciaInvalidaException('El contenido del código de activación está corrupto.');
        }

        return [
            'payloadJson' => $payloadJson,
            'firma' => $firma,
            'codigo' => CodigoActivacion::fromPayloadArray($payload),
        ];
    }
}
