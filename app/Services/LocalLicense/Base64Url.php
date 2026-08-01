<?php

namespace App\Services\LocalLicense;

/**
 * Variante de base64 segura para URLs/copiar-pegar (RFC 4648 §5, sin "+",
 * "/" ni "="), usada para las dos partes del código de activación
 * (payload y firma) para que el string final no se rompa al pegarlo en
 * email/WhatsApp.
 */
final class Base64Url
{
    public static function encode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function decode(string $data): string|false
    {
        $remainder = strlen($data) % 4;

        if ($remainder > 0) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode(strtr($data, '-_', '+/'), true);
    }
}
