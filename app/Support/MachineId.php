<?php

namespace App\Support;

/**
 * Fingerprint de esta maquina (solo tiene un valor real en la edicion
 * Local) -- lo calcula src-tauri/src/lib.rs::machine_id() y lo inyecta
 * como env var POS_MACHINE_ID, expuesta aca via config/pos.php para no
 * llamar env() fuera de un archivo de config.
 */
class MachineId
{
    public static function actual(): string
    {
        return (string) config('pos.machine_id', '');
    }
}
