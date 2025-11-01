<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SoloDigitador
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if (! $user || ! $user->hasRole('digitador')) {
            abort(403, 'Solo los digitadores pueden acceder a esta sección.');
        }

        return $next($request);
    }
}