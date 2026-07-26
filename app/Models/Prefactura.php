<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Prefactura extends Model
{
    use HasFactory;

    protected $fillable = ['empresa_id', 'vendedor_id', 'cajero_id', 'cliente_id', 'observaciones', 'estado', 'servidor_id'];

    public function getNumeroVisualAttribute(): string
    {
        return 'PRE-' . str_pad((string) $this->id, 5, '0', STR_PAD_LEFT);
    }

    public function productos()
    {
        return $this->hasMany(PrefacturaProducto::class);
    }

    public function cliente()
    {
        return $this->belongsTo(Actor::class, 'cliente_id', 'id');
    }

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendedor_id');
    }

    public function cajero(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cajero_id');
    }

    public function configuracionEmpresa()
    {
        return $this->hasOne(\App\Models\ConfiguracionEmpresa::class, 'empresa_id', 'empresa_id');
    }
}
