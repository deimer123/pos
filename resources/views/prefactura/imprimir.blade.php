<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Impresion Prefactura</title>
    <style>
        body {
            font-family: monospace;
            font-size: 12px;
            width: 250px;
            margin: auto;
        }

        .header, .footer {
            text-align: center;
        }

        .items td {
            padding: 2px 0;
        }

        .advertencia {
            text-align: center;
            font-weight: bold;
            font-size: 14px;
            margin-top: 15px;
            text-transform: uppercase;
        }

        @media print {
            @page {
                size: auto;
                margin: 0;
            }

            body {
                margin: 0;
            }
        }
    </style>
</head>
<body onload="window.print()">

@php
    $config = $prefactura->configuracionEmpresa ?? null;
@endphp

<div class="header">
    <strong>{{ $config->nombre_empresa ?? 'EMPRESA DESCONOCIDA' }}</strong><br>
    NIT {{ $config->nit ?? 'N/A' }}<br>
    {{ $config->direccion ?? '' }}<br>
    Tel: {{ $config->telefono ?? '' }}<br>
    ------------------------<br>
    <strong>Prefactura {{ $prefactura->numero_visual }}</strong><br>
    Cliente: {{ $prefactura->cliente->nombre ?? 'Sin cliente' }}<br>
    Fecha: {{ $prefactura->created_at->format('Y-m-d H:i') }}
    <br>------------------------
</div>

<table class="items" width="100%">
    @foreach ($prefactura->productos as $item)
        <tr>
            <td colspan="2">{{ $item->descripcion_larga }}</td>
        </tr>
        <tr>
            <td>{{ $item->cantidad }} x ${{ number_format($item->precio_unitario, 0, ',', '.') }}</td>
            <td style="text-align: right;">${{ number_format($item->subtotal, 0, ',', '.') }}</td>
        </tr>
    @endforeach
</table>

<br>
<div style="text-align: right;">
    <strong>Total: ${{ number_format($prefactura->productos->sum('subtotal'), 0, ',', '.') }}</strong>
</div>

<div class="footer">
    ------------------------<br>
    Gracias por su compra
</div>

<div class="advertencia">
    **ESTO NO ES UN DOCUMENTO VALIDO**
</div>

<script>
    window.onload = () => {
        window.print();

        setTimeout(() => {
            window.close();
        }, 1500);
    };
</script>

</body>
</html>
