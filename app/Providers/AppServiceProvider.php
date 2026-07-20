<?php

namespace App\Providers;

use App\Http\Responses\LoginResponse as CustomLoginResponse;
use App\Models\ConfiguracionEmpresa;
use App\Models\ProductoVariante;
use App\Models\User;
use App\Observers\ProductoVarianteObserver;
use App\Observers\UserObserver;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Contracts\LoginResponse;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LoginResponse::class, CustomLoginResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        User::observe(UserObserver::class);
        ProductoVariante::observe(ProductoVarianteObserver::class);

        View::composer('layouts.pos', function ($view) {
            if (! auth()->check()) {
                return $view->with('nombreEmpresa', 'Empresa no configurada');
            }

            $user = auth()->user();
            $empresaId = $user->hasRole('super_admin') ? null : $user->getEmpresaActualId();
            $config = $empresaId
                ? ConfiguracionEmpresa::where('empresa_id', $empresaId)->first()
                : null;

            $view->with('nombreEmpresa', $config?->nombre_empresa ?? 'Empresa no configurada');
        });
    }
}
