<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\ProductoVariante;
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

    // 🎨 0. CÓDIGO DE UNA VARIANTE PUNTUAL (talla/color): en empresas con
    // variantes el stock vive en la variante, no en el producto padre.
    $variante = ProductoVariante::where('empresa_id', $empresaId)
        ->where('codigo', $codigo)
        ->first();

    if ($variante) {
        $producto = Product::where('id', $variante->product_id)
            ->where('empresa_id', $empresaId)
            ->first();

        if (!$producto) {
            return response()->json(['error' => 'Producto no encontrado'], 404);
        }

        return response()->json([
            'id' => $producto->id_producto,
            'nombre' => $producto->descripcion_larga . ' — ' . $variante->nombre,
            'stock' => $variante->stock ?? 0,
            'producto_variante_id' => $variante->id,
            'codigo_mostrado' => $variante->codigo ?: $producto->id_producto,
        ]);
    }

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

    // 🎨 El stock de un producto con variantes vive en cada variante, no
    // en el producto padre -- se le pide al cajero el codigo de la
    // talla/color especifica en vez de dejarlo ajustar el padre.
    if ($producto->variantes()->where('activo', true)->exists()) {
        return response()->json([
            'error' => 'Este producto tiene variantes (talla/color). Ingresa o escanea el código de la variante específica, no el del producto.',
        ], 422);
    }

    return response()->json([
        'id' => $producto->id_producto,
        'nombre' => $producto->descripcion_larga,
        'stock' => $producto->existencias ?? 0,
    ]);
}


public function buscarLista(Request $request)
{
    $empresaId = auth()->user()->getEmpresaActualId();
    $q = trim($request->input('q', ''));

    $query = DB::table('products')
        ->where('empresa_id', $empresaId)
        // 🎨 Los productos con variantes no se pueden ajustar como un solo
        // renglon desde este buscador rapido -- hay que escribir/escanear
        // el codigo de la variante especifica (ver buscar()).
        ->whereNotIn('id', function ($sub) use ($empresaId) {
            $sub->select('product_id')
                ->from('producto_variantes')
                ->where('empresa_id', $empresaId)
                ->where('activo', true);
        });

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
    $empresaId = auth()->user()->getEmpresaActualId();

    foreach ($request->items as $item) {

        $producto = Product::where('empresa_id', $empresaId)
            ->where('id_producto', $item['codigo'])
            ->first();

        if (!$producto) continue;

        $varianteId = $item['producto_variante_id'] ?? null;

        // 🎨 el "anterior" de una variante es su propio stock, no el del
        // producto padre.
        $anterior = $varianteId
            ? (float) (ProductoVariante::where('id', $varianteId)->where('empresa_id', $empresaId)->value('stock') ?? 0)
            : (float) ($producto->existencias ?? 0);

        AjusteInventarioDetalle::create([
            'ajuste_inventario_id' => $ajuste->id,
            'producto_id' => $producto->id_producto,
            'producto_variante_id' => $varianteId,
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

        // 🔥 Stock ANTERIOR real de cada producto, capturado antes de que
        // el servicio lo toque -- para "inventario nuevo" el servicio pone
        // todo en 0 antes de aplicar las cantidades nuevas, y el kardex
        // necesita el valor de antes de ese reset.
        $stocksAnteriores = DB::table('products')
            ->where('empresa_id', $empresaId)
            ->pluck('existencias', 'id_producto');

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

        // 🔥 2. GUARDAR LOS DETALLES TAL CUAL VIENEN (con variante si
        // aplica) -- cantidad_anterior/diferencia las recalcula el
        // servicio de abajo, que sabe distinguir producto vs variante.
        foreach ($items as $item) {
            DB::table('ajuste_inventario_detalles')->insert([
                'ajuste_inventario_id' => $ajusteId,
                'producto_id' => $item['codigo'],
                'producto_variante_id' => $item['producto_variante_id'] ?? null,
                'cantidad_anterior' => 0,
                'cantidad_nueva' => $item['cantidad'],
                'diferencia' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 🎨 3. APLICAR: mueve products.existencias y, en paralelo,
        // producto_variantes.stock cuando la linea trae variante (mismo
        // servicio que usa el recurso de Filament "Ajustes de Inventario").
        $ajuste = AjusteInventario::findOrFail($ajusteId);
        app(\App\Services\Inventario\AplicarAjusteInventarioService::class)->aplicar($ajuste);

        // 🔥 4. KARDEX: un movimiento neto por PRODUCTO (si un producto
        // tiene varias variantes tocadas en el mismo ajuste, se registra
        // el movimiento neto de todas juntas, igual que el servicio hace
        // el rollup sobre products.existencias).
        $detallesPorProducto = $ajuste->detalles()->get()->groupBy('producto_id');

        foreach ($detallesPorProducto as $idProducto => $detalles) {
            $cantidadAnterior = (float) ($stocksAnteriores[$idProducto] ?? 0);

            if ($tipo === 'inventario_nuevo') {
                $cantidadNuevaProducto = $cantidadAnterior + $detalles->sum('diferencia');

                guardarKardex($idProducto, 'inventario_nuevo', $cantidadNuevaProducto, $empresaId, $ajusteId, $cantidadAnterior);
                continue;
            }

            $diferenciaProducto = $detalles->sum('diferencia');

            if ($diferenciaProducto > 0) {
                guardarKardex($idProducto, 'ajuste_entrada', $diferenciaProducto, $empresaId, $ajusteId, $cantidadAnterior);
            } elseif ($diferenciaProducto < 0) {
                guardarKardex($idProducto, 'ajuste_salida', abs($diferenciaProducto), $empresaId, $ajusteId, $cantidadAnterior);
            }
        }

        DB::commit();

        return response()->json([
            'ok' => true
        ]);

    } catch (\Throwable $e) {

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
    $ajuste = AjusteInventario::with('detalles.producto', 'detalles.variante')
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
