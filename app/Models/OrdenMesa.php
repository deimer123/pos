<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrdenMesa extends Model
{
    use HasFactory;

    protected $table = 'ordenes_mesas';

    protected $fillable = [
        'empresa_id',
        'mesa_id',
        'usuario_id',
        'cliente_id',
        'consecutivo',
        'estado',
        'subtotal',
        'impuestos',
        'descuento',
        'total',
        'observaciones',
        'tipo_pedido',
        'costo_empaque',
        'dom_nombre',
        'dom_telefono',
        'dom_direccion',
        'dom_observaciones',
        'dom_costo_domicilio',
        'dom_costo_desechables',
        'numero_cocina_dia',
        'entregada',
        'abierta_en',
        'cerrada_en',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'impuestos' => 'decimal:2',
        'descuento' => 'decimal:2',
        'total' => 'decimal:2',
        'abierta_en' => 'datetime',
        'cerrada_en' => 'datetime',
    ];

    public function mesa(): BelongsTo
    {
        return $this->belongsTo(Mesa::class, 'mesa_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'usuario_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrdenMesaItem::class, 'orden_mesa_id');
    }
}
