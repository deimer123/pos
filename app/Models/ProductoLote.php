<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Calco de App\Models\ProductoVariante -- mismo mecanismo de stock y
 * codigo de barras propios por combinacion, aca por lote (con su fecha de
 * vencimiento) en vez de talla/color. Se da de alta desde Compras (ver
 * CompraResource::guardarLote()), no desde una pantalla aparte.
 */
class ProductoLote extends Model
{
    use HasFactory;

    protected $table = 'producto_lotes';

    protected $fillable = [
        'empresa_id',
        'product_id',
        'codigo',
        'lote',
        'fecha_vencimiento',
        'stock',
        'activo',
    ];

    protected $casts = [
        'fecha_vencimiento' => 'date',
        'stock' => 'decimal:3',
        'activo' => 'boolean',
    ];

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * Mismo mecanismo que ProductoVariante::setCodigoAttribute() /
     * scopeConCodigo(): "codigo" admite varios codigos separados por coma
     * (el propio + alternos de un lector viejo), normalizados al guardar.
     */
    public function setCodigoAttribute($value): void
    {
        $codigo = trim((string) $value);

        if ($codigo === '') {
            $this->attributes['codigo'] = null;
            return;
        }

        $codigos = collect(explode(',', $codigo))
            ->map(fn ($c) => trim($c))
            ->filter()
            ->unique()
            ->values();

        $this->attributes['codigo'] = $codigos->isEmpty() ? null : $codigos->implode(',');
    }

    public function scopeConCodigo($query, string $codigo)
    {
        $codigo = trim($codigo);

        return $query->where(function ($q) use ($codigo) {
            $q->where('codigo', $codigo)
                ->orWhere('codigo', 'like', $codigo . ',%')
                ->orWhere('codigo', 'like', '%,' . $codigo)
                ->orWhere('codigo', 'like', '%,' . $codigo . ',%');
        });
    }
}
