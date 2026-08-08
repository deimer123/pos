<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ticket orden #{{ str_pad($orden->numero_orden, 4, '0', STR_PAD_LEFT) }}</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        @page { size: 60mm 40mm; margin: 0; }
        body { font-family: sans-serif; width:60mm; height:40mm; padding:2mm; text-align:center; }
        .empresa { font-size:8px; font-weight:700; color:#111; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .numero { font-size:16px; font-weight:900; letter-spacing:1px; margin-top:1mm; }
        img.barcode { width:100%; height:14mm; margin-top:1mm; }
        .equipo { font-size:9px; font-weight:600; margin-top:1mm; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .cliente { font-size:8px; color:#333; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        @media print {
            @page { margin: 0; }
            body { margin: 0; }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="empresa">{{ $config?->nombre_empresa ?? '' }}</div>
    <div class="numero">ORDEN #{{ str_pad($orden->numero_orden, 4, '0', STR_PAD_LEFT) }}</div>
    <img class="barcode" src="data:image/png;base64,{{ \Milon\Barcode\Facades\DNS1DFacade::getBarcodePNG((string) $orden->numero_orden, 'C128', 2, 30) }}">
    <div class="equipo">{{ trim(($orden->marca ?? '') . ' ' . ($orden->modelo ?? '')) ?: 'Equipo' }}</div>
    <div class="cliente">{{ $orden->cliente_nombre }}</div>
</body>
</html>
