<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LiquidacionMecanico extends Model
{
    protected $table = 'liquidaciones_mecanico';

    protected $fillable = [
        'empresa_id', 'mecanico_id', 'fecha_desde', 'fecha_hasta',
        'total_servicios', 'porcentaje_mecanico', 'monto_mecanico',
        'estado', 'fecha_pago', 'medio_pago', 'notas', 'user_id',
    ];

    protected $casts = [
        'fecha_desde'      => 'date',
        'fecha_hasta'      => 'date',
        'fecha_pago'       => 'date',
        'total_servicios'  => 'decimal:2',
        'porcentaje_mecanico' => 'decimal:2',
        'monto_mecanico'   => 'decimal:2',
    ];

    public function mecanico(): BelongsTo
    {
        return $this->belongsTo(Mecanico::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(LiquidacionMecanicoDetalle::class, 'liquidacion_id');
    }
}
