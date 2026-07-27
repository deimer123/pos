{{-- filepath: resources/views/tickets/abono.blade.php --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Comprobante de Abono</title>
    <style>
        body { font-family: monospace; font-size: 15px; }
        .ticket { width: 320px; margin: 0 auto; }
        .center { text-align: center; }
        .bold { font-weight: bold; }
        .mt { margin-top: 10px; }
        .mb { margin-bottom: 10px; }
        hr { border: 0; border-top: 1px dashed #333; margin: 10px 0; }
        @media print {
            @page { size: auto; margin: 0; }
            body { margin: 0; }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="ticket">
        <div class="center bold">COMPROBANTE DE ABONO</div>
        <hr>
        <div><span class="bold">Cliente:</span> {{ $abono->factura->cliente->nombre ?? 'Consumidor final' }}</div>
        <div><span class="bold">Factura:</span> {{ $abono->factura->numero_visual }}</div>
        <div><span class="bold">Fecha abono:</span> {{ \Carbon\Carbon::parse($abono->fecha)->format('Y-m-d H:i') }}</div>
        <div><span class="bold">Medio:</span> {{ ucfirst($abono->medio_pago) }}</div>
        @if($abono->transferencia_obs)
            <div><span class="bold">Obs:</span> {{ $abono->transferencia_obs }}</div>
        @endif
        <hr>
        <div class="bold">Monto abonado: ${{ number_format($abono->monto, 0, ',', '.') }}</div>
        <hr>
        <div>Usuario: {{ $abono->user->name ?? '' }}</div>
        <div class="center mt">¡Gracias por su pago!</div>
    </div>
</body>
</html>