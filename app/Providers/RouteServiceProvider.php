<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The default path users are redirected to after login.
     */
    public const HOME = '/redirect-after-login';// NO lo usaremos directamente, pero lo puedes dejar.

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(function () {
                    Route::get('/', function () {
                        return redirect('/admin'); // Puedes dejar esta si deseas.
                    });

                    require base_path('routes/web.php');
                });
        });
    }

    /**
     * Define a custom redirect path after login based on role.
     */
    public function redirectTo(): string
    {
        $user = auth()->user();

        if ($user && $user->hasRole('vendedor')) {
            return '/pos';
        }

        return '/admin'; // Otros roles (admin, superadmin, etc)
    }
}
