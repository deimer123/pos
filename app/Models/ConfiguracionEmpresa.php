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
        'slug',
        'representante_legal',
        'nit',
        'telefono', 
        'direccion',
        'lema',
        'logo',
        'tipo_negocio',
        'usa_mesas',
        'usa_cocina',
        'usa_recetas',
        'usa_variantes',
        'usa_peso',
        'usa_servicios',
        'usa_domicilios',
        'usa_taller',
        'usa_hotel',
        'hotel_hora_inicio_dia',
        'permite_stock_negativo',
        'imprime_ticket',
        'mostrar_iva_separado',
        'descuento_maximo_permitido',
        'permite_ver_stock_no_admin',
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
        'usa_mesas' => 'boolean',
        'usa_cocina' => 'boolean',
        'usa_recetas' => 'boolean',
        'usa_variantes' => 'boolean',
        'usa_peso' => 'boolean',
        'usa_servicios' => 'boolean',
        'usa_domicilios' => 'boolean',
        'usa_taller' => 'boolean',
        'usa_hotel' => 'boolean',
        'permite_stock_negativo' => 'boolean',
        'imprime_ticket' => 'boolean',
        'mostrar_iva_separado' => 'boolean',
        'descuento_maximo_permitido' => 'decimal:2',
        'permite_ver_stock_no_admin' => 'boolean',
    ];

    public function empresa()
    {
        return $this->belongsTo(\App\Models\User::class, 'empresa_id');
    }

    public static function flagsModuloParaTipo(string $tipo): array
    {
        return match ($tipo) {
            'hotel' => [
                'usa_hotel' => true, 'usa_taller' => false, 'usa_mesas' => false,
                'usa_cocina' => false, 'usa_domicilios' => false, 'usa_recetas' => false,
            ],
            'taller' => [
                'usa_taller' => true, 'usa_servicios' => true, 'usa_hotel' => false,
                'usa_mesas' => false, 'usa_cocina' => false, 'usa_domicilios' => false, 'usa_recetas' => false,
            ],
            'restaurante' => [
                'usa_hotel' => false, 'usa_taller' => false,
            ],
            'panaderia' => [
                'usa_hotel' => false, 'usa_taller' => false, 'usa_cocina' => false,
                'usa_domicilios' => false, 'usa_peso' => false,
            ],
            'farmacia' => [
                'usa_hotel' => false, 'usa_taller' => false, 'usa_mesas' => false,
                'usa_cocina' => false, 'usa_domicilios' => false, 'usa_recetas' => false, 'usa_peso' => false,
            ],
            'bar' => [
                'usa_hotel' => false, 'usa_taller' => false, 'usa_cocina' => false,
                'usa_domicilios' => false, 'usa_peso' => false,
            ],
            'servicios' => [
                'usa_hotel' => false, 'usa_taller' => false, 'usa_mesas' => false,
                'usa_cocina' => false, 'usa_domicilios' => false, 'usa_recetas' => false, 'usa_peso' => false,
            ],
            default => [
                'usa_hotel' => false, 'usa_taller' => false, 'usa_mesas' => false,
                'usa_cocina' => false, 'usa_domicilios' => false, 'usa_recetas' => false,
            ],
        };
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
