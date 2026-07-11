<?php

namespace App\Http\Middleware;

use App\Filament\Resources\ConfiguracionEmpresaResource;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RequireConfiguracionEmpresa
{
    public function handle(Request $request, Closure $next)
    {
        if (
            $request->is('admin/login') ||
            $request->is('logout') ||
            $request->is('admin/configuracion-empresas*')
        ) {
            return $next($request);
        }

        $user = Auth::user();

        // Solo admin_empresa es responsable de completar este wizard; otros
        // roles (vendedor, cajero, mesero, etc.) no deben quedar bloqueados
        // por una configuración pendiente que no les corresponde a ellos.
        if (! $user || ! $user->hasRole('admin_empresa') || ! $user->necesitaConfiguracionInicial()) {
            return $next($request);
        }

        return redirect(ConfiguracionEmpresaResource::getUrl('create'));
    }
}
