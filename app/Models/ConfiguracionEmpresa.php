<?php

// filepath: c:\laragon\www\posapp\app\Models\ConfiguracionEmpresa.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Storage;

class ConfiguracionEmpresa extends Model
{
    use HasFactory;

    protected $table = 'configuracion_empresas';

    protected $fillable = [
        'empresa_id',           // ← Asegúrate de que este campo esté aquí
        'nombre_empresa',
        'representante_legal',
        'nit',
        'telefono', 
        'direccion',
        'lema',
        'logo',
        'prefijo',
        'rango_desde',
        'rango_hasta',
        'rango_actual',
        'numero_resolucion',
        'fecha_inicio',
        'fecha_fin',
        'llave',
        'expirado',
        'activo',
        'factus_enabled',
        'factus_environment',
        'factus_base_url',
        'factus_username',
        'factus_password',
        'factus_client_id',
        'factus_client_secret',
        'factus_numbering_range_id',
        'factus_credit_note_numbering_range_id',
        'factus_send_email',
        'factus_synced_at',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'expirado' => 'boolean',
        'activo' => 'boolean',
        'rango_desde' => 'integer',
        'rango_hasta' => 'integer',
        'rango_actual' => 'integer',
        'factus_enabled' => 'boolean',
        'factus_send_email' => 'boolean',
        'factus_numbering_range_id' => 'integer',
        'factus_credit_note_numbering_range_id' => 'integer',
        'factus_password' => 'encrypted',
        'factus_client_secret' => 'encrypted',
        'factus_synced_at' => 'datetime',
    ];

    public function empresa()
    {
        return $this->belongsTo(\App\Models\User::class, 'empresa_id');
    }

    public function getLogoUrlAttribute()
{
    return $this->logo ? Storage::disk('public')->url($this->logo) : null;
}

public function configuracionEmpresa()
{
    return $this->hasOne(\App\Models\ConfiguracionEmpresa::class, 'empresa_id', 'empresa_id');
}
}
