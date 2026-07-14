<?php

namespace App\Http\Controllers;

use App\Models\ConfiguracionEmpresa;
use App\Models\Product;
use App\Models\ProductCombo;
use App\Models\Receta;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class PosCatalogoController extends Controller
{
    /**
     * Catalogo completo de la empresa actual, para que el POS lo guarde
     * localmente (IndexedDB) y busque sin depender del servidor en cada
     * tecla. Los valores de stock/receta van precalculados aqui para no
     * duplicar esa logica en JS.
     */
    public function index(): JsonResponse
    {
        $empresaId = auth()->user()->getEmpresaActualId();

        $recetasActivas = Receta::where('empresa_id', $empresaId)
            ->where('activo', true)
            ->with('items.ingrediente')
            ->get()
            ->keyBy('product_id');

        $productosConCombo = ProductCombo::where('activo', true)
            ->where('empresa_id', $empresaId)
            ->pluck('product_id')
            ->unique();

        $productos = Product::query()
            ->where('empresa_id', $empresaId)
            ->where('id_producto', '!=', '10001')
            ->with(['alternateCodes', 'mecanico'])
            ->get()
            ->map(function (Product $producto) use ($recetasActivas, $productosConCombo) {
                $vendePor = $producto->vende_por ?? 'unidad';

                $sufijoVenta = match ($vendePor) {
                    'peso' => '/ kg',
                    'porcion' => '/ porcion',
                    'litro' => '/ lt',
                    'metro' => '/ mt',
                    'hora' => '/ hr',
                    default => '/ und',
                };

                $stockUnidad = match ($vendePor) {
                    'peso' => 'kg',
                    'porcion' => 'porcion',
                    'litro' => 'lt',
                    'metro' => 'mt',
                    'hora' => 'hr',
                    default => match ((int) ($producto->id_unidad_de_medida ?? 1)) {
                        2 => 'kg',
                        3 => 'lt',
                        4 => 'mt',
                        5 => 'hr',
                        default => 'und',
                    },
                };

                $receta = $recetasActivas->get($producto->id);
                $receta_info = null;

                if ($receta) {
                    $stockUnidad = $receta->unidad_rendimiento;
                    $receta_info = [
                        'porciones_disponibles' => $receta->porciones_disponibles,
                        'unidad_rendimiento' => $receta->unidad_rendimiento,
                    ];
                }

                $tieneImagen = ! empty($producto->foto) && $producto->foto !== 'NULL';
                $fotoUrl = $tieneImagen
                    ? Storage::disk('public')->url($producto->foto)
                    : asset('images/sin-imagen.png');

                return [
                    'id_producto' => $producto->id_producto,
                    'descripcion_larga' => $producto->descripcion_larga,
                    'precio_venta1' => (float) $producto->precio_venta1,
                    'existencias' => (float) $producto->existencias,
                    'foto_url' => $fotoUrl,
                    'vende_por' => $vendePor,
                    'tipo_producto' => $producto->tipo_producto,
                    'id_unidad_de_medida' => (int) ($producto->id_unidad_de_medida ?? 1),
                    'sufijo_venta' => $sufijoVenta,
                    'stock_unidad' => $stockUnidad,
                    'stock_decimales' => $stockUnidad === 'und' ? 0 : 2,
                    'mecanico_nombre' => $producto->tipo_producto === 'servicio'
                        ? optional($producto->mecanico)->nombre
                        : null,
                    'tercero_nombre' => $producto->tercero_nombre,
                    'alternate_codes' => $producto->alternateCodes->pluck('code')->values(),
                    'receta' => $receta_info,
                    'tiene_receta' => $receta_info !== null,
                    'tiene_combo' => $productosConCombo->contains($producto->id),
                ];
            })
            ->values();

        // Mismo criterio que CarritoVenta::descuentoMaximoPermitido(): sin
        // limite para admin_empresa, si no el maximo configurado (o 100%
        // por defecto). Se manda aqui para poder clamplear el descuento
        // localmente cuando se agrega un producto al carrito offline.
        $descuentoMaximoPermitido = auth()->user()->hasRole('admin_empresa')
            ? null
            : (float) (ConfiguracionEmpresa::where('empresa_id', $empresaId)->value('descuento_maximo_permitido') ?? 100.0);

        return response()->json([
            'productos' => $productos,
            'descuento_maximo_permitido' => $descuentoMaximoPermitido,
            'sincronizado_en' => now()->toIso8601String(),
        ]);
    }
}
