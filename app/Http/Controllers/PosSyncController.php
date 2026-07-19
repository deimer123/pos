<?php

namespace App\Http\Controllers;

use App\Models\Factura;
use App\Models\OperacionOfflineSincronizada;
use App\Services\Ventas\FacturarVentaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Recibe las ventas simples que el POS guardo localmente mientras no
 * habia conexion (ver resources/js/pos-offline-queue.js) y las factura
 * en el servidor, reutilizando la misma logica del flujo online
 * (App\Services\Ventas\FacturarVentaService) para que el resultado sea
 * identico a si se hubiera facturado con internet -- incluyendo el
 * numero de factura, que lo asigna el servidor en el momento de
 * confirmar cada una (en el orden en que llegan), nunca el navegador.
 *
 * Idempotente por uuid: si el mismo uuid ya se proceso antes (reintento
 * por conexion inestable a medio sincronizar), se devuelve el resultado
 * guardado sin volver a facturar.
 */
class PosSyncController extends Controller
{
    public function venta(Request $request): JsonResponse
    {
        $data = $request->validate([
            'uuid' => 'required|uuid',
            'carrito' => 'required|array|min:1',
            'medio_pago' => 'nullable|string|in:efectivo,transferencia',
            'observaciones' => 'nullable|string',
        ]);

        $empresaId = auth()->user()->getEmpresaActualId();

        if ($existente = $this->buscarSincronizada($data['uuid'])) {
            return $this->respuestaFactura($existente->resultado_id);
        }

        try {
            $factura = app(FacturarVentaService::class)->facturar($data['carrito'], [
                'empresa_id' => $empresaId,
                'user_id' => auth()->id(),
                'vendedor_id' => auth()->id(),
                // Regla de negocio: las ventas offline siempre son
                // "salida" (no fiscal) y de contado. No hay cliente ni
                // factura electronica sin conexion -- eso se hace despues,
                // ya con internet, editando la venta si hace falta.
                'tipo_factura' => 'salida',
                'tipo_pago' => 'contado',
                'medio_pago' => $data['medio_pago'] ?? 'efectivo',
                'observaciones' => $data['observaciones'] ?? null,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $this->registrarSincronizada($data['uuid'], $empresaId, $factura->id);

        return $this->respuestaFactura($factura->id);
    }

    private function respuestaFactura(int $facturaId): JsonResponse
    {
        $factura = Factura::find($facturaId);

        return response()->json([
            'id' => $facturaId,
            'numero_visual' => $factura?->numero_visual,
            'print_url' => route('factura.imprimir', $facturaId),
        ]);
    }

    private function buscarSincronizada(string $uuid): ?OperacionOfflineSincronizada
    {
        return OperacionOfflineSincronizada::where('uuid', $uuid)->first();
    }

    private function registrarSincronizada(string $uuid, int $empresaId, ?int $resultadoId): void
    {
        OperacionOfflineSincronizada::create([
            'uuid' => $uuid,
            'tipo' => 'venta',
            'empresa_id' => $empresaId,
            'resultado_id' => $resultadoId,
        ]);
    }
}
