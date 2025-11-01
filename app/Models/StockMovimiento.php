<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovimiento extends Model
{
    protected $fillable = [
        'empresa_id','product_id','tipo','referencia_type','referencia_id',
        'cantidad','costo_unitario','costo_total','fecha',
    ];

    protected $casts = [
        'cantidad' => 'decimal:2',
        'costo_unitario' => 'decimal:2',
        'costo_total' => 'decimal:2',
        'fecha' => 'datetime',
    ];

    public function producto(): BelongsTo { return $this->belongsTo(Product::class, 'product_id'); }
    public function referencia(): MorphTo { return $this->morphTo(); }
}
