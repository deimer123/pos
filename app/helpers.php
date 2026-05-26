<?php

use Illuminate\Support\Facades\DB;

function guardarKardex($productoId, $tipo, $cantidad, $empresaId, $referencia = null, $stockAnteriorManual = null)
{
    // 🔍 Obtener producto
    $producto = DB::table('products')
        ->where('id_producto', $productoId)
        ->where('empresa_id', $empresaId)
        ->first();

    if (!$producto) return;

    // 🧠 Stock anterior (manual o automático)
    $stockAnterior = $stockAnteriorManual !== null 
        ? $stockAnteriorManual 
        : $producto->existencias;

    $entrada = 0;
    $salida = 0;
    $stockNuevo = $stockAnterior;

    // 🎯 Lógica por tipo
    switch ($tipo) {

         // 🟢 ENTRADAS
    case 'compra':
    case 'devolucion':
    case 'devolucion_venta': // 🔥 agregado
    case 'ajuste_entrada':
        $entrada = $cantidad;
        $stockNuevo = $stockAnterior + $cantidad;
        break;

    case 'venta':
    case 'ajuste_salida':
    case 'devolucion_compra': // 🔥 AQUÍ VA
    $salida = $cantidad;
    $stockNuevo = $stockAnterior - $cantidad;
    break;

    // 🔵 INVENTARIO NUEVO
    case 'inventario_nuevo':
        $entrada = $cantidad;
        $salida = 0;
        $stockNuevo = $cantidad;
        break;
}

    // 💾 Guardar en kardex
    DB::table('kardex')->insert([
        'empresa_id' => $empresaId,
        'producto_id' => $productoId,
        'tipo' => $tipo,
        'entrada' => $entrada,
        'salida' => $salida,
        'stock_anterior' => $stockAnterior,
        'stock_actual' => $stockNuevo,
        'referencia' => $referencia,
        'detalle' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}