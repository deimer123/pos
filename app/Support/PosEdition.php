<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Distingue las 3 ediciones del POS: online (droplet, MySQL), hibrida
 * ("Turion", SQLite + sincroniza con el droplet) y local (SQLite, nunca
 * sincroniza, activada por codigo -- ver App\Services\LocalLicense).
 *
 * Ambas ediciones offline corren sobre SQLite, asi que el driver de la
 * base de datos por si solo no alcanza para distinguirlas -- se combina
 * con config('pos.edition') (poblado via POS_EDITION, ver
 * src-tauri/src/lib.rs::preparar_entorno_local()). El chequeo de driver se
 * mantiene igual como defensa en profundidad: si POS_EDITION quedara mal
 * seteado contra una base MySQL, no deberia activarse comportamiento de
 * terminal offline.
 */
class PosEdition
{
    public static function esHibrida(): bool
    {
        return self::esSqliteStandalone() && config('pos.edition') === 'hibrida';
    }

    public static function esLocal(): bool
    {
        return self::esSqliteStandalone() && config('pos.edition') === 'local';
    }

    /**
     * Cualquier instalacion SQLite standalone (hibrida o local) -- util
     * para lo que hoy solo mira el driver pero deberia tratar ambas
     * ediciones offline igual (ej. UI que oculta cosas de red).
     */
    public static function esSqliteStandalone(): bool
    {
        return DB::getDriverName() === 'sqlite';
    }
}
