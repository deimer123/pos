<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;

class TallerOrden extends Model
{
    protected $table = 'taller_ordenes';

    protected $fillable = [
        'empresa_id',
        'numero_orden',
        'cliente_id',
        'cliente_nombre',
        'cliente_telefono',
        'placa',
        'marca',
        'modelo',
        'color',
        'km_ingreso',
        'diagnostico',
        'observaciones',
        'estado',
        'factura_id',
        'mesa_id',
        'creado_por',
        'fecha_entrega_estimada',
        'entregado_at',
        'fotos',
        'nota_trabajo',
    ];

    protected $casts = [
        'km_ingreso'             => 'integer',
        'fecha_entrega_estimada' => 'datetime',
        'entregado_at'           => 'datetime',
        'fotos'                  => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $orden) {
            if (! $orden->numero_orden) {
                $max = static::where('empresa_id', $orden->empresa_id)->max('numero_orden') ?? 0;
                $orden->numero_orden = $max + 1;
            }
        });
    }

    public function repuestos(): HasMany
    {
        return $this->hasMany(TallerRepuesto::class, 'orden_id');
    }

    public function factura(): BelongsTo
    {
        return $this->belongsTo(Factura::class);
    }

    public function getTotalRepuestosAttribute(): float
    {
        return (float) $this->repuestos->sum('subtotal');
    }

    // Normaliza el teléfono guardado (texto libre, sin formato fijo) al formato
    // que exige un link wa.me: solo dígitos, con indicativo de país. Si ya
    // trae 57 (u otro indicativo de más de 10 dígitos) se deja tal cual.
    public function getTelefonoWhatsappAttribute(): ?string
    {
        $digitos = preg_replace('/\D/', '', (string) $this->cliente_telefono);

        if (! $digitos) {
            return null;
        }

        if (strlen($digitos) === 10) {
            $digitos = '57' . $digitos;
        }

        return $digitos;
    }

    // Link público y firmado al PDF (válido 30 días), pensado para compartir
    // por WhatsApp. Cuando el servidor solo tiene IP (sin dominio), WhatsApp
    // no reconoce el link como clickeable, así que se genera con un host
    // "159-89-81-81.nip.io": un servicio de DNS gratuito que resuelve ese
    // nombre de vuelta a la misma IP, sin mandar ningún dato a terceros.
    public function getLinkPdfPublicoAttribute(): string
    {
        $appUrl = config('app.url');
        $host   = parse_url($appUrl, PHP_URL_HOST) ?? 'localhost';
        $scheme = parse_url($appUrl, PHP_URL_SCHEME) ?: 'http';

        if (preg_match('/^\d{1,3}(\.\d{1,3}){3}$/', $host)) {
            $host = str_replace('.', '-', $host) . '.nip.io';
        }

        URL::forceRootUrl("{$scheme}://{$host}");
        $link = URL::temporarySignedRoute(
            'taller.orden.pdf.publico',
            now()->addDays(30),
            ['id' => $this->id]
        );
        URL::forceRootUrl($appUrl);

        return $link;
    }
}
