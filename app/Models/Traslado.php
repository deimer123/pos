<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Traslado extends Model
{
    public const ENVIADO = 'enviado';
    public const RECIBIDO = 'recibido';
    public const RECHAZADO = 'rechazado';
    public const CANCELADO = 'cancelado';

    protected $table = 'traslados';

    protected $fillable = [
        'origen_id', 'destino_id', 'estado', 'observacion',
        'enviado_por', 'resuelto_por', 'enviado_at', 'resuelto_at',
    ];

    protected $casts = [
        'enviado_at' => 'datetime',
        'resuelto_at' => 'datetime',
    ];

    public function origen(): BelongsTo
    {
        return $this->belongsTo(User::class, 'origen_id');
    }

    public function destino(): BelongsTo
    {
        return $this->belongsTo(User::class, 'destino_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TrasladoItem::class);
    }

    public function estaEnviado(): bool
    {
        return $this->estado === self::ENVIADO;
    }
}
