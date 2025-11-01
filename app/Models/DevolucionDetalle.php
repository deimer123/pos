<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DevolucionDetalle extends Model
{
      protected $table = 'devolucion_detalles';
    protected $fillable = [
        'devolucion_id',
        'factura_detalle_id',
        'producto_id',
        'descripcion_larga',
        'cantidad',
        'precio',
        'subtotal',
    ];

    protected $casts = [
        'cantidad' => 'decimal:2',
        'precio'   => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function devolucion()
    {
        return $this->belongsTo(Devolucion::class);
    }

    public function facturaDetalle()
    {
        return $this->belongsTo(FacturaDetalle::class);
    }
}
