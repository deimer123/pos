<?php

// app/Http/Controllers/TicketAbonoController.php
namespace App\Http\Controllers;

use App\Models\FacturaPago;

class TicketAbonoController extends Controller
{
    public function show(FacturaPago $pago)
    {
        $pago->load([
            'factura.cliente',
            'usuario',
            'factura.empresa',           // si usas empresa = user
            'factura.configuracionEmpresa',
        ]);

        $empresa = $pago->factura->configuracionEmpresa
                 ?: optional($pago->factura->empresa); // fallback si usas User como empresa

        return view('tickets.abono', [
            'pago'    => $pago,
            'factura' => $pago->factura,
            'cliente' => $pago->factura->cliente,
            'empresa' => $empresa,
        ]);
    }
}
