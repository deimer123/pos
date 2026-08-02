<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Filament\Pages\Auth\EditProfile;
use Filament\Navigation\UserMenuItem;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
{
    return $panel
        ->default()
        ->id('admin')
        ->path('admin')
        ->login() // ✅ Solo esto, sin auth()
        ->brandName('Sistema POS')
        ->favicon(asset('favicon.ico'))
        ->sidebarWidth('18rem')
        ->brandLogo(fn () => view('filament.logo'))
        ->colors([
            'primary' => Color::Indigo,
        ])
        ->breadcrumbs(false)
        ->discoverClusters(in: app_path('Filament/Clusters'), for: 'App\\Filament\\Clusters')
        ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
        ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
        ->pages([
            \App\Filament\Pages\Dashboard::class,
        ])
        ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
        ->widgets([
            \App\Filament\Widgets\SuperAdminEmpresasOverview::class,
            \App\Filament\Widgets\StatsOverview::class,           
            \App\Filament\Widgets\ProductosMasVendidos::class,
            \App\Filament\Widgets\VentasPorVendedor::class,
             \App\Filament\Widgets\VentasUltimos7Dias::class,
            
        ])
        ->middleware([
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            AuthenticateSession::class,
            ShareErrorsFromSession::class,
            VerifyCsrfToken::class,
            SubstituteBindings::class,
            DisableBladeIconComponents::class,
            DispatchServingFilamentEvent::class,
        ])
        ->authMiddleware([
            Authenticate::class,
            \App\Http\Middleware\BloquearAdminEnTurion::class, // ✔️ Una terminal de Turion (SQLite) es solo para el POS, no carga nada de /admin
            \App\Http\Middleware\BloquearClienteLocalDeAdmin::class, // ✔️ Un cliente de la edición Local no tiene cuenta usable en el droplet
            \App\Http\Middleware\EnforceSessionUniqueness::class, // ✔️ Cierra la sesión si el usuario entra desde otro lugar
            \App\Http\Middleware\RestrictVendedorFromPanel::class, // ✔️ Bloquea acceso a vendedores
            \App\Http\Middleware\RequireConfiguracionEmpresa::class, // ✔️ Bloquea todo el panel hasta completar el wizard de configuración
        ])
        ->userMenuItems([
            UserMenuItem::make()
                ->label('Configurar Empresa')
                ->icon('heroicon-o-building-office')
                ->url(fn () => route('filament.admin.resources.configuracion-empresas.create', auth()->user()))
                ->visible(fn () =>
                    auth()->check() &&
                    auth()->user()->hasRole('admin_empresa')
                ),
        ])
        ->profile(EditProfile::class)
        ->renderHook(
            PanelsRenderHook::TOPBAR_START,
            fn () => view('filament.topbar-brand')
        )
        ->renderHook(
            PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
            fn () => view('filament.turion-olvidar-emparejamiento')
        )
        ->renderHook(
            PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
            // Solo Turion (hibrida) tiene actualizaciones automaticas -- la
            // edicion Local las saco (ver App\Support\PosEdition::esLocal())
            // porque cada actualizacion se cobra aparte y se entrega a mano
            // por el super_admin, nunca por un manifiesto publico.
            fn () => \App\Support\PosEdition::esHibrida() ? view('filament.turion-updater-script') : ''
        )
        ->renderHook(
    'panels::head.end',
    function () {
        $cssPath = public_path('css/admin.css');
        clearstatcache(true, $cssPath);

        return '
        <link rel="stylesheet" href="/css/admin.css?v=' . filemtime($cssPath) . '">
    ';
    }
)
       

        ->renderHook(
    'panels::body.end',
    fn () => view('filament.session-check')
)
        ->renderHook(
    'panels::body.end',
    fn () => '
        <script>
            (function () {
                function aplicarColorPanel() {
                    var sidebar = document.querySelector(".fi-sidebar");
                    var topbar = document.querySelector(".fi-topbar");
                    if (sidebar) {
                        sidebar.style.setProperty("background", "linear-gradient(180deg, #e0e7ff 0%, #c7d2fe 100%)", "important");
                    }
                    if (topbar) {
                        topbar.style.setProperty("background", "linear-gradient(90deg, #e0e7ff 0%, #c7d2fe 100%)", "important");
                    }
                }
                document.addEventListener("DOMContentLoaded", aplicarColorPanel);
                document.addEventListener("livewire:navigated", aplicarColorPanel);
                aplicarColorPanel();
            })();
        </script>
    '
);


}

public function configurePanel(Panel $panel): void
{
    $panel->redirectTo(function () {
        $user = Auth::user();

        if ($user->hasRole('super_admin')) {
    return route('filament.admin.pages.dashboard');
}

 $esVendedor = $user->hasRole('vendedor');

    $esDigitador = $user->hasRole('digitador');

    $esCajero = $user->hasRole('cajero');


// ADMIN EMPRESA
if ($user->hasRole('admin_empresa')) {
    if (method_exists($user, 'necesitaConfiguracionInicial') && $user->necesitaConfiguracionInicial()) {
        return route('filament.admin.resources.configuracion-empresas.create');
    }
    return route('eleccion');
}

// SI TIENE DIGITADOR
if ($esDigitador) {
    return route('filament.admin.pages.dashboard');
}

// SI TIENE CAJERO O VENDEDOR
if ($esCajero || $esVendedor) {
    return redirect()->route('pos');
}
        abort(403, 'Acceso no autorizado');
    });
}
}
