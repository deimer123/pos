<?php

namespace App\Http\Responses;

use Filament\Http\Responses\Auth\Contracts\LoginResponse as FilamentLoginResponseContract;
use Laravel\Fortify\Contracts\LoginResponse as FortifyLoginResponseContract;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

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
            DB::table('sessions')
                ->where('user_id', $user->id)
                ->where('id', '!=', $request->session()->getId())
                ->delete();
        }

        if ($user?->hasRole('super_admin')) {
            return redirect()->intended(route('filament.admin.pages.dashboard'));
        }

        if ($user?->necesitaConfiguracionInicial()) {
            return redirect()->intended(route('filament.admin.resources.configuracion-empresas.create'));
        }

        return redirect()->intended('/eleccion');
    }
}
