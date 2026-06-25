<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnforceSessionUniqueness
{
    public function handle(Request $request, Closure $next)
    {
        if (! Auth::check()) {
            return $next($request);
        }

        $user = Auth::user();

        // Verificación primaria: session_id real de PHP
        // Cada navegador/dispositivo tiene un session_id distinto (distinta cookie de sesión)
        // Si el usuario inició sesión en otro navegador/dispositivo, este session_id no coincidirá
        $sessionId = $request->session()->getId();
        if ($user->session_id && $user->session_id !== $sessionId) {
            return $this->cerrarSesion(
                $request,
                'Tu sesión fue cerrada porque se inició sesión desde otro dispositivo o navegador.'
            );
        }

        // Verificación secundaria: session_token (capa extra de seguridad)
        $sessionToken = $request->session()->get('session_token');
        if ($sessionToken && $user->session_token !== $sessionToken) {
            return $this->cerrarSesion(
                $request,
                'Tu sesión fue cerrada porque se inició sesión desde otro lugar.'
            );
        }

        return $next($request);
    }

    private function cerrarSesion(Request $request, string $mensaje): \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $loginUrl = route('filament.admin.auth.login');

        // Petición Livewire: JSON con redirect para que Livewire lo procese
        if ($request->header('X-Livewire')) {
            return response()->json([
                'effects' => ['redirect' => $loginUrl . '?sesion_cerrada=1'],
            ]);
        }

        // Petición fetch/AJAX (check-tab, check-session, etc.): devolver 401 JSON
        // El JS interpreta el 401 y muestra el overlay correcto
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['error' => 'session_duplicada', 'message' => $mensaje], 401);
        }

        // Navegación normal: redirigir al login con el error en el formulario
        return redirect()->route('filament.admin.auth.login')
            ->withErrors(['email' => $mensaje]);
    }
}
