<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Domicilio extends Model
{
    protected $fillable = [
        'empresa_id', 'cliente_id', 'cliente_nombre', 'cliente_telefono',
        'direccion', 'barrio', 'ciudad', 'referencia',
        'observaciones', 'valor_domicilio', 'total_pedido',
        'estado', 'repartidor_id', 'venta_id', 'factura_id', 'creado_por',
        'asignado_at', 'en_camino_at', 'entregado_at',
    ];

    protected $casts = [
        'asignado_at'  => 'datetime',
        'en_camino_at' => 'datetime',
        'entregado_at' => 'datetime',
        'valor_domicilio' => 'decimal:2',
        'total_pedido'    => 'decimal:2',
    ];

    public static array $estados = [
        'pendiente'  => ['label' => 'Pendiente',   'color' => '#f59e0b', 'icon' => '🕐'],
        'asignado'   => ['label' => 'Asignado',    'color' => '#3b82f6', 'icon' => '🛵'],
        'en_camino'  => ['label' => 'En camino',   'color' => '#8b5cf6', 'icon' => '🚀'],
        'entregado'  => ['label' => 'Entregado',   'color' => '#22c55e', 'icon' => '✅'],
        'cancelado'  => ['label' => 'Cancelado',   'color' => '#ef4444', 'icon' => '❌'],
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(User::class, 'empresa_id');
    }

    public function repartidor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'repartidor_id');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DomicilioItem::class);
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    public function factura(): BelongsTo
    {
        return $this->belongsTo(Factura::class);
    }

    public function scopeEmpresa($query, $empresaId)
    {
        return $query->where('empresa_id', $empresaId);
    }

    public function getEstadoInfoAttribute(): array
    {
        return static::$estados[$this->estado] ?? ['label' => $this->estado, 'color' => '#6b7280', 'icon' => '?'];
    }
}
