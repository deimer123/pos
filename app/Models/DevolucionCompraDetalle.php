<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DevolucionCompraDetalle extends Model
{
    use HasFactory;

    protected $table = 'devolucion_compra_detalles';

    protected $fillable = [
        'devolucion_compra_id',
        'compra_detalle_id',
        'product_id',               // se maneja como string para multiempresa
        'producto_variante_id',
        'cantidad',
        'costo_unitario',
        'porcentaje_impuesto',
        'valor_impuesto',
        'subtotal_sin_impuesto',
        'subtotal_con_impuesto',
    ];

    // 🧩 Relación con la devolución principal
    public function devolucion()
    {
        return $this->belongsTo(DevolucionCompra::class, 'devolucion_compra_id');
    }

    // 🧾 Relación con el detalle de compra original
    public function detalleCompra()
    {
        return $this->belongsTo(CompraDetalle::class, 'compra_detalle_id');
    }

    // 🎨 Relación con la variante (talla/color) devuelta, si aplica
    public function variante()
    {
        return $this->belongsTo(ProductoVariante::class, 'producto_variante_id');
    }

    // 🏷️ Relación con el producto (sin FK rígida, usando empresa + product_id)
    public function producto()
    {
        return $this->belongsTo(Product::class, 'product_id', 'id_producto')
            ->where('empresa_id', auth()->user()->getEmpresaActualId());
    }

    /**
     * 🔹 Método auxiliar opcional para calcular totales
     * (por si necesitas recalcular valores dentro del modelo mismo)
     */
    public function calcularTotales(): array
    {
        $base = $this->cantidad * $this->costo_unitario;
        $valorImpuesto = $base * ($this->porcentaje_impuesto / 100);
        $subtotalConIva = $base + $valorImpuesto;

        return [
            'base' => $base,
            'valorImpuesto' => $valorImpuesto,
            'subtotalConIva' => $subtotalConIva,
        ];
    }
}
