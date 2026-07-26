<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HotelReserva;
use App\Models\PairingCode;
use App\Models\Prefactura;
use App\Models\TallerOrden;
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

    /**
     * Ordenes de taller activas (no entregadas ni canceladas) de la
     * empresa, con sus repuestos -- para que Turion las baje al
     * sincronizar (ver TallerSyncPull). Sin esto, una orden creada
     * directo en el droplet (u otra terminal) nunca aparecia en Turion.
     */
    public function ordenesTaller(Request $request): JsonResponse
    {
        $empresaId = $request->user()->getEmpresaActualId();

        $ordenes = TallerOrden::with('repuestos')
            ->where('empresa_id', $empresaId)
            ->whereNotIn('estado', ['entregado', 'cancelado'])
            ->get();

        return response()->json([
            'ordenes' => $ordenes->map(fn (TallerOrden $o) => [
                'id' => $o->id,
                'cliente_id' => $o->cliente_id,
                'cliente_nombre' => $o->cliente_nombre,
                'cliente_telefono' => $o->cliente_telefono,
                'placa' => $o->placa,
                'marca' => $o->marca,
                'modelo' => $o->modelo,
                'color' => $o->color,
                'km_ingreso' => $o->km_ingreso,
                'diagnostico' => $o->diagnostico,
                'observaciones' => $o->observaciones,
                'estado' => $o->estado,
                'fecha_entrega_estimada' => $o->fecha_entrega_estimada?->toDateTimeString(),
                'items' => $o->repuestos->map(fn ($item) => [
                    'producto_id' => $item->producto_id,
                    'nombre' => $item->descripcion,
                    'cantidad' => (float) $item->cantidad,
                    'precio' => (float) $item->precio_unitario,
                ])->values(),
            ])->values(),
        ]);
    }

    /**
     * Reservas de hotel activas (no checkout ni canceladas) de la empresa,
     * con sus consumos -- para que Turion las baje al sincronizar (ver
     * HotelSyncPull). Mismo problema y misma solucion que ordenesTaller().
     */
    public function reservasHotel(Request $request): JsonResponse
    {
        $empresaId = $request->user()->getEmpresaActualId();

        $reservas = HotelReserva::with('consumos')
            ->where('empresa_id', $empresaId)
            ->whereNotIn('estado', ['checkout', 'cancelada'])
            ->get();

        return response()->json([
            'reservas' => $reservas->map(fn (HotelReserva $r) => [
                'id' => $r->id,
                'habitacion_id' => $r->habitacion_id,
                'actor_id' => $r->actor_id,
                'numero_personas' => $r->numero_personas,
                'climatizacion' => $r->climatizacion,
                'huesped_nombre' => $r->huesped_nombre,
                'huesped_telefono' => $r->huesped_telefono,
                'huesped_documento' => $r->huesped_documento,
                'fecha_checkin' => $r->fecha_checkin?->toDateString(),
                'fecha_checkout' => $r->fecha_checkout?->toDateString(),
                'checkin_real_at' => $r->checkin_real_at?->toDateTimeString(),
                'precio_noche' => (float) $r->precio_noche,
                'abono_monto' => (float) $r->abono_monto,
                'abono_medio_pago' => $r->abono_medio_pago,
                'estado' => $r->estado,
                'observaciones' => $r->observaciones,
                'items' => $r->consumos->map(fn ($item) => [
                    'producto_id' => $item->producto_id,
                    'nombre' => $item->descripcion,
                    'cantidad' => (float) $item->cantidad,
                    'precio' => (float) $item->precio_unitario,
                ])->values(),
            ])->values(),
        ]);
    }
}
