<?php

// app/Http/Controllers/DevolucionController.php
namespace App\Http\Controllers;

use App\Models\Devolucion;

class DevolucionController extends Controller
{
    public function imprimir(int $id)
    {
        $user = auth()->user();
        $empresaId = $user->hasRole('vendedor') && $user->empresa_id ? $user->empresa_id : $user->id;

        $dev = Devolucion::with(['factura.cliente','detalles'])
            ->where('empresa_id', $empresaId)
            ->findOrFail($id);

        return view('devoluciones.imprimir', compact('dev'));
    }
}
