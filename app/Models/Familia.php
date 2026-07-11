<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Familia extends Model
{

    protected $primaryKey = 'id'; // ✅ esta es tu clave real

    // La tabla por defecto es "familias", y la PK por defecto es "id"
    // así que NO necesitamos override de $table ni de $primaryKey

    // Si quieres asignar en masa:
    protected $fillable = ['nombre', 'empresa_id', 'mostrar_en_catalogo'];

    protected $casts = [
        'mostrar_en_catalogo' => 'boolean',
    ];

    /**
     * Una Familia tiene muchas Subfamilias,
     * usando 'id' como key local y 'id_familia1' como FK en subfamilias.
     */
    public function subfamilias(): HasMany
    {
        return $this->hasMany(
            Subfamilia::class,
            'id_familia1',  // FK en la tabla subfamilias
            'id'            // PK local en familias
        );
    }
}
