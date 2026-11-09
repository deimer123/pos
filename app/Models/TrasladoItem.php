<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrasladoItem extends Model
{
    protected $table = 'traslado_items';

    protected $fillable = [
        'traslado_id', 'producto_origen_id', 'producto_destino_id',
        'nombre', 'cantidad', 'costo_unitario', 'precio_venta',
    ];

    protected $casts = [
        'cantidad' => 'decimal:2',
        'costo_unitario' => 'decimal:2',
        'precio_venta' => 'decimal:2',
    ];

    public function traslado(): BelongsTo
    {
        return $this->belongsTo(Traslado::class);
    }
}
