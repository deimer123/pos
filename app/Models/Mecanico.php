<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Mecanico extends Model
{
    protected $table = 'mecanicos';

    // "rol" generaliza este registro (antes solo mecanicos de taller) a
    // cualquier colaborador que gane comision y se le liquide: mecanico
    // (taller), vendedor (tienda/ropa/carniceria) o mesero (bar y
    // restaurante). Ver migracion 2026_07_22_000001.
    public const ROL_MECANICO = 'mecanico';
    public const ROL_VENDEDOR = 'vendedor';
    public const ROL_MESERO = 'mesero';

    protected $fillable = [
        'empresa_id', 'nombre', 'cedula', 'telefono', 'activo',
        'rol', 'user_id', 'porcentaje_comision',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'porcentaje_comision' => 'decimal:2',
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

    public function prestamos(): HasMany
    {
        return $this->hasMany(MecanicoPrestamo::class, 'mecanico_id');
    }

    // Solo vendedor/mesero tienen login propio -- el mecanico de taller
    // es un registro puramente administrativo, sin usuario asociado.
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopePorRol($query, string $rol)
    {
        return $query->where('rol', $rol);
    }
}
