<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServicioTecnicoItem extends Model
{
    protected $table = 'servicio_tecnico_items';

    protected $fillable = [
        'orden_id',
        'producto_id',
        'descripcion',
        'cantidad',
        'precio_unitario',
        'subtotal',
        'tipo',
        'costo_proveedor',
    ];

    protected $casts = [
        'cantidad'        => 'float',
        'precio_unitario' => 'float',
        'subtotal'        => 'float',
        'costo_proveedor' => 'float',
    ];

    public function orden(): BelongsTo
    {
        return $this->belongsTo(ServicioTecnicoOrden::class, 'orden_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'producto_id');
    }
}
