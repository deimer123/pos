<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Marca de que esta instalación SQLite standalone (edición Local) ya se
 * activó con un código válido -- ver EnsureLicenciaLocalActiva. A lo sumo
 * una fila. Vive solo del lado cliente; su contraparte de auditoría en el
 * droplet es LicenciaLocal.
 */
class ActivacionLocal extends Model
{
    protected $table = 'activacion_local';

    protected $fillable = [
        'licencia_id',
        'empresa_id',
        'empresa_nombre',
        'machine_id',
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
