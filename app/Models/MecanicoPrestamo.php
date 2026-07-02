<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MecanicoPrestamo extends Model
{
    protected $table = 'mecanico_prestamos';

    protected $fillable = [
        'empresa_id', 'mecanico_id', 'monto', 'fecha', 'nota',
        'estado', 'liquidacion_id', 'user_id',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'fecha' => 'date',
    ];

    public function mecanico(): BelongsTo
    {
        return $this->belongsTo(Mecanico::class);
    }

    public function liquidacion(): BelongsTo
    {
        return $this->belongsTo(LiquidacionMecanico::class, 'liquidacion_id');
    }
}
