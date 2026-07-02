<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Mecanico extends Model
{
    protected $table = 'mecanicos';

    protected $fillable = [
        'empresa_id', 'nombre', 'cedula', 'telefono', 'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Empresa::class);
    }

    public function servicios(): HasMany
    {
        return $this->hasMany(Product::class, 'mecanico_id');
    }

    public function liquidaciones(): HasMany
    {
        return $this->hasMany(LiquidacionMecanico::class, 'mecanico_id');
    }
}
