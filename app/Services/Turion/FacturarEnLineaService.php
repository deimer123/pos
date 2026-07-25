<?php

namespace App\Services\Turion;

/**
 * Factura llamando DIRECTO Y EN EL MOMENTO al droplet -- se usa desde
 * App\Livewire\CarritoVenta cuando esta corriendo en una terminal de
 * Turion (SQLite) CON conexion. La factura real (con numeracion fiscal)
 * la crea el droplet, nunca esta terminal; por eso el retorno es un
 * array (id/numero_visual/print_url), no un modelo Factura local.
 *
 * A diferencia de ColaSincronizacion::encolar() (que guarda para subir
 * despues con el boton "Subir"), esto espera la respuesta del droplet
 * ahi mismo -- si no hay conexion, ConectividadDroplet::llamar() lanza
 * una excepcion (el llamador debe haber verificado antes con
 * ConectividadDroplet::estaEnLinea() y no dejar llegar hasta aqui).
 *
 * @see App\Http\Controllers\PosSyncController que recibe estas mismas
 * rutas (compartidas con el flujo de "Subir" en cola).
 */
class FacturarEnLineaService
{
    /**
     * @param  array<int, array<string, mixed>>  $carrito  mismo formato que CarritoVenta::$carrito
     * @param  array<string, mixed>  $opciones  mismas claves que FacturarVentaService::facturar()
     * @return array{id: int, numero_visual: ?string, print_url: string}
     */
    public function facturar(array $carrito, array $opciones): array
    {
        [$ruta, $body] = $this->rutaYBody($carrito, $opciones);

        return ConectividadDroplet::llamar($ruta, $body);
    }

    private function rutaYBody(array $carrito, array $opciones): array
    {
        $comunes = [
            'cliente_id' => $opciones['cliente_id'] ?? null,
            'vendedor_id' => $opciones['vendedor_id'] ?? null,
            'tipo_factura' => $opciones['tipo_factura'] ?? 'salida',
            'tipo_pago' => $opciones['tipo_pago'] ?? 'contado',
            'medio_pago' => $opciones['medio_pago'] ?? null,
            'fecha_vencimiento' => $opciones['fecha_vencimiento'] ?? null,
            'transferencia_obs' => $opciones['transferencia_obs'] ?? null,
            'observaciones' => $opciones['observaciones'] ?? null,
            'tipo_pedido' => $opciones['tipo_pedido'] ?? 'local',
            'costo_empaque' => $opciones['costo_empaque'] ?? 0,
            'cobro_domicilio' => $opciones['cobro_domicilio'] ?? null,
            'dom_nombre' => $opciones['dom_nombre'] ?? null,
            'dom_telefono' => $opciones['dom_telefono'] ?? null,
            'dom_direccion' => $opciones['dom_direccion'] ?? null,
            'dom_observaciones' => $opciones['dom_observaciones'] ?? null,
            'dom_nit' => $opciones['dom_nit'] ?? null,
            'dom_email' => $opciones['dom_email'] ?? null,
            'dom_razon_social' => $opciones['dom_razon_social'] ?? null,
            'dom_costo_domicilio' => $opciones['dom_costo_domicilio'] ?? 0,
            'dom_costo_desechables' => $opciones['dom_costo_desechables'] ?? 0,
            'propina_monto' => $opciones['propina_monto'] ?? 0,
        ];

        // taller_orden_id/hotel_reserva_id que trae CarritoVenta son el id
        // LOCAL de Turion -- si la orden/reserva se creo offline, el
        // droplet tiene su PROPIA fila con un id distinto (asignado al
        // subir su "_crear"). Sin este mapeo se mandaria un id que en el
        // droplet no existe (o, peor, que ya es de otra orden/reserva).
        if (! empty($opciones['taller_orden_id'])) {
            $servidorId = ColaSincronizacion::servidorIdSincronizado('taller_crear', 'local_id', $opciones['taller_orden_id']);

            if (! $servidorId) {
                throw new \RuntimeException('Esta orden de taller todavia no se ha subido al droplet. Presiona "Sincronizar/Subir" antes de facturar.');
            }

            return ['/api/pairing/subir/taller/facturar', array_merge($comunes, [
                'taller_orden_id' => $servidorId,
            ])];
        }

        if (! empty($opciones['hotel_reserva_id'])) {
            $servidorId = ColaSincronizacion::servidorIdSincronizado('hotel_crear', 'local_id', $opciones['hotel_reserva_id']);

            if (! $servidorId) {
                throw new \RuntimeException('Esta reserva de hotel todavia no se ha subido al droplet. Presiona "Sincronizar/Subir" antes de facturar.');
            }

            return ['/api/pairing/subir/hotel/facturar', array_merge($comunes, [
                'hotel_reserva_id' => $servidorId,
            ])];
        }

        if (! empty($opciones['mesa_id'])) {
            return ['/api/pairing/subir/mesa/facturar', array_merge($comunes, [
                'mesa_id' => $opciones['mesa_id'],
            ])];
        }

        return ['/api/pairing/subir/venta', array_merge($comunes, [
            'carrito' => $carrito,
            'prefactura_servidor_id' => $opciones['prefactura_servidor_id'] ?? null,
        ])];
    }
}
