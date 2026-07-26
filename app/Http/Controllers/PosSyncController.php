<?php

namespace App\Http\Controllers;

use App\Models\Actor;
use App\Models\Factura;
use App\Models\HotelReserva;
use App\Models\Mesa;
use App\Models\OperacionOfflineSincronizada;
use App\Models\Prefactura;
use App\Models\TallerOrden;
use App\Services\Hotel\GuardarReservaService;
use App\Services\Mesas\AgregarItemMesaService;
use App\Services\Taller\GuardarOrdenTallerService;
use App\Services\Turion\ResolverActorService;
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
        $data = $request->validate(array_merge([
            'uuid' => 'required|uuid',
            'carrito' => 'required|array|min:1',
            'medio_pago' => 'nullable|string|in:efectivo,transferencia',
            'prefactura_servidor_id' => 'nullable|integer',
        ], $this->reglasFacturarComunes()));

        $empresaId = auth()->user()->getEmpresaActualId();

        if ($existente = $this->buscarSincronizada($data['uuid'])) {
            return $this->respuestaFactura($existente->resultado_id);
        }

        // Si esta venta viene de una prefactura que ya se subio antes
        // (Turion la referencia por el id que le asigno el servidor), hay
        // que confirmar que siga viva -- si ya no existe es porque alguien
        // mas ya la facturo (o la borro) desde el droplet directamente, y
        // dejar pasar esta venta facturaria lo mismo dos veces.
        if (! empty($data['prefactura_servidor_id'])) {
            $prefactura = Prefactura::where('id', $data['prefactura_servidor_id'])
                ->where('empresa_id', $empresaId)
                ->first();

            if (! $prefactura) {
                return response()->json([
                    'message' => 'Esta prefactura ya fue facturada o eliminada. Actualiza la lista de prefacturas.',
                ], 409);
            }
        }

        try {
            $factura = app(FacturarVentaService::class)->facturar($data['carrito'], array_merge([
                'empresa_id' => $empresaId,
                'user_id' => auth()->id(),
                'medio_pago' => $data['medio_pago'] ?? 'efectivo',
            ], $this->opcionesFacturarComunes($data, $empresaId)));
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        if (! empty($data['prefactura_servidor_id'])) {
            Prefactura::where('id', $data['prefactura_servidor_id'])->where('empresa_id', $empresaId)->delete();
        }

        $this->registrarSincronizada($data['uuid'], 'venta', $empresaId, $factura->id);

        return $this->respuestaFactura($factura->id);
    }

    /**
     * Crea o actualiza en el servidor la prefactura que Turion tiene
     * guardada localmente -- foto COMPLETA de su estado actual (no un
     * historial de ediciones), asi que cada subida reemplaza cliente,
     * observaciones y productos de una vez.
     *
     * Si ya se habia subido antes (viene "servidor_id") pero esa
     * prefactura ya no existe -- porque se facturo o se borro directo en
     * el droplet mientras tanto -- NO se revive: se avisa con
     * "ya_facturada" para que Turion borre tambien su copia local (asi es
     * como se evita facturarla otra vez desde Turion).
     */
    public function prefacturaGuardar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'uuid' => 'required|uuid',
            'servidor_id' => 'nullable|integer',
            'cliente_id' => 'nullable|integer',
            'cliente' => 'nullable|array',
            'cliente.identificacion' => 'nullable|string',
            'cliente.nombre' => 'nullable|string',
            'cliente.razon_social' => 'nullable|string',
            'cliente.tipo_documento_id' => 'nullable|integer',
            'cliente.telefono' => 'nullable|string',
            'cliente.email' => 'nullable|string',
            'cliente.direccion' => 'nullable|string',
            'cliente.departamento_id' => 'nullable|integer',
            'cliente.ciudad_id' => 'nullable|integer',
            'cliente.tipo_persona' => 'nullable|string',
            'cliente.regimen_tributario' => 'nullable|string',
            'cliente.responsable_iva' => 'nullable|boolean',
            'vendedor_id' => 'nullable|integer',
            'observaciones' => 'nullable|string',
            'estado' => 'nullable|string|in:borrador,vendida',
            'items' => 'required|array',
            'items.*.producto_id' => 'required',
            'items.*.nombre' => 'required|string',
            'items.*.cantidad' => 'required|numeric|min:0.01',
            'items.*.precio' => 'required|numeric|min:0',
            'items.*.descuento' => 'nullable|numeric',
        ]);

        $empresaId = auth()->user()->getEmpresaActualId();

        if ($existente = $this->buscarSincronizada($data['uuid'])) {
            return response()->json(['id' => $existente->resultado_id, 'ya_facturada' => $existente->resultado_id === null]);
        }

        if (! empty($data['servidor_id'])) {
            $prefactura = Prefactura::where('id', $data['servidor_id'])->where('empresa_id', $empresaId)->first();

            if (! $prefactura) {
                $this->registrarSincronizada($data['uuid'], 'prefactura_guardar', $empresaId, null);

                return response()->json(['id' => null, 'ya_facturada' => true]);
            }
        } else {
            $prefactura = new Prefactura(['empresa_id' => $empresaId]);
        }

        $clienteId = $this->resolverClientePrefactura($empresaId, $data);

        $prefactura->fill([
            'empresa_id' => $empresaId,
            'cliente_id' => $clienteId,
            'vendedor_id' => $data['vendedor_id'] ?? auth()->id(),
            'cajero_id' => null,
            'observaciones' => $data['observaciones'] ?? '',
            'estado' => $data['estado'] ?? 'borrador',
        ])->save();

        $prefactura->productos()->delete();

        foreach ($data['items'] as $item) {
            $cantidad = (float) $item['cantidad'];
            $precio = (float) $item['precio'];

            $prefactura->productos()->create([
                'empresa_id' => $empresaId,
                'producto_id' => is_numeric($item['producto_id']) ? (int) $item['producto_id'] : 0,
                'descripcion_larga' => $item['nombre'],
                'cantidad' => $cantidad,
                'precio_unitario' => $precio,
                'subtotal' => round($precio * $cantidad, 2),
                'descuento' => (float) ($item['descuento'] ?? 0),
            ]);
        }

        $this->registrarSincronizada($data['uuid'], 'prefactura_guardar', $empresaId, $prefactura->id);

        return response()->json(['id' => $prefactura->id, 'ya_facturada' => false]);
    }

    /**
     * Borra en el servidor una prefactura que se borro offline en Turion --
     * sin esto, el siguiente "Sincronizar" la volvia a bajar (el droplet
     * nunca se entero de que se habia borrado localmente).
     */
    public function prefacturaBorrar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'uuid' => 'required|uuid',
            'servidor_id' => 'required|integer',
        ]);

        $empresaId = auth()->user()->getEmpresaActualId();

        if ($this->buscarSincronizada($data['uuid'])) {
            return response()->json(['ok' => true]);
        }

        $prefactura = Prefactura::where('id', $data['servidor_id'])->where('empresa_id', $empresaId)->first();

        if ($prefactura) {
            $prefactura->productos()->delete();
            $prefactura->delete();
        }

        $this->registrarSincronizada($data['uuid'], 'prefactura_borrar', $empresaId, null);

        return response()->json(['ok' => true]);
    }

    /**
     * Borra en el servidor una orden de taller que se borro offline en
     * Turion -- mismo motivo que prefacturaBorrar().
     */
    public function tallerBorrar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'uuid' => 'required|uuid',
            'servidor_id' => 'required|integer',
        ]);

        $empresaId = auth()->user()->getEmpresaActualId();

        if ($this->buscarSincronizada($data['uuid'])) {
            return response()->json(['ok' => true]);
        }

        TallerOrden::where('id', $data['servidor_id'])->where('empresa_id', $empresaId)->delete();

        $this->registrarSincronizada($data['uuid'], 'taller_borrar', $empresaId, null);

        return response()->json(['ok' => true]);
    }

    /**
     * Cancela en el servidor una reserva de hotel que se cancelo offline en
     * Turion -- mismo motivo que prefacturaBorrar(). No se borra (una
     * reserva cancelada sigue siendo historial util), se marca 'cancelada'
     * igual que HotelPanel::cancelarReserva() en linea.
     */
    public function hotelCancelar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'uuid' => 'required|uuid',
            'servidor_id' => 'required|integer',
        ]);

        $empresaId = auth()->user()->getEmpresaActualId();

        if ($this->buscarSincronizada($data['uuid'])) {
            return response()->json(['ok' => true]);
        }

        HotelReserva::where('id', $data['servidor_id'])->where('empresa_id', $empresaId)
            ->update(['estado' => 'cancelada']);

        $this->registrarSincronizada($data['uuid'], 'hotel_cancelar', $empresaId, null);

        return response()->json(['ok' => true]);
    }

    /**
     * Libera en el servidor una mesa que se libero offline en Turion.
     * A diferencia de taller/hotel, una orden de mesa no tiene su propio
     * servidor_id -- se identifica por mesa_id (mesas SI vienen en el
     * catalogo con el mismo id en ambos lados), buscando la orden
     * actualmente activa de esa mesa (mismo criterio de "activa" que usa
     * AgregarItemMesaService al agregar items).
     */
    public function mesaLiberar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'uuid' => 'required|uuid',
            'mesa_id' => 'required|integer',
        ]);

        $empresaId = auth()->user()->getEmpresaActualId();

        if ($this->buscarSincronizada($data['uuid'])) {
            return response()->json(['ok' => true]);
        }

        $mesa = Mesa::where('id', $data['mesa_id'])->where('empresa_id', $empresaId)->first();

        if ($mesa) {
            \App\Models\OrdenMesa::where('mesa_id', $mesa->id)
                ->where('empresa_id', $empresaId)
                ->whereIn('estado', ['abierta', 'en_preparacion'])
                ->update(['estado' => 'cancelada', 'cerrada_en' => now()]);

            $mesa->update(['estado' => 'libre']);
        }

        $this->registrarSincronizada($data['uuid'], 'mesa_liberar', $empresaId, null);

        return response()->json(['ok' => true]);
    }

    private function resolverClientePrefactura(int $empresaId, array $data): ?int
    {
        return ResolverActorService::resolverOCrear($empresaId, $data['cliente'] ?? [], $data['cliente_id'] ?? null);
    }

    /**
     * Sube un cliente (Actor) creado directo en Turion -- ver
     * ColaSincronizacion::encolarActorCreado(), llamado desde
     * CarritoVenta/HotelPanel justo despues de Actor::create(). Encuentra
     * o crea el Actor en el droplet (mismo resolver que usa
     * prefacturaGuardar()) y devuelve su id real para que Turion lo
     * guarde en actors.servidor_id.
     */
    public function actorCrear(Request $request): JsonResponse
    {
        $data = $request->validate([
            'uuid' => 'required|uuid',
            'actor_local_id' => 'required|integer',
            'identificacion' => 'nullable|string',
            'nombre' => 'required|string',
            'razon_social' => 'nullable|string',
            'tipo_documento_id' => 'nullable|integer',
            'telefono' => 'nullable|string',
            'email' => 'nullable|string',
            'direccion' => 'nullable|string',
            'departamento_id' => 'nullable|integer',
            'ciudad_id' => 'nullable|integer',
            'tipo_persona' => 'nullable|string',
            'regimen_tributario' => 'nullable|string',
            'responsable_iva' => 'nullable|boolean',
        ]);

        $empresaId = auth()->user()->getEmpresaActualId();

        if ($existente = $this->buscarSincronizada($data['uuid'])) {
            return response()->json(['id' => $existente->resultado_id]);
        }

        $id = ResolverActorService::resolverOCrear($empresaId, $data);

        $this->registrarSincronizada($data['uuid'], 'actor_crear', $empresaId, $id);

        return response()->json(['id' => $id]);
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
        $data = $request->validate(array_merge([
            'uuid' => 'required|uuid',
            'mesa_id' => 'required|integer',
            'medio_pago' => 'nullable|string|in:efectivo,transferencia',
        ], $this->reglasFacturarComunes()));

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
            $factura = app(FacturarVentaService::class)->facturar($carrito, array_merge([
                'empresa_id' => $empresaId,
                'user_id' => auth()->id(),
                'medio_pago' => $data['medio_pago'] ?? 'efectivo',
                'mesa_id' => (int) $data['mesa_id'],
            ], $this->opcionesFacturarComunes($data, $empresaId)));
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
        $data = $request->validate(array_merge([
            'uuid' => 'required|uuid',
            'taller_orden_id' => 'required|integer',
            'medio_pago' => 'nullable|string|in:efectivo,transferencia',
        ], $this->reglasFacturarComunes()));

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
        // queda a nombre de ese cliente en vez de Consumidor Final (a
        // menos que el que factura haya elegido otro cliente explicito).
        $clienteIdDefault = null;
        if ($orden->cliente_nombre) {
            $clienteIdDefault = Actor::where('empresa_id', $empresaId)
                ->whereRaw('LOWER(TRIM(nombre)) = ?', [mb_strtolower(trim($orden->cliente_nombre))])
                ->value('id');
        }

        $carrito = $tallerService->cargarCarritoDesdeOrden((int) $data['taller_orden_id'], $empresaId);

        if (empty($carrito)) {
            return response()->json(['message' => 'La orden de taller no tiene repuestos para facturar.'], 422);
        }

        try {
            $factura = app(FacturarVentaService::class)->facturar($carrito, array_merge([
                'empresa_id' => $empresaId,
                'user_id' => auth()->id(),
                'medio_pago' => $data['medio_pago'] ?? 'efectivo',
                'taller_orden_id' => (int) $data['taller_orden_id'],
            ], $this->opcionesFacturarComunes($data, $empresaId, $clienteIdDefault)));
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
        $data = $request->validate(array_merge([
            'uuid' => 'required|uuid',
            'hotel_reserva_id' => 'required|integer',
            'medio_pago' => 'nullable|string|in:efectivo,transferencia',
        ], $this->reglasFacturarComunes()));

        $empresaId = auth()->user()->getEmpresaActualId();

        if ($existente = $this->buscarSincronizada($data['uuid'])) {
            return $this->respuestaFactura($existente->resultado_id);
        }

        $reserva = HotelReserva::where('id', $data['hotel_reserva_id'])->where('empresa_id', $empresaId)->first();
        if (! $reserva) {
            return response()->json(['message' => 'La reserva no existe.'], 422);
        }

        $clienteIdDefault = null;
        if ($reserva->actor_id) {
            $clienteIdDefault = $reserva->actor_id;
        } elseif ($reserva->huesped_nombre) {
            $clienteIdDefault = Actor::where('empresa_id', $empresaId)
                ->whereRaw('LOWER(TRIM(nombre)) = ?', [mb_strtolower(trim($reserva->huesped_nombre))])
                ->value('id');
        }

        $carrito = $hotelService->cargarCarritoDesdeReserva((int) $data['hotel_reserva_id'], $empresaId);

        if (empty($carrito)) {
            return response()->json(['message' => 'La reserva no tiene nada para facturar.'], 422);
        }

        try {
            $factura = app(FacturarVentaService::class)->facturar($carrito, array_merge([
                'empresa_id' => $empresaId,
                'user_id' => auth()->id(),
                'medio_pago' => $data['medio_pago'] ?? 'efectivo',
                'hotel_reserva_id' => (int) $data['hotel_reserva_id'],
                'hotel_abono_monto' => (float) $reserva->abono_monto,
            ], $this->opcionesFacturarComunes($data, $empresaId, $clienteIdDefault)));
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $this->registrarSincronizada($data['uuid'], 'hotel_facturar', $empresaId, $factura->id);

        return $this->respuestaFactura($factura->id);
    }

    /**
     * Reglas de validacion comunes a los 4 endpoints de facturar: el set
     * completo que soporta FacturarVentaService::facturar(), para que
     * facturar en linea desde Turion (ver FacturarEnLineaService) tenga
     * la misma funcionalidad que facturar directo en el droplet -- cliente,
     * factura electronica, credito, domicilio, propina, vendedor manual.
     */
    private function reglasFacturarComunes(): array
    {
        return [
            'cliente_id' => 'nullable|integer',
            'vendedor_id' => 'nullable|integer',
            'tipo_factura' => 'nullable|string|in:salida,electronica',
            'tipo_pago' => 'nullable|string|in:contado,credito',
            'fecha_vencimiento' => 'nullable|date',
            'transferencia_obs' => 'nullable|string',
            'observaciones' => 'nullable|string',
            'tipo_pedido' => 'nullable|string|in:local,para_llevar,domicilio',
            'costo_empaque' => 'nullable|numeric|min:0',
            'cobro_domicilio' => 'nullable|string',
            'dom_nombre' => 'nullable|string',
            'dom_telefono' => 'nullable|string',
            'dom_direccion' => 'nullable|string',
            'dom_observaciones' => 'nullable|string',
            'dom_nit' => 'nullable|string',
            'dom_email' => 'nullable|string',
            'dom_razon_social' => 'nullable|string',
            'dom_costo_domicilio' => 'nullable|numeric|min:0',
            'dom_costo_desechables' => 'nullable|numeric|min:0',
            'propina_monto' => 'nullable|numeric|min:0',
        ];
    }

    /**
     * $clienteIdDefault: a que cliente facturar si el llamador no mando
     * "cliente_id" explicito (ej. el cliente ya vinculado a la orden de
     * taller/reserva de hotel). Si tipo_pago es "credito", el cupo
     * disponible se recalcula aqui mismo (dato financiero, no se confia
     * en lo que mande el cliente).
     */
    private function opcionesFacturarComunes(array $data, int $empresaId, ?int $clienteIdDefault = null): array
    {
        $clienteId = ! empty($data['cliente_id']) ? (int) $data['cliente_id'] : $clienteIdDefault;
        $tipoPago = $data['tipo_pago'] ?? 'contado';

        return [
            'cliente_id' => $clienteId,
            'vendedor_id' => $data['vendedor_id'] ?? auth()->id(),
            'tipo_factura' => $data['tipo_factura'] ?? 'salida',
            'tipo_pago' => $tipoPago,
            'fecha_vencimiento' => $data['fecha_vencimiento'] ?? null,
            'transferencia_obs' => $data['transferencia_obs'] ?? null,
            'observaciones' => $data['observaciones'] ?? null,
            'tipo_pedido' => $data['tipo_pedido'] ?? 'local',
            'costo_empaque' => (float) ($data['costo_empaque'] ?? 0),
            'cobro_domicilio' => $data['cobro_domicilio'] ?? null,
            'dom_nombre' => $data['dom_nombre'] ?? null,
            'dom_telefono' => $data['dom_telefono'] ?? null,
            'dom_direccion' => $data['dom_direccion'] ?? null,
            'dom_observaciones' => $data['dom_observaciones'] ?? null,
            'dom_nit' => $data['dom_nit'] ?? null,
            'dom_email' => $data['dom_email'] ?? null,
            'dom_razon_social' => $data['dom_razon_social'] ?? null,
            'dom_costo_domicilio' => (float) ($data['dom_costo_domicilio'] ?? 0),
            'dom_costo_desechables' => (float) ($data['dom_costo_desechables'] ?? 0),
            'propina_monto' => (float) ($data['propina_monto'] ?? 0),
            'cliente_credito_info' => $tipoPago === 'credito'
                ? FacturarVentaService::calcularCreditoCliente($empresaId, $clienteId)
                : null,
        ];
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
