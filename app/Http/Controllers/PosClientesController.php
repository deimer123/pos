<?php

namespace App\Http\Controllers;

use App\Models\Actor;
use Illuminate\Http\JsonResponse;

/**
 * Catalogo de clientes de la empresa actual, para que el POS lo guarde
 * localmente (IndexedDB) y pueda buscar/elegir cliente sin conexion.
 * Mismo patron que PosCatalogoController (productos).
 */
class PosClientesController extends Controller
{
    public function index(): JsonResponse
    {
        $empresaId = auth()->user()->getEmpresaActualId();

        $clientes = Actor::query()
            ->whereIn('tipo', [1, 2])
            ->where('empresa_id', $empresaId)
            ->orderBy('nombre')
            ->get(['id', 'id_clip_pro', 'nombre', 'identificacion', 'telefono', 'direccion', 'email', 'permite_credito'])
            ->values();

        return response()->json(['clientes' => $clientes, 'empresa_id' => $empresaId]);
    }
}
