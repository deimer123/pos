<?php

namespace App\Http\Controllers;

use App\Services\Sucursales\SucursalActual;
use Illuminate\Http\Request;

class SucursalActualController extends Controller
{
    public function cambiar(Request $request, SucursalActual $servicio)
    {
        $request->validate(['sucursal' => ['required']]);

        abort_unless($servicio->establecer($request->user(), $request->input('sucursal')), 403);

        return redirect()->back();
    }
}
