<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Devolucion extends Model
{
     protected $table = 'devoluciones'; // <- clave del arreglo
    protected $fillable = [
        'empresa_id',
        'factura_id',
        'cliente_id',
        'user_id',      // <-- quién registró la devolución
        'fecha',
        'total',
        'observaciones',
        'factus_credit_note_reference_code',
        'factus_credit_note_id',
        'factus_credit_note_number',
        'factus_credit_note_cude',
        'factus_credit_note_status',
        'factus_credit_note_response',
        'factus_credit_note_validated_at',
    ];

    protected $casts = [
        'fecha' => 'datetime',
        'total' => 'decimal:2',
        'factus_credit_note_response' => 'array',
        'factus_credit_note_validated_at' => 'datetime',
    ];

    public function detalles()
    {
        return $this->hasMany(DevolucionDetalle::class);
    }

    public function factura()
    {
        return $this->belongsTo(Factura::class);
    }

    public function cliente()
    {
        return $this->belongsTo(Actor::class, 'cliente_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function getNumeroVisualAttribute(): string
    {
        return 'DEV-' . str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }
}
