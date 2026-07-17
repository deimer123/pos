<?php

namespace App\Http\Responses;

use Filament\Http\Responses\Auth\Contracts\LoginResponse as FilamentLoginResponseContract;
use Laravel\Fortify\Contracts\LoginResponse as FortifyLoginResponseContract;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class LoginResponse implements FilamentLoginResponseContract, FortifyLoginResponseContract
{
    public function toResponse($request)
    {
        $user = Auth::user();

        if ($user && ! $user->puedeIngresarPorPlan()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('filament.admin.auth.login')
                ->withErrors([
                    'email' => 'La empresa esta inactiva o el plan se encuentra vencido. Contacta al administrador del sistema.',
                ]);
        }

        if ($user) {
            // Guardar el session_id real de PHP y datos de auditoría
            $token = Str::random(60);
            $user->update([
                'session_token'   => $token,
                'session_id'      => $request->session()->getId(),
                'last_login_at'   => now(),
                'last_login_ip'   => $request->ip(),
                'last_user_agent' => $request->userAgent(),
            ]);

            $request->session()->put('session_token', $token);
        }

        // Rol cocina: solo puede ir a la pantalla de cocina
        if ($user?->hasRole('cocina') && ! $user->hasAnyRole(['admin_empresa', 'cajero', 'vendedor', 'mesero', 'digitador'])) {
            return redirect()->route('cocina');
        }

        // Rol mesero puro: va directo al POS
        if ($user?->hasRole('mesero') && ! $user->hasAnyRole(['admin_empresa', 'cajero', 'vendedor', 'digitador'])) {
            return redirect()->to('/pos');
        }

        // Rol taller puro: va directo al panel de taller
        if ($user?->hasRole('taller') && ! $user->hasAnyRole(['admin_empresa', 'cajero', 'vendedor', 'digitador', 'mesero', 'cocina'])) {
            return redirect()->to('/taller');
        }

        if ($user?->hasRole('super_admin')) {
            return redirect()->intended(route('filament.admin.pages.dashboard'));
        }

        // Solo el admin de la empresa debe terminar el wizard de
        // configuracion inicial. Sin este filtro por rol, un vendedor o
        // cajero que vuelve a iniciar sesion (ej. despues de que la sesion
        // unica lo saco por haber entrado en otro dispositivo) tambien
        // caia aca si la ConfiguracionEmpresa quedaba incompleta, y como
        // esas rutas de /admin estan bloqueadas para esos roles
        // (RestrictVendedorFromPanel), terminaba viendo un 403 en vez del
        // POS.
        if ($user?->hasRole('admin_empresa') && $user->necesitaConfiguracionInicial()) {
            return redirect()->intended(route('filament.admin.resources.configuracion-empresas.create'));
        }

        return redirect()->intended('/eleccion');
    }
}
