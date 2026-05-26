<?php

namespace App\Http\Controllers;

use App\Models\NotaCredito;
use App\Models\ConfiguracionEmpresa;

class NotaCreditoPdfController extends Controller
{
    public function pdf(NotaCredito $nota)
    {
        abort_unless(
            auth()->check() && (int) $nota->empresa_id === (int) auth()->user()->getEmpresaActualId(),
            404
        );

        $nota->load([
            'items',
            'compra',
            'user',
        ]);

        // 🔹 Cargar configuración de empresa
        $empresa = ConfiguracionEmpresa::where('empresa_id', $nota->empresa_id)->first();

        // 🔹 Cargar nombres de productos (si los quieres)
        foreach ($nota->items as $item) {
            $item->nombre = optional(
                \App\Models\Product::where('id_producto', $item->product_id)
                    ->where('empresa_id', $nota->empresa_id)
                    ->first()
            )->descripcion_larga;
        }

        $pdf = \PDF::loadView('pdf.nota_credito', [
            'nota' => $nota,
            'empresa' => $empresa,
        ])->setPaper('A4');

        return $pdf->stream('NotaCredito_' . $nota->numero . '.pdf');
    }
}
