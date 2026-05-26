<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class KardexController extends Controller
{

public function reporte(Request $request)
{
    $empresaId = auth()->user()->getEmpresaActualId();

    $productoId = $request->producto_id;
    $desde = $request->desde ? Carbon::parse($request->desde)->startOfDay() : null;
    $hasta = $request->hasta ? Carbon::parse($request->hasta)->endOfDay() : null;

    $query = DB::table('kardex as k')
    ->join('products as p', function($join) {
        $join->on('p.id_producto', '=', 'k.producto_id')
             ->on('p.empresa_id', '=', 'k.empresa_id');
    })
   ->select('k.*', 'p.descripcion_larga as producto_nombre')
    ->where('k.empresa_id', $empresaId)
    ->where('k.producto_id', $productoId)
    ->when($desde, fn ($q) => $q->where('k.created_at', '>=', $desde))
    ->when($hasta, fn ($q) => $q->where('k.created_at', '<=', $hasta))
    ->orderBy('k.created_at', 'asc');

    // 🔎 Filtros por fecha
    if ($desde) {
        $query->whereDate('created_at', '>=', $desde);
    }

    if ($hasta) {
        $query->whereDate('created_at', '<=', $hasta);
    }

    $movimientos = $query->get();

    // 🔥 SALDO ACUMULADO
    $saldo = 0;

    foreach ($movimientos as $m) {
        $saldo += $m->entrada;
        $saldo -= $m->salida;

        $m->saldo = $saldo;
    }

    return view('kardex.reporte', compact('movimientos'));
}

public function index(Request $request)
{
    $empresaId = auth()->user()->getEmpresaActualId();

    $codigo = $request->codigo;
    $desdeInput = $request->desde;
    $hastaInput = $request->hasta;
    $desde = $desdeInput ? Carbon::parse($desdeInput)->startOfDay() : null;
    $hasta = $hastaInput ? Carbon::parse($hastaInput)->endOfDay() : null;

    // 🚫 NO BUSCAR SI NO HAY FILTROS
    if (!$codigo || !$desdeInput || !$hastaInput) {
        return view('kardex.index', [
            'movimientos' => [],
            'empresa' => auth()->user(),
            'error' => 'Debe ingresar código y rango de fechas'
        ]);
    }

    // 🔥 Validar rango máximo 3 meses
    $diff = Carbon::parse($desdeInput)->diffInMonths($hastaInput);
    if ($diff > 3) {
        return back()->with('error', 'Máximo 3 meses de rango');
    }

    $query = DB::table('kardex as k')
    ->join('products as p', function($join) {
        $join->on('p.id_producto', '=', 'k.producto_id')
             ->on('p.empresa_id', '=', 'k.empresa_id');
    })
    ->select('k.*', 'p.descripcion_larga as producto_nombre')
    ->where('k.empresa_id', $empresaId)
    ->where('k.producto_id', $codigo)
    ->where('k.created_at', '>=', $desde)
    ->where('k.created_at', '<=', $hasta)
    ->orderBy('k.created_at', 'asc');

    $movimientos = $query->get();

    // 🔥 SALDO ACUMULADO DESDE CERO EN EL PERIODO
    $saldo = 0;

    foreach ($movimientos as $m) {
        $saldo += $m->entrada;
        $saldo -= $m->salida;
        $m->saldo = $saldo;
    }

    return view('kardex.index', [
        'movimientos' => $movimientos,
        'empresa' => auth()->user(),
        'error' => null
    ]);
}

public function documento(Request $request)
{
    $empresaId = auth()->user()->getEmpresaActualId();
    $tipo = $request->tipo;
    $ref  = $request->ref;

    if ($tipo == 'venta') {

    $data = DB::table('factura_detalles as d')

        // 🔥 UNIR FACTURA
        ->join('facturas as f', 'f.id', '=', 'd.factura_id')

        // 🔥 UNIR PRODUCTO CON EMPRESA CORRECTA
        ->join('products as p', function($join){
            $join->on('p.id_producto', '=', 'd.producto_id')
                 ->on('p.empresa_id', '=', 'f.empresa_id');
        })

        ->where('d.factura_id', $ref)
        ->where('f.empresa_id', $empresaId)

        ->select(
            'd.producto_id',
            'p.descripcion_larga as nombre',
            'd.cantidad',
            'p.precio_costo as costo',
            'd.precio',
            'p.iva_venta as iva',

            DB::raw('
                ROUND(
                    (
                        (
                            d.precio - (p.precio_costo * (1 + p.iva_venta/100))
                        ) / d.precio
                    ) * 100
                ,2) as utilidad
            ')
        )

        ->get();

    return response()->json($data);
}
if ($tipo == 'compra') {

    $data = DB::table('compra_detalles as d')

        // 🔥 unir compra (aquí está la empresa)
        ->join('compras as c', 'c.id', '=', 'd.compra_id')

        // 🔥 unir producto correctamente
        ->join('products as p', function($join){
            $join->on('p.id_producto', '=', 'd.product_id')
                 ->on('p.empresa_id', '=', 'c.empresa_id'); // 👈 CLAVE
        })

        ->where('d.compra_id', $ref)
        ->where('c.empresa_id', $empresaId)

        ->select(
            'd.product_id as producto_id',
            'p.descripcion_larga as nombre',
            'd.cantidad',

            'd.costo_unitario as costo',

            DB::raw('0 as precio'),

            'd.iva_pct as iva',

            DB::raw('0 as utilidad')
        )

        ->get();

    return response()->json($data);
}

if ($tipo == 'devolucion_compra') {

    $data = DB::table('devolucion_compra_detalles as d')

        // 🔥 unir cabecera para empresa
        ->join('devolucion_compras as dc', 'dc.id', '=', 'd.devolucion_compra_id')

        // 🔥 unir productos correctamente
        ->join('products as p', function($join){
            $join->on('p.id_producto', '=', 'd.product_id')
                 ->on('p.empresa_id', '=', 'dc.empresa_id');
        })

        ->where('d.devolucion_compra_id', $ref)
        ->where('dc.empresa_id', $empresaId)

        ->select(
            'd.product_id as producto_id',
            'p.descripcion_larga as nombre',
            'd.cantidad',

            // 🔥 COSTO REAL DEVUELTO
            'd.costo_unitario as costo',

            // ❌ NO hay precio de venta aquí
            DB::raw('0 as precio'),

            // 🔥 IVA correcto
            'd.porcentaje_impuesto as iva',

            // ❌ NO aplica utilidad en devolucion compra
            DB::raw('0 as utilidad')
        )

        ->get();

    return response()->json($data);
}
if ($tipo == 'devolucion_venta') {

    $data = DB::table('devolucion_detalles as d')

        // 🔥 unir cabecera para empresa
        ->join('devoluciones as dv', 'dv.id', '=', 'd.devolucion_id')

        // 🔥 unir productos correctamente
        ->join('products as p', function($join){
            $join->on('p.id_producto', '=', 'd.producto_id')
                 ->on('p.empresa_id', '=', 'dv.empresa_id');
        })

        ->where('d.devolucion_id', $ref)
        ->where('dv.empresa_id', $empresaId)

        ->select(
            'd.producto_id',
            'p.descripcion_larga as nombre',
            'd.cantidad',

            // 🔥 costo actual del producto
            'p.precio_costo as costo',

            // 🔥 precio al que se devolvió
            'd.precio',

            // 🔥 IVA correcto
            'p.iva_venta as iva',

            // 🔥 UTILIDAD REAL
            DB::raw('
                ROUND(
                    (
                        (
                            d.precio - (p.precio_costo * (1 + p.iva_venta/100))
                        ) / d.precio
                    ) * 100
                ,2) as utilidad
            ')
        )

        ->get();

    return response()->json($data);
}

if ($tipo == 'inventario_nuevo') {

    $data = DB::table('ajuste_inventario_detalles as d')

        ->join('ajustes_inventario as a', 'a.id', '=', 'd.ajuste_inventario_id')

        ->join('products as p', function($join){
            $join->on('p.id_producto', '=', 'd.producto_id')
                 ->on('p.empresa_id', '=', 'a.empresa_id');
        })

        ->where('d.ajuste_inventario_id', $ref)
        ->where('a.empresa_id', $empresaId)

        ->select(
            'd.producto_id',
            'p.descripcion_larga as nombre',

            DB::raw('d.cantidad_nueva as cantidad'),

            'p.precio_costo as costo',

            DB::raw('0 as precio'),

            'p.iva_compra as iva',

            DB::raw('0 as utilidad')
        )

        ->groupBy('d.id')

        ->get();

    return response()->json($data);
}

if ($tipo == 'ajuste_entrada' || $tipo == 'ajuste_salida') {

    $data = DB::table('ajuste_inventario_detalles as d')

        ->join('ajustes_inventario as a', 'a.id', '=', 'd.ajuste_inventario_id')

        ->join('products as p', function($join){
            $join->on('p.id_producto', '=', 'd.producto_id')
                 ->on('p.empresa_id', '=', 'a.empresa_id');
        })

        ->where('d.ajuste_inventario_id', $ref)
        ->where('a.empresa_id', $empresaId)

        ->select(
            'd.producto_id',
            'p.descripcion_larga as nombre',

            // 🔥 diferencia real
            DB::raw('d.diferencia as cantidad'),

            'p.precio_costo as costo',

            DB::raw('0 as precio'),

            'p.iva_compra as iva',

            DB::raw('0 as utilidad')
        )

        ->groupBy('d.id')

        ->get();

    return response()->json($data);
}

    return [];
}

}
