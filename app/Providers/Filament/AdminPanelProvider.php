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
        ->colors([
            'primary' => Color::Amber,
        ])
        ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
        ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
        ->pages([
            Pages\Dashboard::class,
        ])
        ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
        ->widgets([
            Widgets\AccountWidget::class,
            Widgets\FilamentInfoWidget::class,
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
            \App\Http\Middleware\RestrictVendedorFromPanel::class, // ✔️ Bloquea acceso a vendedores
        ])
        ->userMenuItems([
            UserMenuItem::make()
                ->label('Configurar Empresa')
                ->icon('heroicon-o-building-office')
                ->url(fn () => route('filament.admin.resources.configuracion-empresas.create', auth()->user()))
                ->visible(fn () =>
                    auth()->check() &&
                    (auth()->user()->hasRole('admin_empresa') || auth()->user()->roles->contains('id', 2))
                ),
        ])
        ->profile(EditProfile::class);
}

public function configurePanel(Panel $panel): void
{
    $panel->redirectTo(function () {
        $user = Auth::user();

        if ($user->hasRole('super_admin')) {
            return route('filament.admin.pages.dashboard');
        }

        if ($user->hasRole('admin_empresa')) {
            return route('redirect-after-login');
        }

        $esVendedor = $user->hasRole('vendedor');
        $esDigitador = $user->hasRole('digitador');

        if ($esVendedor && $esDigitador) {
            return route('redirect-after-login');
        }

        if ($esVendedor && ! $esDigitador) {
            return route('pos');
        }

        if ($esDigitador && ! $esVendedor) {
            return route('filament.admin.pages.dashboard');
        }

        abort(403, 'Acceso no autorizado');
    });
}
}
