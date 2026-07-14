<?php

namespace App\Http\Controllers;

use App\Models\ConflictoOffline;
use App\Models\HotelReserva;
use App\Models\Mesa;
use App\Models\OperacionOfflineSincronizada;
use App\Models\TallerOrden;
use App\Services\Clientes\GuardarClienteService;
use App\Services\Hotel\GuardarReservaService;
use App\Services\Mesas\AgregarItemMesaService;
use App\Services\Taller\GuardarOrdenTallerService;
use App\Services\Ventas\FacturarVentaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Recibe las operaciones que el POS guardo localmente mientras no habia
 * conexion (ver resources/js/pos-offline-queue.js) y las rehace en el
 * servidor, reutilizando la misma logica que el flujo online
 * (App\Services\Ventas\FacturarVentaService, etc.) para que el resultado
 * sea identico a si se hubiera facturado con internet.
 *
 * Todas las rutas son idempotentes por uuid: si el mismo uuid ya se
 * proceso antes (reintento por conexion inestable a medio sincronizar),
 * se devuelve el resultado guardado sin repetir la operacion.
 */
class PosSyncController extends Controller
{
    public function venta(Request $request): JsonResponse
    {
        $data = $request->validate([
            'uuid' => 'required|uuid',
            'carrito' => 'required|array|min:1',
            'cliente_id' => 'nullable|integer',
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
                'cliente_id' => $data['cliente_id'] ?? null,
                // Regla de negocio de la Fase 2: las ventas offline siempre
                // son "salida" (no fiscal) y de contado. No se confia en lo
                // que mande el cliente para esto.
                'tipo_factura' => 'salida',
                'tipo_pago' => 'contado',
                'medio_pago' => $data['medio_pago'] ?? 'efectivo',
                'observaciones' => $data['observaciones'] ?? null,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $this->registrarSincronizada($data['uuid'], 'venta', $empresaId, $factura->id);

        return $this->respuestaFactura($factura->id);
    }

    public function mesaItem(Request $request, AgregarItemMesaService $service): JsonResponse
    {
        $data = $request->validate([
            'uuid' => 'required|uuid',
            'mesa_id' => 'required|integer',
            'id_producto' => 'required',
            'cantidad_delta' => 'required|numeric|min:0.01',
        ]);

        $empresaId = auth()->user()->getEmpresaActualId();

        if ($existente = $this->buscarSincronizada($data['uuid'])) {
            return response()->json(['id' => $existente->resultado_id]);
        }

        $mesa = Mesa::where('id', $data['mesa_id'])->where('empresa_id', $empresaId)->first();
        if (! $mesa) {
            return response()->json(['message' => 'La mesa no existe.'], 422);
        }

        try {
            $item = $service->incrementarItem($data['id_producto'], (float) $data['cantidad_delta'], (int) $data['mesa_id'], $empresaId, auth()->id());
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $this->registrarSincronizada($data['uuid'], 'mesa_item', $empresaId, $item->id);

        return response()->json(['id' => $item->id]);
    }

    public function mesaFacturar(Request $request, AgregarItemMesaService $mesaService): JsonResponse
    {
        $data = $request->validate([
            'uuid' => 'required|uuid',
            'mesa_id' => 'required|integer',
            'medio_pago' => 'nullable|string|in:efectivo,transferencia',
        ]);

        $empresaId = auth()->user()->getEmpresaActualId();

        if ($existente = $this->buscarSincronizada($data['uuid'])) {
            return $this->respuestaFactura($existente->resultado_id);
        }

        $mesa = Mesa::where('id', $data['mesa_id'])->where('empresa_id', $empresaId)->first();
        if (! $mesa) {
            return response()->json(['message' => 'La mesa no existe.'], 422);
        }

        // El carrito se reconstruye desde los OrdenMesaItem que ya esten en
        // el servidor (incluye lo que este mismo dispositivo acaba de
        // sincronizar arriba en la cola) — no se confia en precios que
        // pudiera mandar el cliente offline.
        $carrito = $mesaService->cargarCarritoDesdeOrdenMesa((int) $data['mesa_id'], $empresaId);

        if (empty($carrito)) {
            return response()->json(['message' => 'La mesa no tiene productos para facturar.'], 422);
        }

        try {
            $factura = app(FacturarVentaService::class)->facturar($carrito, [
                'empresa_id' => $empresaId,
                'user_id' => auth()->id(),
                'vendedor_id' => auth()->id(),
                'cliente_id' => null,
                'tipo_factura' => 'salida',
                'tipo_pago' => 'contado',
                'medio_pago' => $data['medio_pago'] ?? 'efectivo',
                'mesa_id' => (int) $data['mesa_id'],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $this->registrarSincronizada($data['uuid'], 'mesa_facturar', $empresaId, $factura->id);

        return $this->respuestaFactura($factura->id);
    }

    public function tallerItem(Request $request, GuardarOrdenTallerService $service): JsonResponse
    {
        $data = $request->validate([
            'uuid' => 'required|uuid',
            'taller_orden_id' => 'required|integer',
            'items' => 'required|array',
            'items.*.id_producto' => 'required',
            'items.*.nombre' => 'required|string',
            'items.*.cantidad' => 'required|numeric|min:0.01',
            'items.*.precio' => 'required|numeric|min:0',
        ]);

        $empresaId = auth()->user()->getEmpresaActualId();

        if ($existente = $this->buscarSincronizada($data['uuid'])) {
            return response()->json(['id' => $existente->resultado_id]);
        }

        $orden = TallerOrden::where('id', $data['taller_orden_id'])->where('empresa_id', $empresaId)->first();
        if (! $orden) {
            return response()->json(['message' => 'La orden de taller no existe.'], 422);
        }

        try {
            $service->sincronizarItems((int) $data['taller_orden_id'], $empresaId, $data['items']);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $this->registrarSincronizada($data['uuid'], 'taller_item', $empresaId, $orden->id);

        return response()->json(['id' => $orden->id]);
    }

    public function tallerFacturar(Request $request, GuardarOrdenTallerService $tallerService): JsonResponse
    {
        $data = $request->validate([
            'uuid' => 'required|uuid',
            'taller_orden_id' => 'required|integer',
            'medio_pago' => 'nullable|string|in:efectivo,transferencia',
        ]);

        $empresaId = auth()->user()->getEmpresaActualId();

        if ($existente = $this->buscarSincronizada($data['uuid'])) {
            return $this->respuestaFactura($existente->resultado_id);
        }

        $orden = TallerOrden::where('id', $data['taller_orden_id'])->where('empresa_id', $empresaId)->first();
        if (! $orden) {
            return response()->json(['message' => 'La orden de taller no existe.'], 422);
        }

        // Igual que CarritoVenta::cargarOrdenTaller(): si el nombre del
        // cliente de la orden coincide con un Actor existente, la factura
        // queda a nombre de ese cliente en vez de Consumidor Final.
        $clienteId = null;
        if ($orden->cliente_nombre) {
            $clienteId = \App\Models\Actor::where('empresa_id', $empresaId)
                ->whereRaw('LOWER(TRIM(nombre)) = ?', [mb_strtolower(trim($orden->cliente_nombre))])
                ->value('id');
        }

        $carrito = $tallerService->cargarCarritoDesdeOrden((int) $data['taller_orden_id'], $empresaId);

        if (empty($carrito)) {
            return response()->json(['message' => 'La orden de taller no tiene repuestos para facturar.'], 422);
        }

        try {
            $factura = app(FacturarVentaService::class)->facturar($carrito, [
                'empresa_id' => $empresaId,
                'user_id' => auth()->id(),
                'vendedor_id' => auth()->id(),
                'cliente_id' => $clienteId,
                'tipo_factura' => 'salida',
                'tipo_pago' => 'contado',
                'medio_pago' => $data['medio_pago'] ?? 'efectivo',
                'taller_orden_id' => (int) $data['taller_orden_id'],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $this->registrarSincronizada($data['uuid'], 'taller_facturar', $empresaId, $factura->id);

        return $this->respuestaFactura($factura->id);
    }

    public function hotelItem(Request $request, GuardarReservaService $service): JsonResponse
    {
        $data = $request->validate([
            'uuid' => 'required|uuid',
            'hotel_reserva_id' => 'required|integer',
            'items' => 'required|array',
            'items.*.id_producto' => 'required',
            'items.*.nombre' => 'required|string',
            'items.*.cantidad' => 'required|numeric|min:0.01',
            'items.*.precio' => 'required|numeric|min:0',
        ]);

        $empresaId = auth()->user()->getEmpresaActualId();

        if ($existente = $this->buscarSincronizada($data['uuid'])) {
            return response()->json(['id' => $existente->resultado_id]);
        }

        $reserva = HotelReserva::where('id', $data['hotel_reserva_id'])->where('empresa_id', $empresaId)->first();
        if (! $reserva) {
            return response()->json(['message' => 'La reserva no existe.'], 422);
        }

        try {
            $service->sincronizarConsumos((int) $data['hotel_reserva_id'], $empresaId, $data['items']);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $this->registrarSincronizada($data['uuid'], 'hotel_item', $empresaId, $reserva->id);

        return response()->json(['id' => $reserva->id]);
    }

    public function hotelFacturar(Request $request, GuardarReservaService $hotelService): JsonResponse
    {
        $data = $request->validate([
            'uuid' => 'required|uuid',
            'hotel_reserva_id' => 'required|integer',
            'medio_pago' => 'nullable|string|in:efectivo,transferencia',
        ]);

        $empresaId = auth()->user()->getEmpresaActualId();

        if ($existente = $this->buscarSincronizada($data['uuid'])) {
            return $this->respuestaFactura($existente->resultado_id);
        }

        $reserva = HotelReserva::where('id', $data['hotel_reserva_id'])->where('empresa_id', $empresaId)->first();
        if (! $reserva) {
            return response()->json(['message' => 'La reserva no existe.'], 422);
        }

        $clienteId = null;
        if ($reserva->actor_id) {
            $clienteId = $reserva->actor_id;
        } elseif ($reserva->huesped_nombre) {
            $clienteId = \App\Models\Actor::where('empresa_id', $empresaId)
                ->whereRaw('LOWER(TRIM(nombre)) = ?', [mb_strtolower(trim($reserva->huesped_nombre))])
                ->value('id');
        }

        $carrito = $hotelService->cargarCarritoDesdeReserva((int) $data['hotel_reserva_id'], $empresaId);

        if (empty($carrito)) {
            return response()->json(['message' => 'La reserva no tiene nada para facturar.'], 422);
        }

        try {
            $factura = app(FacturarVentaService::class)->facturar($carrito, [
                'empresa_id' => $empresaId,
                'user_id' => auth()->id(),
                'vendedor_id' => auth()->id(),
                'cliente_id' => $clienteId,
                'tipo_factura' => 'salida',
                'tipo_pago' => 'contado',
                'medio_pago' => $data['medio_pago'] ?? 'efectivo',
                'hotel_reserva_id' => (int) $data['hotel_reserva_id'],
                'hotel_abono_monto' => (float) $reserva->abono_monto,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $this->registrarSincronizada($data['uuid'], 'hotel_facturar', $empresaId, $factura->id);

        return $this->respuestaFactura($factura->id);
    }

    public function clienteCrear(Request $request, GuardarClienteService $service): JsonResponse
    {
        $data = $request->validate([
            'uuid' => 'required|uuid',
            'datos' => 'required|array',
        ]);

        $empresaId = auth()->user()->getEmpresaActualId();

        if ($existente = $this->buscarSincronizada($data['uuid'])) {
            return response()->json(['id' => $existente->resultado_id]);
        }

        try {
            $cliente = $service->crearRapido($empresaId, $data['datos']);
        } catch (ValidationException $e) {
            return response()->json(['message' => implode(' ', $e->validator->errors()->all())], 422);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $this->registrarSincronizada($data['uuid'], 'cliente_crear', $empresaId, $cliente->id);

        return response()->json(['id' => $cliente->id, 'nombre' => $cliente->nombre]);
    }

    /**
     * El dispositivo llama esto cuando una operacion de la cola offline
     * termino en conflicto (ej. stock insuficiente), para que quede
     * visible centralmente en la pantalla de revision (Filament), no solo
     * en el navegador de esa caja/mesero.
     */
    public function reportarConflicto(Request $request): JsonResponse
    {
        $data = $request->validate([
            'uuid' => 'required|uuid',
            'tipo' => 'required|string',
            'payload' => 'nullable|array',
            'error' => 'nullable|string',
        ]);

        $empresaId = auth()->user()->getEmpresaActualId();

        ConflictoOffline::updateOrCreate(
            ['uuid' => $data['uuid']],
            [
                'tipo' => $data['tipo'],
                'empresa_id' => $empresaId,
                'payload' => $data['payload'] ?? null,
                'error' => $data['error'] ?? null,
                'estado' => 'pendiente',
            ]
        );

        return response()->json(['ok' => true]);
    }

    private function respuestaFactura(int $facturaId): JsonResponse
    {
        $factura = \App\Models\Factura::find($facturaId);

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

    private function registrarSincronizada(string $uuid, string $tipo, int $empresaId, ?int $resultadoId): void
    {
        OperacionOfflineSincronizada::create([
            'uuid' => $uuid,
            'tipo' => $tipo,
            'empresa_id' => $empresaId,
            'resultado_id' => $resultadoId,
        ]);
    }
}
