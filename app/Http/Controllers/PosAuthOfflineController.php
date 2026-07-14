<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Confirma la contraseña del cajero para poder activar el "desbloqueo
 * offline" de este dispositivo (ver resources/js/pos-offline-auth.js).
 *
 * Importante: esto NO crea ninguna sesion nueva ni manda el hash real de
 * la cuenta al navegador -- solo confirma que la contraseña escrita es
 * correcta (Hash::check contra la BD), y el navegador es quien deriva y
 * guarda su propio hash local (distinto, con su propia sal) para poder
 * comparar despues sin conexion.
 */
class PosAuthOfflineController extends Controller
{
    public function confirmarPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'password' => 'required|string',
        ]);

        $user = auth()->user();

        if (! Hash::check($data['password'], $user->password)) {
            return response()->json(['ok' => false, 'message' => 'Contraseña incorrecta.'], 422);
        }

        return response()->json(['ok' => true, 'user_id' => $user->id]);
    }
}
