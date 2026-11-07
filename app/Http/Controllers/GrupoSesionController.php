<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Grupo\CambioDeEmpresa;
use Illuminate\Http\Request;

class GrupoSesionController extends Controller
{
    public function entrar(Request $request, User $sucursal, CambioDeEmpresa $servicio)
    {
        $motivo = $servicio->entrar($request, $request->user(), $sucursal);

        if ($motivo) {
            return redirect()->back()->with('grupo_error', $motivo);
        }

        return redirect()->route('eleccion');
    }

    public function volver(Request $request, CambioDeEmpresa $servicio)
    {
        $motivo = $servicio->volver($request, $request->user());

        if ($motivo) {
            return redirect()->back()->with('grupo_error', $motivo);
        }

        return redirect()->route('eleccion');
    }
}
