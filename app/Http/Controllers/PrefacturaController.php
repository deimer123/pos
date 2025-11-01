<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Prefactura;

class PrefacturaController extends Controller
{
    public function imprimir($id)
{
    $prefactura = Prefactura::with(['cliente', 'productos', 'configuracionEmpresa']) // o 'empresa'
        ->where('id', $id)
        ->firstOrFail();

    return view('prefactura.imprimir', compact('prefactura'));
}
}
