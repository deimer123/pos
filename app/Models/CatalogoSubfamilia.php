<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatalogoSubfamilia extends Model
{
    protected $table = 'catalogo_subfamilia';

    protected $fillable = [
        'catalogo_id',
        'subfamilia_id',
    ];

    public function catalogo(): BelongsTo
    {
        return $this->belongsTo(Catalogo::class, 'catalogo_id');
    }

    public function subfamilia(): BelongsTo
    {
        return $this->belongsTo(Subfamilia::class, 'subfamilia_id', 'id_familia2');
    }
}
