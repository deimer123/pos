<?php

use App\Models\ConfiguracionEmpresa;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Spatie\Permission\Models\Role;

// Turion y Local inyectan FILESYSTEM_DISK=local (ver
// src-tauri/src/lib.rs y src-tauri-local/src/lib.rs::preparar_entorno_local()),
// pero el logo/fotos se guardan explicitamente en el disco 'public' (ver
// ConfiguracionEmpresaResource FileUpload::disk('public'),
// CarritoVenta::guardarFotoTaller() con ->store('taller', 'public')).
// Storage::url($ruta) SIN especificar disco usa el disco POR DEFECTO
// (filesystems.default) -- con 'local' de disco por defecto, esa llamada
// resuelve mal (el disco 'local' ni siquiera tiene 'url' configurada en
// config/filesystems.php) y el <img> queda roto. Debe ser siempre
// Storage::disk('public')->url(...), explicito.

test('el logo de la empresa usa el disco public explicito, no el disco por defecto', function () {
    Config::set('filesystems.default', 'local');

    Role::firstOrCreate(['name' => 'admin_empresa', 'guard_name' => 'web']);

    $empresa = User::factory()->create(['tipo_usuario' => 'empresa']);
    $empresa->assignRole('admin_empresa');

    ConfiguracionEmpresa::create([
        'empresa_id' => $empresa->id,
        'nombre_empresa' => 'Tienda de prueba',
        'representante_legal' => 'Rep Legal',
        'tipo_negocio' => 'tienda',
        'logo' => 'logos/prueba.jpg',
    ]);

    $this->actingAs($empresa);

    $html = view('filament.logo')->render();

    expect($html)->toContain('/storage/logos/prueba.jpg');
});
