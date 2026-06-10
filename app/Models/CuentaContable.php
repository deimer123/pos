<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CuentaContable extends Model
{
    use HasFactory;

    protected $fillable = [
        'empresa_id',
        'codigo',
        'nombre',
        'tipo',
        'categoria',
        'descripcion',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $cuenta): void {
            if (auth()->check() && empty($cuenta->empresa_id)) {
                $cuenta->empresa_id = auth()->user()->getEmpresaActualId();
            }
        });
    }

    public function productos(): HasMany
    {
        return $this->hasMany(Product::class, 'cuenta_contable_id');
    }
}
