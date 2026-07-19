<?php

namespace App\Services\Hotel;

use App\Models\ConfiguracionEmpresa;
use App\Models\HotelReserva;
use App\Models\HotelReservaConsumo;
use App\Models\Product;
use App\Services\Turion\ColaSincronizacion;

/**
 * Logica de consumos de una reserva de hotel extraida de
 * App\Livewire\CarritoVenta::sincronizarCarritoConHotelReserva() /
 * cargarHotelReserva() (extraccion pura, sin cambiar el comportamiento
 * online), para reutilizarla desde la sincronizacion de pedidos guardados
 * offline. Solo cubre reservas que ya existian -- crear una reserva nueva
 * estando offline se encola por separado (ver HotelReserva::booted()).
 */
class GuardarReservaService
{
    /**
     * Mismo comportamiento que CarritoVenta::sincronizarCarritoConHotelReserva():
     * borra los consumos actuales de la reserva y los vuelve a crear desde
     * el carrito completo (no es incremental). El item sintetico de
     * hospedaje ("hotel-reserva-{id}") no es un consumo, se ignora aqui.
     *
     * @param  array<int, array<string, mixed>>  $carrito
     */
    public function sincronizarConsumos(int $reservaId, int $empresaId, array $carrito): HotelReserva
    {
        $reserva = HotelReserva::where('id', $reservaId)
            ->where('empresa_id', $empresaId)
            ->firstOrFail();

        HotelReservaConsumo::where('reserva_id', $reserva->id)->delete();

        foreach ($carrito as $item) {
            if (str_starts_with((string) ($item['id_producto'] ?? ''), 'hotel-reserva-')) {
                continue;
            }

            $precio = (float) ($item['nuevo_precio'] ?? $item['precio'] ?? 0);
            $cant = (float) ($item['cantidad'] ?? 1);

            HotelReservaConsumo::create([
                'reserva_id' => $reserva->id,
                'producto_id' => is_numeric($item['id_producto']) ? (int) $item['id_producto'] : null,
                'descripcion' => $item['nombre'] ?? 'Sin descripción',
                'cantidad' => $cant,
                'precio_unitario' => $precio,
                'subtotal' => round($precio * $cant, 2),
            ]);
        }

        $itemsParaSubir = array_values(array_filter($carrito, fn ($item) => ! str_starts_with((string) ($item['id_producto'] ?? ''), 'hotel-reserva-')));

        ColaSincronizacion::encolar('hotel_item', [
            'hotel_reserva_id' => $reservaId,
            'items' => array_map(fn ($item) => [
                'id_producto' => $item['id_producto'],
                'nombre' => $item['nombre'] ?? 'Sin descripción',
                'cantidad' => (float) ($item['cantidad'] ?? 1),
                'precio' => (float) ($item['nuevo_precio'] ?? $item['precio'] ?? 0),
            ], $itemsParaSubir),
        ]);

        return $reserva;
    }

    /**
     * Reconstruye un array con la misma forma de CarritoVenta::$carrito a
     * partir de la reserva actual (hospedaje + consumos), igual que
     * CarritoVenta::cargarHotelReserva(), para poder facturar sin depender
     * de que el cliente offline mande los precios.
     */
    public function cargarCarritoDesdeReserva(int $reservaId, int $empresaId): array
    {
        $reserva = HotelReserva::where('id', $reservaId)
            ->where('empresa_id', $empresaId)
            ->with(['habitacion', 'consumos'])
            ->first();

        if (! $reserva) {
            return [];
        }

        $carrito = [];

        $horaInicioDia = (string) (ConfiguracionEmpresa::where('empresa_id', $empresaId)->value('hotel_hora_inicio_dia') ?? '14:00:00');
        $noches = $reserva->fecha_checkout ? $reserva->numero_noches : $reserva->calcularNochesAbiertas($horaInicioDia);
        $totalHospedaje = round((float) $reserva->precio_noche * $noches, 2);

        $numeroHabitacion = $reserva->habitacion->numero ?? '?';
        $descripcion = "Hospedaje habitación {$numeroHabitacion} · {$noches} noche(s) x {$reserva->numero_personas} persona(s)";
        $uuid = 'hotel-reserva-' . $reserva->id;

        $carrito[$uuid] = [
            'uuid' => $uuid,
            'id_producto' => $uuid,
            'nombre' => $descripcion,
            'cantidad' => 1,
            'precio' => $totalHospedaje,
            'nuevo_precio' => $totalHospedaje,
            'descuento' => 0,
            'id_unidad_de_medida' => 1,
            'vende_por' => 'unidad',
            'permite_fraccion' => false,
            'permite_decimal' => false,
        ];

        foreach ($reserva->consumos as $consumo) {
            $precio = (float) $consumo->precio_unitario;
            $cant = (float) $consumo->cantidad;

            if ($consumo->producto_id) {
                $producto = Product::where('id_producto', $consumo->producto_id)
                    ->where('empresa_id', $empresaId)
                    ->first();

                if ($producto) {
                    $key = (string) $consumo->producto_id;
                    $carrito[$key] = [
                        'uuid' => $key,
                        'id_producto' => $producto->id_producto,
                        'nombre' => $consumo->descripcion ?: $producto->descripcion_larga,
                        'cantidad' => $cant,
                        'precio' => $precio,
                        'nuevo_precio' => $precio,
                        'descuento' => 0,
                        'id_unidad_de_medida' => (int) ($producto->id_unidad_de_medida ?? 1),
                        'vende_por' => (string) ($producto->vende_por ?? 'unidad'),
                        'permite_fraccion' => (bool) ($producto->permite_fraccion ?? false),
                        'permite_decimal' => $producto->permiteCantidadDecimal(),
                    ];
                    continue;
                }
            }

            $uuidConsumo = 'hotel-consumo-' . $consumo->id;
            $carrito[$uuidConsumo] = [
                'uuid' => $uuidConsumo,
                'id_producto' => $uuidConsumo,
                'nombre' => $consumo->descripcion ?: 'Consumo',
                'cantidad' => $cant,
                'precio' => $precio,
                'nuevo_precio' => $precio,
                'descuento' => 0,
                'id_unidad_de_medida' => 1,
                'vende_por' => 'unidad',
                'permite_fraccion' => false,
                'permite_decimal' => false,
            ];
        }

        return $carrito;
    }
}
