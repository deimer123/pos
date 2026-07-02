<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiquidacionMecanicoDetalle extends Model
{
    protected $table = 'liquidacion_mecanico_detalles';

    protected $fillable = [
        'liquidacion_id', 'factura_detalle_id', 'subtotal_servicio', 'monto_mecanico',
    ];

    protected $casts = [
        'subtotal_servicio' => 'decimal:2',
        'monto_mecanico'    => 'decimal:2',
    ];

    public function liquidacion(): BelongsTo
    {
        return $this->belongsTo(LiquidacionMecanico::class);
    }

    public function facturaDetalle(): BelongsTo
    {
        return $this->belongsTo(FacturaDetalle::class, 'factura_detalle_id');
    }
}
