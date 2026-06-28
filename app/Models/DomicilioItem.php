<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DomicilioItem extends Model
{
    protected $fillable = [
        'domicilio_id', 'producto_id', 'nombre_producto',
        'cantidad', 'precio', 'total', 'nota',
    ];

    public function domicilio(): BelongsTo
    {
        return $this->belongsTo(Domicilio::class);
    }
}
