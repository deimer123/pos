<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\AjusteInventario;
use App\Models\AjusteInventarioDetalle;
use Illuminate\Support\Facades\DB;

use Barryvdh\DomPDF\Facade\Pdf;

class InventarioController extends Controller
{
    public function buscar($codigo)
{
    $empresaId = auth()->check() 
        ? auth()->user()->getEmpresaActualId() 
        : 1;

    $codigo = trim($codigo);
    $codigo = str_replace(' ', '', $codigo);

   // 🔥 1. BUSCAR POR CÓDIGO PRINCIPAL (EXACTO)
    $producto = Product::where('empresa_id', $empresaId)
        ->where('id_producto', $codigo)
        ->first();

    // 🔥 2. SI NO EXISTE → BUSCAR EN CÓDIGOS ALTERNOS
    if (!$producto) {

        $alterno = DB::table('alternate_codes')
            ->where('empresa_id', $empresaId)
            ->where('code', $codigo) // ✅ EXACTO
            ->first();

        if ($alterno) {
            // 🔥 TRAER EL PRODUCTO REAL
            $producto = Product::where('id', $alterno->product_id)
                ->where('empresa_id', $empresaId)
                ->first();
        }
    }

    if (!$producto) {
        return response()->json(['error' => 'Producto no encontrado'], 404);
    }

    return response()->json([
        'id' => $producto->id_producto,
        'nombre' => $producto->descripcion_larga,
        'stock' => $producto->existencias ?? 0,
    ]);
}


public function buscarLista(Request $request)
{
    $q = trim($request->input('q', ''));

    $query = DB::table('products')
        ->where('empresa_id', auth()->user()->getEmpresaActualId());

    if ($q) {
        $palabras = explode(' ', $q);

        foreach ($palabras as $palabra) {
            $query->where(function($qry) use ($palabra) {
                $qry->whereRaw('LOWER(descripcion_larga) LIKE ?', ["%".strtolower($palabra)."%"])
                    ->orWhereRaw('LOWER(id_producto) LIKE ?', ["%".strtolower($palabra)."%"]);
            });
        }
    }

    return response()->json($query->limit(50)->get(['id_producto', 'descripcion_larga', 'existencias']));
}


public function guardar(Request $request)
{
    
    if (!$request->items || !is_array($request->items)) {
        return response()->json(['error' => 'Items inválidos'], 400);
    }

    // 🔥 SI EXISTE → EDITAR
    if ($request->id) {

        $ajuste = AjusteInventario::where('empresa_id', auth()->user()->getEmpresaActualId())
            ->find($request->id);

        if (!$ajuste) {
            return response()->json(['error' => 'Borrador no encontrado'], 404);
        }

        // 🔥 actualizar datos
        $ajuste->tipo = $request->tipo;
        $ajuste->observacion = $request->observacion;
        $ajuste->save();

        // 🔥 borrar detalles anteriores
        AjusteInventarioDetalle::where('ajuste_inventario_id', $ajuste->id)->delete();

    } else {

        // 🔥 crear nuevo
        $ajuste = AjusteInventario::create([
            'empresa_id' => auth()->user()->getEmpresaActualId(),
            'usuario_id' => auth()->id(),
            'tipo' => $request->tipo,
            'observacion' => $request->observacion,
            'estado' => 'borrador'
        ]);
    }

    // 🔥 insertar nuevos detalles
    foreach ($request->items as $item) {

        $producto = Product::where('empresa_id', auth()->user()->getEmpresaActualId())
            ->where('id_producto', $item['codigo'])
            ->first();

        if (!$producto) continue;

        $anterior = $producto->existencias ?? 0;

        AjusteInventarioDetalle::create([
            'ajuste_inventario_id' => $ajuste->id,
            'producto_id' => $producto->id_producto,
            'cantidad_anterior' => $anterior,
            'cantidad_nueva' => $item['cantidad'],
            'diferencia' => $item['cantidad'] - $anterior
        ]);
    }

    return response()->json([
        'ok' => true,
        'id' => $ajuste->id,
    ]);
}

public function aplicar(Request $request)
{
    try {

        // 🔥 OBTENER DATA JSON
        $data = $request->json()->all();

        $tipo = $data['tipo'] ?? null;
        $items = $data['items'] ?? [];
        $observacion = !empty($data['observacion']) ? $data['observacion'] : 'Sin observación';

        if (!$tipo) {
            return response('Tipo requerido', 400);
        }

        if (count($items) === 0) {
            return response('No hay productos', 400);
        }

        $empresaId = auth()->user()->getEmpresaActualId();

        DB::beginTransaction();

        // 🔥 1. CREAR ENCABEZADO
        $ajusteId = DB::table('ajustes_inventario')->insertGetId([
            'empresa_id' => $empresaId,
            'usuario_id' => auth()->id(),
            'tipo' => $tipo,
            'observacion' => $observacion,
            'estado' => 'confirmado',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $stocksAnteriores = collect();

        if ($tipo === 'inventario_nuevo') {
            $stocksAnteriores = DB::table('products')
                ->where('empresa_id', $empresaId)
                ->pluck('existencias', 'id_producto');

            DB::table('products')
                ->where('empresa_id', $empresaId)
                ->update(['existencias' => 0]);
        }

        // 🔥 2. RECORRER ITEMS
        foreach ($items as $item) {

            $producto = DB::table('products')
                ->where('id_producto', $item['codigo'])
                ->where('empresa_id', $empresaId)
                ->first();

            if (!$producto) continue;

            // Para inventario nuevo, el stock anterior debe ser el real antes de poner todo en cero.
            $cantidadAnterior = $tipo === 'inventario_nuevo'
                ? (float) ($stocksAnteriores[$item['codigo']] ?? 0)
                : (float) $producto->existencias;

            $cantidadNueva = (float) $item['cantidad'];
            $diferencia = $cantidadNueva - $cantidadAnterior;

            // 🔥 ACTUALIZAR STOCK
            DB::table('products')
                ->where('id_producto', $item['codigo'])
                ->where('empresa_id', $empresaId)
                ->update(['existencias' => $cantidadNueva]);

            // 🔥 KARDEX CORRECTO
            if ($tipo === 'inventario_nuevo') {

                guardarKardex(
                    $item['codigo'],
                    'inventario_nuevo',
                    $cantidadNueva,
                    $empresaId,
                    $ajusteId,
                    $cantidadAnterior // 🔥 AQUÍ ESTÁ LA CLAVE
                );

            } else {

             if ($diferencia > 0) {

                guardarKardex(
                    $item['codigo'],
                    'ajuste_entrada',
                    $diferencia,
                    $empresaId,
                    $ajusteId,
                    $cantidadAnterior // 👈 CLAVE
                );

            } elseif ($diferencia < 0) {

                guardarKardex(
                    $item['codigo'],
                    'ajuste_salida',
                    abs($diferencia),
                    $empresaId,
                    $ajusteId,
                    $cantidadAnterior // 👈 CLAVE
                );
            }

            }

            // 🔥 GUARDAR DETALLE
            DB::table('ajuste_inventario_detalles')->insert([
                'ajuste_inventario_id' => $ajusteId,
                'producto_id' => $item['codigo'],
                'cantidad_anterior' => $cantidadAnterior,
                'cantidad_nueva' => $cantidadNueva,
                'diferencia' => $diferencia,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::commit();

        return response()->json([
            'ok' => true
        ]);

    } catch (\Exception $e) {

        DB::rollBack();

        return response($e->getMessage(), 500);
    }
}

public function borradores()
{
    return AjusteInventario::where('estado', 'borrador')
       ->where('empresa_id', auth()->user()->getEmpresaActualId())
        ->latest()
        ->get();
}

public function verBorrador($id)
{
    $ajuste = AjusteInventario::with('detalles.producto')
        ->where('id', $id)
        ->where('empresa_id', auth()->user()->getEmpresaActualId())
        ->where('estado', 'borrador')
        ->firstOrFail();

    return response()->json([
        'tipo' => $ajuste->tipo,
        'observacion' => $ajuste->observacion,
        'detalles' => $ajuste->detalles
    ]);
}


public function eliminar($id)
{
    $ajuste = AjusteInventario::where('empresa_id', auth()->user()->getEmpresaActualId())
        ->find($id);

    if (!$ajuste) {
        return response()->json(['error' => 'No encontrado'], 404);
    }

    // 🔥 borrar detalles primero
    AjusteInventarioDetalle::where('ajuste_inventario_id', $ajuste->id)->delete();

    // 🔥 borrar borrador
    $ajuste->delete();

    return response()->json(['ok' => true]);
}


public function reporte($id)
{
    $empresaId = auth()->user()->getEmpresaActualId();

    $ajuste = DB::table('ajustes_inventario')
        ->where('id', $id)
        ->where('empresa_id', $empresaId)
        ->first();

    $detalles = DB::table('ajuste_inventario_detalles as d')
        ->join('products as p', 'p.id_producto', '=', 'd.producto_id')
        ->where('d.ajuste_inventario_id', $id)
        ->select(
            'p.id_producto as codigo',
            'p.descripcion_larga as nombre',
            'd.cantidad_anterior',
            'd.cantidad_nueva'
        )
        ->get();

    return view('inventario.reporte', compact('ajuste', 'detalles'));
}
public function index()
{
    return view('inventario.rapido'); // 👈 tu vista
}
public function reportePdf($id)
{
    $empresaId = auth()->user()->getEmpresaActualId();

    $ajuste = DB::table('ajustes_inventario')
        ->where('id', $id)
        ->where('empresa_id', $empresaId)
        ->first();

    // 🔴 VALIDAR ESTADO
    if ($ajuste->estado !== 'confirmado') {
        abort(403, 'No se puede ver reporte en borrador');
    }

    $empresa = DB::table('users')->where('id', $empresaId)->first();

    $detalles = DB::table('ajuste_inventario_detalles as d')
        ->join('products as p', 'p.id_producto', '=', 'd.producto_id')
        ->where('d.ajuste_inventario_id', $id)
        ->select(
            'p.id_producto as codigo',
            'p.descripcion_larga as nombre',
            'd.cantidad_anterior',
            'd.cantidad_nueva'
        )
        ->get();

    $pdf = Pdf::loadView('inventario.reporte_pdf', compact('ajuste', 'detalles', 'empresa'));

    return $pdf->download('reporte_inventario_'.$id.'.pdf');
}

}
