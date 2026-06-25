<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Receta extends Model
{
    use HasFactory;

    protected $table = 'recetas';

    protected $fillable = [
        'empresa_id',
        'product_id',
        'nombre',
        'rendimiento',
        'unidad_rendimiento',
        'activo',
    ];

    protected $casts = [
        'rendimiento' => 'decimal:3',
        'activo' => 'boolean',
    ];

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(RecetaItem::class, 'receta_id');
    }

    /**
     * Calcula cuántas porciones se pueden producir
     * según el stock actual de cada ingrediente.
     */
    public function getPorcionesDisponiblesAttribute(): float
    {
        $rendimiento = (float) $this->rendimiento ?: 1;
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->with('ingrediente')->get();

        if ($items->isEmpty()) {
            return 0;
        }

        $minPorciones = PHP_INT_MAX;

        foreach ($items as $item) {
            $ingrediente = $item->ingrediente;
            if (! $ingrediente) continue;

            $cantBase  = (float) $item->cantidad / $rendimiento;
            $merma     = (float) $item->merma;
            $cantReal  = $merma > 0 ? $cantBase * (1 + $merma / 100) : $cantBase;

            if ($cantReal <= 0) continue;

            $stock     = (float) $ingrediente->existencias;
            $porciones = floor($stock / $cantReal * 1000) / 1000;

            if ($porciones < $minPorciones) {
                $minPorciones = $porciones;
            }
        }

        return $minPorciones === PHP_INT_MAX ? 0 : max(0, $minPorciones);
    }
}
