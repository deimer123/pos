<?php

namespace App\Http\Controllers;

use App\Models\Actor;
use App\Models\Factura;
use App\Models\HotelReserva;
use App\Models\Mesa;
use App\Models\OperacionOfflineSincronizada;
use App\Models\TallerOrden;
use App\Services\Hotel\GuardarReservaService;
use App\Services\Mesas\AgregarItemMesaService;
use App\Services\Taller\GuardarOrdenTallerService;
use App\Services\Ventas\FacturarVentaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Recibe las operaciones que una terminal de Turion (o el navegador,
 * mientras estuvo sin conexion) tenia pendientes de subir, y las rehace
 * en el servidor reutilizando la misma logica del flujo online
 * (FacturarVentaService, AgregarItemMesaService, etc.) para que el
 * resultado sea identico a si se hubiera hecho con internet -- incluyendo
 * los numeros de factura/orden/reserva, que siempre los asigna el
 * servidor al confirmar, nunca el cliente.
 *
 * Todos los metodos son idempotentes por uuid: si el mismo uuid ya se
 * proceso antes (reintento por conexion inestable a medio subir), se
 * devuelve el resultado guardado sin repetir la operacion.
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
        // el servidor (incluye lo que este mismo dispositivo acaba de subir
        // arriba en la cola) -- no se confia en precios que pudiera mandar
        // el cliente offline.
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

    /**
     * Crea en el servidor una orden de taller que se abrio por primera vez
     * estando offline. El servidor asigna su propio numero_orden secuencial
     * (igual que ya hace TallerOrden::booted() al crear online) -- el
     * numero local de Turion solo sirve para el ticket impreso local.
     */
    public function tallerCrear(Request $request): JsonResponse
    {
        $data = $request->validate([
            'uuid' => 'required|uuid',
            'cliente_nombre' => 'required|string|max:200',
            'cliente_telefono' => 'nullable|string|max:30',
            'placa' => 'required|string|max:20',
            'marca' => 'nullable|string|max:80',
            'modelo' => 'nullable|string|max:80',
            'color' => 'nullable|string|max:50',
            'km_ingreso' => 'nullable|integer',
            'diagnostico' => 'nullable|string',
            'observaciones' => 'nullable|string',
            'fecha_entrega_estimada' => 'nullable|date',
        ]);

        $empresaId = auth()->user()->getEmpresaActualId();

        if ($existente = $this->buscarSincronizada($data['uuid'])) {
            return response()->json(['id' => $existente->resultado_id]);
        }

        $orden = TallerOrden::create([
            'empresa_id' => $empresaId,
            'cliente_nombre' => $data['cliente_nombre'],
            'cliente_telefono' => $data['cliente_telefono'] ?? null,
            'placa' => strtoupper($data['placa']),
            'marca' => $data['marca'] ?? null,
            'modelo' => $data['modelo'] ?? null,
            'color' => $data['color'] ?? null,
            'km_ingreso' => $data['km_ingreso'] ?? null,
            'diagnostico' => $data['diagnostico'] ?? null,
            'observaciones' => $data['observaciones'] ?? null,
            'fecha_entrega_estimada' => $data['fecha_entrega_estimada'] ?? null,
            'estado' => 'pendiente',
            'creado_por' => auth()->id(),
        ]);

        $this->registrarSincronizada($data['uuid'], 'taller_crear', $empresaId, $orden->id);

        return response()->json(['id' => $orden->id, 'numero_orden' => $orden->numero_orden]);
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
            $clienteId = Actor::where('empresa_id', $empresaId)
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

    /**
     * Crea en el servidor una reserva de hotel que se abrio por primera
     * vez estando offline. El servidor asigna su propio numero_reserva
     * secuencial (igual que HotelReserva::booted() al crear online).
     */
    public function hotelCrear(Request $request): JsonResponse
    {
        $data = $request->validate([
            'uuid' => 'required|uuid',
            'habitacion_id' => 'required|integer',
            'huesped_nombre' => 'required|string|max:200',
            'huesped_telefono' => 'nullable|string|max:30',
            'huesped_documento' => 'nullable|string|max:50',
            'numero_personas' => 'nullable|integer|min:1',
            'fecha_checkin' => 'required|date',
            'fecha_checkout' => 'nullable|date',
            'precio_noche' => 'nullable|numeric|min:0',
            'observaciones' => 'nullable|string',
        ]);

        $empresaId = auth()->user()->getEmpresaActualId();

        if ($existente = $this->buscarSincronizada($data['uuid'])) {
            return response()->json(['id' => $existente->resultado_id]);
        }

        $habitacion = \App\Models\HotelHabitacion::where('id', $data['habitacion_id'])->where('empresa_id', $empresaId)->first();
        if (! $habitacion) {
            return response()->json(['message' => 'La habitación no existe.'], 422);
        }

        $reserva = HotelReserva::create([
            'empresa_id' => $empresaId,
            'habitacion_id' => $data['habitacion_id'],
            'huesped_nombre' => $data['huesped_nombre'],
            'huesped_telefono' => $data['huesped_telefono'] ?? null,
            'huesped_documento' => $data['huesped_documento'] ?? null,
            'numero_personas' => $data['numero_personas'] ?? 1,
            'fecha_checkin' => $data['fecha_checkin'],
            'fecha_checkout' => $data['fecha_checkout'] ?? null,
            'precio_noche' => $data['precio_noche'] ?? $habitacion->precioParaPersonas($data['numero_personas'] ?? 1),
            'observaciones' => $data['observaciones'] ?? null,
            'estado' => 'checkin',
            'checkin_real_at' => now(),
            'creado_por' => auth()->id(),
        ]);

        $this->registrarSincronizada($data['uuid'], 'hotel_crear', $empresaId, $reserva->id);

        return response()->json(['id' => $reserva->id, 'numero_reserva' => $reserva->numero_reserva]);
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
            $clienteId = Actor::where('empresa_id', $empresaId)
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
