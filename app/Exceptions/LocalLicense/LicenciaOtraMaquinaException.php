<?php

namespace App\Exceptions\LocalLicense;

/**
 * El código de activación es válido (firma correcta) pero fue emitido para
 * una máquina distinta a la actual -- caso separado de
 * LicenciaInvalidaException porque el mensaje/soporte a dar es distinto
 * (acá el código es legítimo, solo no aplica a este equipo).
 */
class LicenciaOtraMaquinaException extends LicenciaInvalidaException
{
}
