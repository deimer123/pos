<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subfamilia extends Model
{
    // Definimos la tabla y la PK personalizada
    protected $table = 'subfamilias';
    protected $primaryKey = 'id_familia2';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = ['nombre', 'id_familia1'];

    /**
     * Cada Subfamilia pertenece a una Familia
     */
    public function familia(): BelongsTo
    {
        return $this->belongsTo(
            Familia::class,
            'id_familia1',  // FK en subfamilias
            'id'            // PK en familias
        );
    }
}
