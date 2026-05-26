<?php

namespace App\Http\Controllers;

use App\Models\Devolucion;

class DevolucionController extends Controller
{
    public function imprimir(int $id)
    {
        $user = auth()->user();

        // Ajusta esta lógica a tu app si es diferente
        $empresaId = (int) $user->getEmpresaActualId();

        $dev = Devolucion::with(['factura.cliente', 'detalles'])
            ->where('empresa_id', $empresaId)
            ->findOrFail($id);

        return view('devoluciones.imprimir', compact('dev'));
    }
}
