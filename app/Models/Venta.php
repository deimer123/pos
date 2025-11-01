<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Venta extends Model
{
    use HasFactory;


    public function cliente() {
    return $this->belongsTo(Actor::class, 'cliente_clip_id', 'id_clip_pro');
}

public function detalles()
{
    return $this->hasMany(DetalleVenta::class);
}

}
