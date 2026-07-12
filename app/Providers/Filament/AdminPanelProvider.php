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
    'panels::head.end',
    fn () => '
        <link rel="stylesheet" href="/css/admin.css?v=' . filemtime(public_path('css/admin.css')) . '">
    '
)
       

        ->renderHook(
    'panels::body.end',
    fn () => view('filament.session-check')
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
