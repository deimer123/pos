<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Laravel\Fortify\Contracts\LoginResponse;
use App\Http\Responses\LoginResponse as CustomLoginResponse;
use App\Models\User;
use App\Observers\UserObserver;
use App\Models\ConfiguracionEmpresa;

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
    // 👁️ Observar cambios del modelo User
    User::observe(UserObserver::class);

    // 📦 Compartir nombre de la empresa con la vista del POS
    View::composer('layouts.pos', function ($view) {
        if (!auth()->check()) {
            return $view->with('nombreEmpresa', 'Empresa no configurada');
        }

        $user = auth()->user();

        // Lógica para obtener empresa_id dependiendo del rol
        $empresaId = null;

        if ($user->hasRole('admin_empresa')) {
            $empresaId = $user->id;
        } elseif ($user->hasRole('vendedor') && !empty($user->empresa_id)) {
            $empresaId = $user->empresa_id;
        }

        // Consultar la configuración
        $config = \App\Models\ConfiguracionEmpresa::where('empresa_id', $empresaId)->first();

        $nombreEmpresa = $config->nombre_empresa ?? 'Empresa no configurada';

        $view->with('nombreEmpresa', $nombreEmpresa);
    });
}
}
