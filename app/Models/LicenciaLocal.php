<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Historial de códigos de activación emitidos para la edición Local (ver
 * app/Filament/Pages/EmitirLicenciaLocal.php) -- solo vive en el droplet,
 * es un registro para soporte/auditoría, no algo que el cliente consulte
 * (la activación en el cliente se guarda aparte, en ActivacionLocal).
 */
class LicenciaLocal extends Model
{
    protected $table = 'licencias_locales';

    protected $fillable = [
        'licencia_id',
        'empresa_id',
        'machine_id',
        'machine_label',
        'creado_por',
        'codigo_emitido',
        'issued_at',
        'revocado_at',
        'notas',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
        'revocado_at' => 'datetime',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(User::class, 'empresa_id');
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function scopeActivas(Builder $query): Builder
    {
        return $query->whereNull('revocado_at');
    }
}
