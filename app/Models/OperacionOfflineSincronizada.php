<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Registro de idempotencia: cada venta offline que ya se sincronizo con
 * exito, para que reintentar el mismo uuid (por una conexion inestable a
 * medio sincronizar) no la vuelva a facturar.
 */
class OperacionOfflineSincronizada extends Model
{
    protected $table = 'operaciones_offline_sincronizadas';

    protected $fillable = [
        'uuid',
        'tipo',
        'empresa_id',
        'resultado_id',
    ];
}
