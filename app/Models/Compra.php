<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB; // ✅ ÚNICO IMPORTANTE
use App\Models\Product;
use App\Models\CompraDetalle;
use App\Models\DevolucionCompra;
use App\Models\DevolucionCompraDetalle;
use App\Models\Pago;
use App\Models\Actor;
use App\Models\User;

class Compra extends Model
{
    protected $table = 'compras';

    protected $fillable = [
        'empresa_id',
        'proveedor_id',
        'user_id',
        'estado',
        'tipo_pago',
        'fecha',
        'fecha_vencimiento',
        'numero_factura',
        'subtotal',
        'descuento_total',
        'impuesto_total',
        'total',
        'saldo',
        'nota',
    ];

    protected $casts = [
        'fecha'             => 'date',
        'fecha_vencimiento' => 'date',
        'subtotal'          => 'decimal:2',
        'descuento_total'   => 'decimal:2',
        'impuesto_total'    => 'decimal:2',
        'total'             => 'decimal:2',
        'saldo'             => 'decimal:2',
    ];

    /* ─────────────── Relaciones ─────────────── */

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'proveedor_id', 'id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(CompraDetalle::class, 'compra_id');
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class, 'compra_id');
    }

    public function devoluciones(): HasMany
    {
        return $this->hasMany(DevolucionCompra::class, 'compra_id');
    }


    /* ─────────────── Recalcular Totales después de Devoluciones ─────────────── */

    public function recalcularTotalesConDevolucion(): void
    {
        $detalles = $this->detalles()->get();

        $subtotalOriginal = 0;
        $ivaOriginal = 0;

        foreach ($detalles as $detalle) {
            $descCom = $detalle->desc_comercial ?? 0;
            $precioUnit = $detalle->costo_unitario - ($detalle->costo_unitario * ($descCom / 100));
            $base = $detalle->cantidad * $precioUnit;
            $iva = $base * (($detalle->iva_pct ?? 0) / 100);

            $subtotalOriginal += $base;
            $ivaOriginal += $iva;
        }

        $devoluciones = DevolucionCompraDetalle::whereHas('devolucion', function ($q) {
            $q->where('compra_id', $this->id);
        })->get();

        $subtotalDevuelto = $devoluciones->sum('subtotal_sin_impuesto');
        $ivaDevuelto = $devoluciones->sum('valor_impuesto');

        $nuevoSubtotal = max(0, $subtotalOriginal - $subtotalDevuelto);
        $nuevoIVA = max(0, $ivaOriginal - $ivaDevuelto);
        $nuevoTotal = $nuevoSubtotal + $nuevoIVA;

        $pagos = $this->pagos()->sum('monto') ?? 0;
        $nuevoSaldo = max(0, $nuevoTotal - $pagos);

        $this->update([
            'subtotal' => $nuevoSubtotal,
            'impuesto_total' => $nuevoIVA,
            'total' => $nuevoTotal,
            'saldo' => $nuevoSaldo,
        ]);
    }


    /* ─────────────── Confirmar Compra (Aplicar Inventario) ─────────────── */
public function confirmar(): void
{
    DB::transaction(function () {

        $this->load('detalles');

        $subtotal = 0;
        $descuentoTotal = 0;
        $impuestoTotal = 0;
        $total = 0;

        foreach ($this->detalles as $detalle) {

            $cantidad = (float)$detalle->cantidad;
            $costo    = (float)$detalle->costo_unitario;
            $desc     = (float)$detalle->desc_comercial;
            $iva      = (float)$detalle->iva_pct;
            $util     = (float)$detalle->utilidad_pct;
            $pv       = (float)$detalle->precio_venta;

            // ✔ ESTE ES EL CÓDIGO REAL YA GUARDADO
            $codigo = (string)$detalle->product_id;

            // ✔ Buscar producto por empresa + código real
            $producto = Product::where('empresa_id', $this->empresa_id)
                ->where('id_producto', $codigo)
                ->first();

            // Calcular costos
            $costoConDesc = round($costo * (1 - $desc / 100), 2);
            $costoConIva  = round($costoConDesc * (1 + $iva / 100), 2);

            if ($pv <= 0) {
                $pv = round($costoConIva * (1 + $util / 100), 2);
            }

            // Totales línea
            $lineaBruta      = $cantidad * $costo;
            $lineaDescuento  = $lineaBruta * ($desc / 100);
            $lineaBase       = $lineaBruta - $lineaDescuento;
            $lineaImpuesto   = $lineaBase * ($iva / 100);
            $lineaTotal      = $lineaBase + $lineaImpuesto;

            $subtotal        += $lineaBruta;
            $descuentoTotal  += $lineaDescuento;
            $impuestoTotal   += $lineaImpuesto;
            $total           += $lineaTotal;

            // Actualizar inventario y precios
           if ($producto) {

                // 🔥 1. STOCK ANTES
                $stockAnterior = (float)$producto->existencias;

                // 🔥 2. GUARDAR KARDEX (ANTES DE TODO)
                guardarKardex(
                    $codigo,
                    'compra',
                    $cantidad,
                    $this->empresa_id,
                    $this->id,
                    $stockAnterior
                );

                // 🔥 3. ACTUALIZAR INVENTARIO
                $producto->existencias = $stockAnterior + $cantidad;

                // 🔥 4. ACTUALIZAR PRECIOS
                $producto->precio_costo_anterior = $producto->precio_costo;
                $producto->precio_venta_anterior = $producto->precio_venta1;

                $producto->precio_costo           = $costo;
                $producto->descuento_comercial    = $desc;
                $producto->precio_con_descuento   = $costoConDesc;
                $producto->costo_iva             = $costoConIva;
                $producto->iva_compra            = $iva;
                $producto->iva_venta             = $iva;
                $producto->utilidad1             = $util;
                $producto->precio_venta1         = $pv;

                // 🔥 5. GUARDAR
                $producto->save();
            }
        }

        // Totales finales
        $this->update([
            'subtotal'        => $subtotal,
            'descuento_total' => $descuentoTotal,
            'impuesto_total'  => $impuestoTotal,
            'total'           => $total,
            'saldo'           => $this->tipo_pago === 'credito' ? $total : 0,
            'estado'          => 'confirmada',
        ]);
    });
}



public function notasCredito()
{
    return $this->hasMany(\App\Models\NotaCredito::class);
}


public function productos()
{
    return $this->hasMany(CompraDetalle::class, 'compra_id');
}


}
