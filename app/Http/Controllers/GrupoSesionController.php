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

    // Entrar eligiendo la sucursal en el select de la barra superior. Las
    // mismas reglas que entrar(): el servicio valida que sea sucursal propia.
    public function entrarSeleccion(Request $request, CambioDeEmpresa $servicio)
    {
        $datos = $request->validate(['sucursal' => ['required', 'integer']]);

        $sucursal = User::find($datos['sucursal']);

        if (! $sucursal) {
            return redirect()->back()->with('grupo_error', 'Esa sucursal no existe.');
        }

        $motivo = $servicio->entrar($request, $request->user(), $sucursal);

        return $motivo
            ? redirect()->back()->with('grupo_error', $motivo)
            : redirect()->route('eleccion');
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
