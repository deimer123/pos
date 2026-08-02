<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens;
    use HasFactory;
    use HasProfilePhoto;
    use Notifiable;
    use TwoFactorAuthenticatable;
    use HasRoles;

    protected $fillable = [
        'name',
        'email',
        'password',
        'tipo_usuario',
        'empresa_id',
        'activo',
        'botones_ocultos_pos',
        'recursos_ocultos_admin',
        'plan_meses',
        'plan_started_at',
        'plan_ends_at',
        'plan_id',
        'paquete_usuarios_id',
        'valor_plan_total',
        'es_empresa_emisora',
        'tipo_edicion',
        'max_vendedores',
        'max_cajeros',
        'max_digitadores',
        'telefono',
        'direccion',
        'session_token',
        'active_tab_id',
        'session_id',
        'last_login_at',
        'last_login_ip',
        'last_user_agent',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'activo' => 'boolean',
        'botones_ocultos_pos' => 'array',
        'recursos_ocultos_admin' => 'array',
        'plan_started_at' => 'date',
        'plan_ends_at' => 'date',
        'es_empresa_emisora' => 'boolean',
    ];

    public function puedeVerBotonPos(string $boton): bool
    {
        if ($this->hasRole('admin_empresa')) {
            return true;
        }

        return ! in_array($boton, $this->botones_ocultos_pos ?? []);
    }

    /**
     * Igual que puedeVerBotonPos() pero para los resources del panel de
     * admin (Productos, Recetas, Compras, etc.) -- admin_empresa siempre
     * los ve todos; para el resto (en la practica, solo digitador llega a
     * tener acceso al panel de admin) se revisa la lista de resources
     * marcados como ocultos para ese empleado en particular. Es una lista
     * de exclusion (igual que botones_ocultos_pos), no de permisos: si el
     * rol ya no tiene acceso al resource por su propia regla (ej. un
     * modulo que la empresa no tiene activado), esto no se lo devuelve.
     */
    public function puedeVerResource(string $resource): bool
    {
        if ($this->hasRole('admin_empresa')) {
            return true;
        }

        return ! in_array($resource, $this->recursos_ocultos_admin ?? []);
    }

    protected $appends = [
        'profile_photo_url',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($user) {
            if ($user->tipo_usuario === 'empleado' && !$user->empresa_id) {
                $user->empresa_id = auth()->user()?->getEmpresaActualId();
            }
        });
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->puedeVerAdmin();
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(User::class, 'empresa_id');
    }

    public function empleados(): HasMany
    {
        return $this->hasMany(User::class, 'empresa_id')->where('tipo_usuario', 'empleado');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Plan::class, 'plan_id');
    }

    public function paqueteUsuarios(): BelongsTo
    {
        return $this->belongsTo(\App\Models\PaqueteUsuarios::class, 'paquete_usuarios_id');
    }

    public function complementos(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(\App\Models\Complemento::class, 'empresa_complementos', 'empresa_id', 'complemento_id')
            ->withPivot('precio_aplicado')
            ->withTimestamps();
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'empresa_id');
    }

    public function familias(): HasMany
    {
        return $this->hasMany(Familia::class, 'empresa_id');
    }

    public function esEmpresa(): bool
    {
        return $this->tipo_usuario === 'empresa';
    }

    public function esEmpleado(): bool
    {
        return $this->tipo_usuario === 'empleado';
    }

    public function esAdminEmpresa(): bool
    {
        return $this->hasRole('admin_empresa');
    }

    public function esVendedor(): bool
    {
        return $this->hasRole('vendedor');
    }

    public function esDigitador(): bool
    {
        return $this->hasRole('digitador');
    }

    public function esSuperAdmin(): bool
    {
        return $this->hasRole('super_admin');
    }

    public function tieneAlgunRol(array $roles): bool
    {
        return $this->hasAnyRole($roles);
    }

    public function scopeEmpleados($query)
    {
        return $query->where('tipo_usuario', 'empleado');
    }

    public function scopeEmpresas($query)
    {
        return $query->where('tipo_usuario', 'empresa');
    }

    public function scopeDeEmpresa($query, $empresaId = null)
    {
        $empresaId = $empresaId ?? auth()->user()?->getEmpresaActualId();
        return $query->where('empresa_id', $empresaId);
    }

    public function scopeConRol($query, $rol)
    {
        return $query->role($rol);
    }

    public function puedeCrearUsuarios(): bool
    {
        return $this->hasRole(['super_admin', 'admin_empresa']);
    }

    public function puedeCrearAdminEmpresa(): bool
    {
        return $this->hasRole('super_admin');
    }

    public function puedeCrearEmpleados(): bool
    {
        return $this->hasRole('admin_empresa');
    }

    public function getEmpleadosQueManeja()
    {
        if ($this->hasRole('super_admin')) {
            return User::role('admin_empresa')->get();
        }

        if ($this->hasRole('admin_empresa')) {
            return User::where('empresa_id', $this->id)
                ->role(['vendedor', 'digitador'])
                ->get();
        }

        return collect();
    }

    public function configuracion()
    {
        return $this->hasOne(\App\Models\ConfiguracionEmpresa::class, 'empresa_id', 'id');
    }

    public function esCajero(): bool
    {
        return $this->hasRole('cajero');
    }

    public function getEmpresaActualId(): ?int
    {
        if ($this->tipo_usuario === 'empresa') {
            return $this->id;
        }

        return $this->empresa_id;
    }

    public function empresaPrincipal(): self
    {
        if ($this->tipo_usuario === 'empresa') {
            return $this;
        }

        return $this->empresa ?: $this;
    }

    public function planVencido(): bool
    {
        $empresa = $this->empresaPrincipal();

        return filled($empresa->plan_ends_at) && $empresa->plan_ends_at->lt(today());
    }

    public function puedeIngresarPorPlan(): bool
    {
        if ($this->hasRole('super_admin')) {
            return true;
        }

        $empresa = $this->empresaPrincipal();

        // Un cliente Local no tiene cuenta usable en el droplet: su unica
        // interfaz es la app de escritorio activada con codigo (ver
        // App\Services\LocalLicense). Esta fila solo existe como ancla
        // para emitirle licencias, no para que inicie sesion aca.
        if ($empresa->tipo_edicion === 'local') {
            return false;
        }

        return (bool) $empresa->activo && ! $empresa->planVencido();
    }

    // Hibrida = Online + Turion (puede emparejar/descargar Sistema POS
    // Offline, ver EditConfiguracionEmpresa). Se controla desde
    // EmpresaResource, solo super_admin.
    public function puedeUsarHibrida(): bool
    {
        return $this->empresaPrincipal()->tipo_edicion === 'hibrida';
    }

    public function esClienteLocal(): bool
    {
        return $this->empresaPrincipal()->tipo_edicion === 'local';
    }

    public function puedeFacturar(): bool
    {
        return $this->hasAnyRole([
            'admin_empresa',
            'cajero',
        ]);
    }

    public function necesitaConfiguracionInicial(): bool
    {
        if ($this->hasRole('super_admin')) {
            return false;
        }

        $empresaId = $this->getEmpresaActualId();

        if (! $empresaId) {
            return true;
        }

        $configuracion = \App\Models\ConfiguracionEmpresa::where('empresa_id', $empresaId)->first();

        if (! $configuracion) {
            return true;
        }

        return blank($configuracion->tipo_negocio)
            || blank($configuracion->nombre_empresa)
            || blank($configuracion->representante_legal)
            || blank($configuracion->nit);
    }

    public function puedeAbrirCaja(): bool
    {
        return $this->hasAnyRole([
            'admin_empresa',
            'cajero',
        ]);
    }

    public function puedeVerAdmin(): bool
    {
        return $this->hasAnyRole([
            'super_admin',
            'admin_empresa',
            'digitador',
            'mesero',
            'cocina',
            'vendedor',
            'cajero',
            'taller',
            'recepcion',
        ]);
    }
}
