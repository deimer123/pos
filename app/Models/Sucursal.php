<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

// Sucursal de una empresa. Una empresa es un User con
// tipo_usuario='empresa', por eso empresa_id apunta a users.id. Solo las
// empresas con usa_sucursales=true (decidido por el super_admin) tienen
// sucursales.
class Sucursal extends Model
{
    // Laravel pluralizaria "Sucursal" como "sucursals" (reglas del ingles).
    protected $table = 'sucursales';

    protected $fillable = [
        'empresa_id',
        'nombre',
        'codigo',
        'direccion',
        'telefono',
        'es_principal',
        'activo',
    ];

    protected $casts = [
        'es_principal' => 'boolean',
        'activo' => 'boolean',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(User::class, 'empresa_id');
    }

    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'sucursal_user', 'sucursal_id', 'user_id')
            ->withTimestamps();
    }

    public function scopeDeEmpresa($query, int $empresaId)
    {
        return $query->where('empresa_id', $empresaId);
    }

    public function scopeActivas($query)
    {
        return $query->where('activo', true);
    }
}
