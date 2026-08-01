<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Genera UNA VEZ el par de llaves Ed25519 para firmar/verificar codigos de
 * activacion de la edicion Local (ver app/Services/LocalLicense). Correr
 * localmente, nunca en CI -- no persiste nada a disco, solo imprime, para
 * que la llave privada no quede escrita en ningun archivo por accidente.
 */
class GenerarLlavesLicenciaLocal extends Command
{
    protected $signature = 'pos:licencia:generar-llaves';

    protected $description = 'Genera un par de llaves Ed25519 nuevo para firmar codigos de activacion de la edicion Local.';

    public function handle(): int
    {
        $keypair = sodium_crypto_sign_keypair();

        $privateKey = base64_encode(sodium_crypto_sign_secretkey($keypair));
        $publicKey = base64_encode(sodium_crypto_sign_publickey($keypair));

        $this->warn('Guardar la llave privada como secret -- nunca commitear, nunca en un .env de cliente (Turion/Local).');
        $this->newLine();

        $this->line('Droplet (.env o secret del servidor):');
        $this->line("POS_LOCAL_LICENSE_PRIVATE_KEY={$privateKey}");
        $this->newLine();

        $this->line('Cliente (es publica, se puede commitear como default en config/pos.php):');
        $this->line("POS_LOCAL_LICENSE_PUBLIC_KEY={$publicKey}");

        return self::SUCCESS;
    }
}
