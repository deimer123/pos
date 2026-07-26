<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PairingCode;
use App\Models\Prefactura;
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

    /**
     * Prefacturas "borrador" activas de la empresa (con sus productos),
     * para que Turion las baje al sincronizar -- ver PosSyncPrefacturas
     * en Turion, que fusiona esta lista con lo que ya tiene localmente.
     * No incluye prefacturas ya facturadas/eliminadas: si una que Turion
     * tenia bajada deja de aparecer aqui, es la señal de que ya se
     * facturo (o se borro) directo en el droplet.
     */
    public function prefacturas(Request $request): JsonResponse
    {
        $empresaId = $request->user()->getEmpresaActualId();

        $prefacturas = Prefactura::with(['productos', 'cliente'])
            ->where('empresa_id', $empresaId)
            ->where('estado', 'borrador')
            ->get();

        return response()->json([
            'prefacturas' => $prefacturas->map(fn (Prefactura $p) => [
                'id' => $p->id,
                'cliente_id' => $p->cliente_id,
                'cliente_nombre' => $p->cliente?->nombre,
                'cliente_identificacion' => $p->cliente?->identificacion,
                'vendedor_id' => $p->vendedor_id,
                'observaciones' => $p->observaciones,
                'estado' => $p->estado,
                'items' => $p->productos->map(fn ($item) => [
                    'producto_id' => $item->producto_id,
                    'nombre' => $item->descripcion_larga,
                    'cantidad' => (float) $item->cantidad,
                    'precio' => (float) $item->precio_unitario,
                    'descuento' => (float) $item->descuento,
                ])->values(),
            ])->values(),
        ]);
    }
}
