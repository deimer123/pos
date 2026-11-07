<?php

namespace App\Services\Grupo;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

// "Entrar" a una sucursal y "Volver" a la empresa del dueno. Cada cambio es
// un inicio de sesion real del otro usuario, con el mismo control de sesion
// unica que LoginResponse (session_token + session_id). Nada de datos se
// mezcla: la sucursal es una empresa normal y se trabaja como ella.
class CambioDeEmpresa
{
    public const SESSION_ORIGEN = 'grupo_origen_id';

    public function estaEnSucursal(): bool
    {
        return session()->has(self::SESSION_ORIGEN);
    }

    // null = se puede entrar; si no, el motivo para mostrarlo.
    public function motivoNoPuedeEntrar(User $actual, User $sucursal): ?string
    {
        if ($this->estaEnSucursal()) {
            return 'Primero vuelve a tu empresa para entrar a otra sucursal.';
        }

        if (! $actual->esEmpresa() || ! $actual->hasRole('admin_empresa')) {
            return 'Solo el administrador de la empresa puede entrar a sus sucursales.';
        }

        if (! $sucursal->esEmpresa() || (int) $sucursal->empresa_matriz_id !== (int) $actual->id) {
            return 'Esa empresa no es una sucursal tuya.';
        }

        if (! $sucursal->hasRole('admin_empresa')) {
            return 'La sucursal no tiene un administrador configurado.';
        }

        if (! $sucursal->puedeIngresarPorPlan()) {
            return 'La sucursal está inactiva o su plan está vencido. Contacta al administrador del sistema.';
        }

        return null;
    }

    public function entrar(Request $request, User $actual, User $sucursal): ?string
    {
        if ($motivo = $this->motivoNoPuedeEntrar($actual, $sucursal)) {
            return $motivo;
        }

        $this->iniciarSesionComo($request, $sucursal);
        $request->session()->put(self::SESSION_ORIGEN, $actual->id);

        Log::info('Cambio a sucursal', ['matriz_id' => $actual->id, 'sucursal_id' => $sucursal->id]);

        return null;
    }

    // null = se volvio; si no, el motivo.
    public function volver(Request $request, User $actual): ?string
    {
        $origenId = $request->session()->get(self::SESSION_ORIGEN);
        $origen = $origenId ? User::find($origenId) : null;

        // Solo se vuelve a la matriz de la sucursal en la que se esta.
        if (! $origen || (int) $actual->empresa_matriz_id !== (int) $origen->id || ! $origen->puedeIngresarPorPlan()) {
            return 'No se pudo volver a tu empresa. Cierra sesión e inicia de nuevo.';
        }

        $this->iniciarSesionComo($request, $origen);
        $request->session()->forget(self::SESSION_ORIGEN);

        Log::info('Regreso a la matriz', ['matriz_id' => $origen->id, 'sucursal_id' => $actual->id]);

        return null;
    }

    private function iniciarSesionComo(Request $request, User $usuario): void
    {
        Auth::login($usuario);

        // AuthenticateSession guarda el hash de la contrasena del usuario
        // anterior; se borra para que guarde el del nuevo y no cierre la sesion.
        $request->session()->forget('password_hash_' . Auth::getDefaultDriver());

        // Igual que LoginResponse: el nuevo usuario queda como dueno de esta
        // sesion (login() ya regenero el id de sesion).
        $token = Str::random(60);
        $usuario->update([
            'session_token' => $token,
            'session_id' => $request->session()->getId(),
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
            'last_user_agent' => $request->userAgent(),
        ]);
        $request->session()->put('session_token', $token);
    }
}
