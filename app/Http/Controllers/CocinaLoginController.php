<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CocinaLoginController extends Controller
{
    public function showLogin()
    {
        if (Auth::check() && Auth::user()->hasAnyRole(['cocina', 'admin_empresa', 'cajero'])) {
            return redirect()->route('cocina');
        }

        return view('cocina.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials, false)) {
            $user = Auth::user();

            if (! $user->hasAnyRole(['cocina', 'admin_empresa', 'cajero'])) {
                Auth::logout();
                return back()->withErrors(['email' => 'No tienes acceso a la pantalla de cocina.']);
            }

            $request->session()->regenerate();

            return redirect()->route('cocina');
        }

        return back()->withErrors(['email' => 'Credenciales incorrectas.']);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('cocina.login');
    }
}
