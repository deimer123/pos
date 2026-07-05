<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelReservaConsumo extends Model
{
    protected $table = 'hotel_reserva_consumos';

    protected $fillable = [
        'reserva_id',
        'producto_id',
        'descripcion',
        'cantidad',
        'precio_unitario',
        'subtotal',
    ];

    protected $casts = [
        'cantidad'        => 'float',
        'precio_unitario'  => 'float',
        'subtotal'        => 'float',
    ];

    public function reserva(): BelongsTo
    {
        return $this->belongsTo(HotelReserva::class, 'reserva_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'producto_id');
    }
}
