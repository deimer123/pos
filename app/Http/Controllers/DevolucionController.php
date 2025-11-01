<?php

namespace App\Http\Controllers;

use App\Models\Devolucion;

class DevolucionController extends Controller
{
    public function imprimir(int $id)
    {
        $user = auth()->user();

        // Ajusta esta lógica a tu app si es diferente
        $empresaId = ($user->hasRole('vendedor') && $user->empresa_id)
            ? (int) $user->empresa_id
            : (int) $user->id;

        $dev = Devolucion::with(['factura.cliente', 'detalles'])
            ->where('empresa_id', $empresaId)
            ->findOrFail($id);

        return view('devoluciones.imprimir', compact('dev'));
    }
}
