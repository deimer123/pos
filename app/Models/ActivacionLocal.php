<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Marca de que esta instalación SQLite standalone (edición Local) ya se
 * activó con un código válido -- ver EnsureLicenciaLocalActiva. Vive solo
 * del lado cliente; su contraparte de auditoría en el droplet es
 * LicenciaLocal.
 *
 * Puede tener VARIAS filas: la del propio equipo servidor (rol=servidor,
 * siempre la primera que existe) y una por cada terminal emparejado
 * contra él (rol=cliente, ver EmparejarTerminalLocalController) -- código
 * que necesite "la" activación de este equipo (no de un terminal
 * cualquiera) debe filtrar explícitamente por rol=servidor.
 */
class ActivacionLocal extends Model
{
    protected $table = 'activacion_local';

    protected $fillable = [
        'licencia_id',
        'empresa_id',
        'empresa_nombre',
        'machine_id',
        'rol',
        'codigo_raw',
        'activada_at',
    ];

    protected $casts = [
        'activada_at' => 'datetime',
    ];

    public static function activa(): bool
    {
        return static::query()->exists();
    }
}
