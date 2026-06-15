<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecetaItem extends Model
{
    use HasFactory;

    protected $table = 'receta_items';

    protected $fillable = [
        'empresa_id',
        'receta_id',
        'ingrediente_product_id',
        'cantidad',
        'unidad',
        'merma',
    ];

    protected $casts = [
        'cantidad' => 'decimal:3',
        'merma' => 'decimal:3',
    ];

    public function receta(): BelongsTo
    {
        return $this->belongsTo(Receta::class, 'receta_id');
    }

    public function ingrediente(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'ingrediente_product_id');
    }
}
