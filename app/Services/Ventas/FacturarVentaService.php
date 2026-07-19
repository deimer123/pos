<?php

namespace App\Services\Ventas;

use App\Models\Actor;
use App\Models\ConfiguracionEmpresa;
use App\Models\Factura;
use App\Models\HotelReserva;
use App\Models\Mesa;
use App\Models\OrdenMesa;
use App\Models\Product;
use App\Models\Receta;
use App\Models\TallerOrden;
use App\Services\Factus\FactusInvoiceService;
use Illuminate\Support\Facades\DB;

/**
 * Logica central de "facturar" extraida de
 * App\Livewire\CarritoVenta::facturarConfirmada()/facturarEImprimir()
 * (extraccion pura, sin cambiar comportamiento) para tenerla separada
 * del componente Livewire.
 */
class FacturarVentaService
{
    /**
     * @param  array<int, array<string, mixed>>  $carrito  mismo formato que CarritoVenta::$carrito
     * @param  array<string, mixed>  $opciones  ver claves usadas abajo
     */
    public function facturar(array $carrito, array $opciones): Factura
    {
        $empresaId = (int) $opciones['empresa_id'];

        DB::beginTransaction();

        try {
            $clienteId = $opciones['cliente_id'] ?? $this->getConsumidorFinalId($empresaId);
            if (! $clienteId) {
                throw new \Exception('Falta CONSUMIDOR FINAL en esta empresa.');
            }

            $this->validarStockCarrito($carrito, $empresaId);
            $this->validarDescuentoCarrito($carrito, $empresaId);

            $tipoFactura = $opciones['tipo_factura'] ?? 'salida';
            $tipoPago = $opciones['tipo_pago'] ?? 'contado';
            $medioPago = $tipoPago === 'contado' ? ($opciones['medio_pago'] ?? 'efectivo') : null;
            $vencRaw = $opciones['fecha_vencimiento'] ?? null;
            $tipoPedido = $opciones['tipo_pedido'] ?? 'local';
            $costoEmpaque = (float) ($opciones['costo_empaque'] ?? 0);
            $cobroDomicilio = $opciones['cobro_domicilio'] ?? null;

            if ($tipoFactura === 'electronica' && ! $this->facturacionElectronicaDisponible($empresaId)) {
                throw new \Exception('La facturacion electronica no esta activa o no tiene rango Factus configurado para esta empresa.');
            }

            $transferObs = ($tipoPago === 'contado' && $medioPago === 'transferencia')
                ? trim((string) ($opciones['transferencia_obs'] ?? ''))
                : null;

            $obs = trim((string) ($opciones['observaciones'] ?? ''));

            $factura = Factura::create([
                'empresa_id' => $empresaId,
                'cliente_id' => $clienteId,
                'user_id' => $opciones['user_id'],
                'vendedor_id' => $opciones['vendedor_id'] ?? $opciones['user_id'],
                'cajero_id' => $opciones['user_id'],
                'tipo_factura' => $tipoFactura,
                'factus_reference_code' => null,
                'factus_bill_id' => null,
                'factus_number' => null,
                'factus_cufe' => null,
                'factus_status' => null,
                'factus_response' => null,
                'factus_validated_at' => null,
                'tipo_pago' => $tipoPago,
                'medio_pago' => $medioPago,
                'fecha' => now(),
                'fecha_compra' => now(),
                'fecha_pago' => null,
                'fecha_vencimiento' => ($tipoPago === 'credito' && $vencRaw)
                    ? \Carbon\Carbon::parse($vencRaw)->toDateString()
                    : null,
                'total' => 0,
                'saldo' => 0,
                'estado_pago' => 'pendiente',
                'observaciones' => $obs,
                'transferencia_obs' => $transferObs,
                'tipo_pedido' => $tipoPedido,
                'costo_empaque' => $costoEmpaque,
                'dom_costo_domicilio' => $tipoPedido === 'domicilio' ? (float) ($opciones['dom_costo_domicilio'] ?? 0) : 0,
                'dom_costo_desechables' => $tipoPedido === 'domicilio' ? (float) ($opciones['dom_costo_desechables'] ?? 0) : ($tipoPedido === 'para_llevar' ? $costoEmpaque : 0),
                'cobro_domicilio' => $cobroDomicilio,
                'dom_nombre' => $opciones['dom_nombre'] ?? null,
                'dom_telefono' => $opciones['dom_telefono'] ?? null,
                'dom_direccion' => $opciones['dom_direccion'] ?? null,
                'dom_observaciones' => $opciones['dom_observaciones'] ?? null,
                'dom_nit' => $opciones['dom_nit'] ?? null,
                'dom_email' => $opciones['dom_email'] ?? null,
                'dom_razon_social' => $opciones['dom_razon_social'] ?? null,
            ]);

            $hotelReservaId = $opciones['hotel_reserva_id'] ?? null;
            $hotelAbonoMonto = (float) ($opciones['hotel_abono_monto'] ?? 0);

            foreach ($carrito as $item) {
                $precio = (float) ($item['nuevo_precio'] ?? $item['precio'] ?? 0);
                $cant = $this->normalizarCantidad($item['cantidad'] ?? 1, $this->permiteCantidadDecimal($item));
                $sub = round($precio * $cant, 2);

                $idFacturable = $this->idProductoFacturable($item['id_producto']);

                $producto = Product::where('empresa_id', $empresaId)
                    ->where('id_producto', $idFacturable)
                    ->lockForUpdate()
                    ->first();

                $datosServicio = $this->datosServicioFactura($producto);

                $this->crearDetalleFactura($factura, $item, (string) $idFacturable, $datosServicio, $precio, $cant, $sub, $hotelReservaId, $hotelAbonoMonto);

                if ($producto && $producto->tipo_producto !== 'servicio' && (string) $producto->id_producto !== '10001') {
                    $stockAnterior = (float) $producto->existencias;

                    guardarKardex($item['id_producto'], 'venta', $cant, $empresaId, $factura->id, $stockAnterior);

                    $receta = Receta::where('empresa_id', $empresaId)
                        ->where('product_id', $producto->id)
                        ->where('activo', true)
                        ->with('items.ingrediente')
                        ->first();

                    if (! $receta) {
                        $producto->existencias = $stockAnterior - $cant;
                        $producto->save();
                    }

                    if ($receta && $receta->items->isNotEmpty()) {
                        $rendimiento = (float) $receta->rendimiento ?: 1;
                        foreach ($receta->items as $recetaItem) {
                            $ingrediente = $recetaItem->ingrediente;
                            if (! $ingrediente) {
                                continue;
                            }
                            $cantBase = ((float) $recetaItem->cantidad / $rendimiento) * $cant;
                            $merma = (float) $recetaItem->merma;
                            $cantConMerma = $merma > 0 ? $cantBase * (1 + $merma / 100) : $cantBase;
                            $stockIngAnterior = (float) $ingrediente->existencias;
                            guardarKardex($ingrediente->id_producto, 'venta', $cantConMerma, $empresaId, $factura->id, $stockIngAnterior);
                            $ingrediente->existencias = $stockIngAnterior - $cantConMerma;
                            $ingrediente->save();
                        }
                    }
                }
            }

            if ($costoEmpaque > 0) {
                $label = match ($tipoPedido) {
                    'domicilio' => 'Domicilio + desechables',
                    'para_llevar' => 'Empaque / desechables',
                    default => 'Empaque',
                };
                $factura->detalles()->create([
                    'producto_id' => 0,
                    'descripcion_larga' => $label,
                    'cantidad' => 1,
                    'precio' => $costoEmpaque,
                    'subtotal' => $costoEmpaque,
                    'descuento' => 0,
                ]);
            }

            $factura->recalcularTotales();

            $this->finalizarPagoFactura(
                $factura,
                $tipoPago,
                $medioPago,
                $transferObs,
                $vencRaw,
                $opciones['user_id'],
                $opciones['cliente_credito_info'] ?? null,
                $hotelReservaId,
                $hotelAbonoMonto,
            );

            $this->validarFacturaElectronicaConFactus($factura);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        // ===== Post-commit: vincular con mesa/taller/hotel (igual que el
        // flujo online: fuera de la transaccion de la factura) =====

        if (! empty($opciones['taller_orden_id'])) {
            $this->vincularFacturaTaller($opciones['taller_orden_id'], $empresaId, $factura->id);
        }

        if (! empty($opciones['hotel_reserva_id'])) {
            $this->vincularFacturaHotel($opciones['hotel_reserva_id'], $empresaId, $factura->id);
        }

        if (! empty($opciones['mesa_id'])) {
            $this->liberarMesaSiCorresponde($opciones['mesa_id']);
        }

        return $factura;
    }

    public function getConsumidorFinalId(int $empresaId): ?int
    {
        return Actor::where('empresa_id', $empresaId)
            ->where('nombre', 'CONSUMIDOR FINAL')
            ->value('id');
    }

    public function facturacionElectronicaDisponible(int $empresaId): bool
    {
        return ConfiguracionEmpresa::query()
            ->where('empresa_id', $empresaId)
            ->where('factus_enabled', true)
            ->whereNotNull('factus_numbering_range_id')
            ->whereNotNull('nit')
            ->where('nit', '!=', '')
            ->whereNotNull('prefijo')
            ->where('prefijo', '!=', '')
            ->whereNotNull('rango_desde')
            ->whereNotNull('rango_hasta')
            ->whereNotNull('rango_actual')
            ->whereNotNull('numero_resolucion')
            ->where('numero_resolucion', '!=', '')
            ->whereNotNull('fecha_inicio')
            ->whereNotNull('fecha_fin')
            ->exists();
    }

    /**
     * Ultima linea de defensa del limite de descuento (ConfiguracionEmpresa::
     * descuento_maximo_permitido), replicando App\Livewire\CarritoVenta::
     * descuentoMaximoPermitido()/clampDescuento().
     */
    private function validarDescuentoCarrito(array $carrito, int $empresaId): void
    {
        if (auth()->user()?->hasRole('admin_empresa')) {
            return; // sin limite
        }

        $max = ConfiguracionEmpresa::where('empresa_id', $empresaId)->value('descuento_maximo_permitido');
        $max = $max === null ? 100.0 : (float) $max;

        foreach ($carrito as $item) {
            $descuento = (float) ($item['descuento'] ?? 0);

            if ($descuento < -$max) {
                $nombre = $item['nombre'] ?? ($item['id_producto'] ?? '');
                throw new \Exception("El descuento máximo permitido es {$max}%".($nombre !== '' ? " (\"{$nombre}\")." : '.'));
            }
        }
    }

    private function validarStockCarrito(array $carrito, int $empresaId): void
    {
        $permiteNegativo = (bool) ConfiguracionEmpresa::where('empresa_id', $empresaId)->value('permite_stock_negativo');

        foreach ($carrito as $item) {
            $prod = null;

            if (is_numeric($item['id_producto'])) {
                $prod = Product::where('empresa_id', $empresaId)
                    ->where('id_producto', $item['id_producto'])
                    ->lockForUpdate()
                    ->first();

                if (! $prod) {
                    throw new \Exception("Producto {$item['id_producto']} no existe.");
                }
            }

            $cant = $this->normalizarCantidad($item['cantidad'] ?? 1, $this->permiteCantidadDecimal($item));
            if ($cant <= 0) {
                throw new \Exception("Cantidad invalida en {$item['id_producto']}.");
            }

            if (! $permiteNegativo && $prod && $prod->tipo_producto !== 'servicio' && $cant > (float) $prod->existencias) {
                throw new \Exception("Stock insuficiente para \"{$prod->descripcion_larga}\": disponible {$prod->existencias}, solicitado {$cant}.");
            }
        }
    }

    private function permiteCantidadDecimal(array $item): bool
    {
        if (array_key_exists('permite_decimal', $item)) {
            return (bool) $item['permite_decimal'];
        }

        $vendePor = strtolower(trim((string) ($item['vende_por'] ?? '')));
        $permiteFraccion = (bool) ($item['permite_fraccion'] ?? false);

        if (in_array($vendePor, ['peso', 'porcion', 'litro', 'metro', 'hora'], true)) {
            return true;
        }

        if ($permiteFraccion) {
            return true;
        }

        return (int) ($item['id_unidad_de_medida'] ?? 1) !== 1;
    }

    private function normalizarCantidad($cantidad, bool $permiteDecimal = true): float
    {
        $valor = (float) str_replace(',', '.', (string) ($cantidad ?? 1));

        if ($valor <= 0) {
            return 1.0;
        }

        if (! $permiteDecimal) {
            return (float) max(1, (int) floor($valor));
        }

        return round($valor, 2);
    }

    private function idProductoFacturable($idProducto): int
    {
        return is_numeric($idProducto) ? (int) $idProducto : 0;
    }

    private function crearDetalleFactura(
        Factura $factura,
        array $item,
        string $idFacturable,
        array $datosServicio,
        float $precio,
        float $cant,
        float $sub,
        ?int $hotelReservaId,
        float $hotelAbonoMonto,
    ): void {
        $esHospedaje = str_starts_with((string) $item['id_producto'], 'hotel-reserva-');

        if ($esHospedaje && $hotelReservaId && $hotelAbonoMonto > 0) {
            $abonoAplicado = min($hotelAbonoMonto, $sub);
            $subRestante = round($sub - $abonoAplicado, 2);

            if ($subRestante > 0) {
                $factura->detalles()->create([
                    'producto_id' => $idFacturable,
                    'descripcion_larga' => $item['nombre'],
                    'cantidad' => $cant,
                    'precio' => round($subRestante / $cant, 2),
                    'subtotal' => $subRestante,
                    'descuento' => (float) ($item['descuento'] ?? 0),
                    'tipo_servicio' => $datosServicio['tipo_servicio'],
                    'porcentaje_empresa' => $datosServicio['porcentaje_empresa'],
                    'mecanico_id' => $datosServicio['mecanico_id'] ?? null,
                ]);
            }

            $factura->detalles()->create([
                'producto_id' => 0,
                'descripcion_larga' => 'Abono ya recibido al reservar (' . $item['nombre'] . ')',
                'cantidad' => 1,
                'precio' => $abonoAplicado,
                'subtotal' => $abonoAplicado,
                'descuento' => 0,
                'tipo_servicio' => null,
                'porcentaje_empresa' => null,
                'mecanico_id' => null,
            ]);

            return;
        }

        $factura->detalles()->create([
            'producto_id' => $idFacturable,
            'descripcion_larga' => $item['nombre'],
            'cantidad' => $cant,
            'subtotal' => $sub,
            'precio' => $precio,
            'descuento' => (float) ($item['descuento'] ?? 0),
            'tipo_servicio' => $datosServicio['tipo_servicio'],
            'porcentaje_empresa' => $datosServicio['porcentaje_empresa'],
            'mecanico_id' => $datosServicio['mecanico_id'] ?? null,
        ]);
    }

    private function datosServicioFactura(?Product $producto): array
    {
        if (! $producto || $producto->tipo_producto !== 'servicio' || ! $producto->tipo_servicio) {
            return ['tipo_servicio' => null, 'porcentaje_empresa' => null, 'mecanico_id' => null];
        }

        return [
            'tipo_servicio' => $producto->tipo_servicio,
            'porcentaje_empresa' => (float) ($producto->porcentaje_empresa ?? 0),
            'mecanico_id' => $producto->tipo_servicio === 'propio' ? $producto->mecanico_id : null,
        ];
    }

    private function finalizarPagoFactura(
        Factura $factura,
        string $tipoPago,
        ?string $medioPago,
        ?string $transferObs,
        ?string $vencRaw,
        int $userId,
        ?array $clienteCreditoInfo,
        ?int $hotelReservaId,
        float $hotelAbonoMonto,
    ): void {
        if ($tipoPago === 'credito') {
            $info = $clienteCreditoInfo ?? [];

            if (! ($info['permite'] ?? false)) {
                throw new \Exception('El cliente no tiene crédito habilitado.');
            }
            if ($factura->total > (float) ($info['cupo_disponible'] ?? 0)) {
                throw new \Exception('Cupo insuficiente para otorgar este crédito.');
            }

            $dias = (int) ($info['dias'] ?? 0);

            $factura->saldo = $factura->total;
            $factura->estado_pago = 'pendiente';
            $factura->fecha_vencimiento = $vencRaw
                ? \Carbon\Carbon::parse($vencRaw)->toDateString()
                : now()->addDays($dias)->toDateString();

            $factura->save();

            return;
        }

        $nota = ($medioPago === 'transferencia' && $transferObs)
            ? ('Transferencia: ' . $transferObs)
            : 'Pago contado';

        if ($hotelReservaId && $hotelAbonoMonto > 0) {
            $nota .= ' (incluye abono de $' . number_format($hotelAbonoMonto, 0, ',', '.') . ' ya recibido al reservar)';
        }

        $factura->registrarAbono(
            monto: (float) $factura->total,
            medio: $medioPago ?? 'efectivo',
            nota: $nota,
            userId: $userId,
            transferenciaObs: $transferObs
        );
    }

    private function validarFacturaElectronicaConFactus(Factura $factura): void
    {
        if ($factura->tipo_factura !== 'electronica') {
            return;
        }

        $factura->load(['cliente.ciudad', 'detalles']);

        app(FactusInvoiceService::class)->validate($factura);

        $factura->refresh();

        if (blank($factura->factus_number) || blank($factura->factus_cufe) || $factura->factus_status !== 'validada') {
            throw new \RuntimeException('Factus no valido la factura electronica. No se guardo la venta.');
        }
    }

    private function vincularFacturaTaller(int $tallerOrdenId, int $empresaId, int $facturaId): void
    {
        TallerOrden::where('id', $tallerOrdenId)
            ->where('empresa_id', $empresaId)
            ->update([
                'factura_id' => $facturaId,
                'estado' => 'entregado',
                'entregado_at' => now(),
            ]);
    }

    private function vincularFacturaHotel(int $hotelReservaId, int $empresaId, int $facturaId): void
    {
        HotelReserva::where('id', $hotelReservaId)
            ->where('empresa_id', $empresaId)
            ->update([
                'factura_id' => $facturaId,
                'estado' => 'checkout',
                'checkout_real_at' => now(),
            ]);
    }

    private function liberarMesaSiCorresponde(int $mesaId): void
    {
        OrdenMesa::where('mesa_id', $mesaId)
            ->whereIn('estado', ['abierta', 'en_preparacion'])
            ->update(['estado' => 'facturada', 'cerrada_en' => now()]);

        $cuentasEnEspera = OrdenMesa::where('mesa_id', $mesaId)
            ->where('estado', 'lista')
            ->count();

        if ($cuentasEnEspera === 0) {
            Mesa::where('id', $mesaId)->update(['estado' => 'libre']);
        }
    }
}
