<?php

namespace App\Exceptions\LocalLicense;

/**
 * Código de activación corrupto, mal formado, con firma inválida, o con
 * una versión de formato que esta instalación no soporta. Mensaje siempre
 * en español, pensado para mostrarse directo en la pantalla de activación.
 */
class LicenciaInvalidaException extends \RuntimeException
{
}
