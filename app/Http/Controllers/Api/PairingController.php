<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PairingCode;
use App\Models\User;
use App\Services\Turion\CatalogoExporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Emparejamiento de una terminal de Turion: el admin genera un codigo desde
 * el panel (ver App\Filament\Pages\EmparejarTerminal), y Turion lo cambia
 * aqui por un token de Sanctum permanente + el paquete inicial de catalogo
 * (mismo contenido que "php artisan pos:export-catalog").
 */
class PairingController extends Controller
{
    public function bootstrap(Request $request, CatalogoExporter $exporter): JsonResponse
    {
        $data = $request->validate([
            'codigo' => 'required|string',
            'terminal_nombre' => 'nullable|string|max:100',
        ]);

        $pairing = PairingCode::where('codigo', strtoupper($data['codigo']))->first();

        if (! $pairing || ! $pairing->valido()) {
            return response()->json(['message' => 'Codigo de emparejamiento invalido o vencido.'], 422);
        }

        $empresa = User::where('id', $pairing->empresa_id)
            ->where('tipo_usuario', 'empresa')
            ->first();

        if (! $empresa) {
            return response()->json(['message' => 'La empresa asociada a este codigo ya no existe.'], 422);
        }

        $nombreTerminal = $data['terminal_nombre'] ?? ('Turion-'.now()->format('YmdHis'));

        $token = $empresa->createToken($nombreTerminal, ['pos-terminal'])->plainTextToken;

        $pairing->update([
            'usado_at' => now(),
            'terminal_nombre' => $nombreTerminal,
        ]);

        return response()->json([
            'token' => $token,
            'empresa_id' => $empresa->id,
            'empresa_nombre' => $empresa->name,
            'catalogo' => $exporter->paraEmpresa($empresa->id),
        ]);
    }

    public function refrescarCatalogo(Request $request, CatalogoExporter $exporter): JsonResponse
    {
        $empresaId = $request->user()->getEmpresaActualId();

        return response()->json([
            'catalogo' => $exporter->paraEmpresa($empresaId),
        ]);
    }
}
