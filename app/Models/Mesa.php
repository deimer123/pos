<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Mesa extends Model
{
    use HasFactory;

    protected $table = 'mesas';

    protected $fillable = [
        'empresa_id',
        'codigo',
        'nombre',
        'zona',
        'capacidad',
        'estado',
        'activo',
    ];

    protected $casts = [
        'capacidad' => 'integer',
        'activo' => 'boolean',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(User::class, 'empresa_id');
    }

    public function ordenes(): HasMany
    {
        return $this->hasMany(OrdenMesa::class, 'mesa_id');
    }
}
