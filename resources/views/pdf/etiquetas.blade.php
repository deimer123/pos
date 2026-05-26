<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">

<style>
/* ===== TAMAÑO REAL DEL PAPEL (NO A4) ===== */
@page {
    margin: 0;
    size: {{ $papel_ancho }}mm auto;
}

/* ===== ESTILOS GENERALES ===== */
body {
    margin: 0;
    padding: 0;
    font-family: sans-serif;
    font-size: 9px;
}

.etiqueta {
    border: 1px solid #000; /* quitar cuando esté calibrado */
    height: 25mm;
    padding: 2mm;
    box-sizing: border-box;
    text-align: center;
}

.nombre {
    font-size: 8px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

img {
    max-height: 18mm;
}

small {
    display: block;
    font-size: 7px;
}
</style>
</head>

<body>

@php
/*
FORMATOS SOPORTADOS
-------------------
2x50 → papel 108 mm
3x32 → papel 103 mm
*/

if ($formato === '2x50') {
    $porFila = 2;
    $margenInicio = 3;
    $margenEntre = 3;
    $margenFinal = 2;
    $anchoEtiqueta = 50;
    $colspan = 5;
} else { // 3x32
    $porFila = 3;
    $margenInicio = 2;
    $margenEntre = 2;
    $margenFinal = 1;
    $anchoEtiqueta = 32;
    $colspan = 7;
}

$margenVertical = 3;
@endphp

<table width="{{ $papel_ancho }}mm" cellspacing="0" cellpadding="0">
@foreach ($etiquetas->chunk($porFila) as $fila)

    <tr>
        <!-- Margen izquierdo -->
        <td width="{{ $margenInicio }}mm"></td>

        @foreach ($fila as $item)
            <td width="{{ $anchoEtiqueta }}mm" align="center" valign="top">
                <div class="etiqueta">
                    <strong>{{ $almacen }}</strong>

                    <div class="nombre">{{ $item['nombre'] }}</div>

                    @if ($tipo_codigo === 'barcode')
                        <img src="data:image/png;base64,{{ DNS1D::getBarcodePNG($item['product_id'], 'C128', 1.2, 30) }}">
                    @else
                        <img src="data:image/png;base64,{{ base64_encode(
                            \Endroid\QrCode\Builder\Builder::create()
                                ->data((string) $item['product_id'])
                                ->size(80)
                                ->margin(0)
                                ->build()
                                ->getString()
                        ) }}">
                    @endif

                    <small>{{ $item['product_id'] }}</small>
                </div>
            </td>

            @if (!$loop->last)
                <!-- Espacio entre etiquetas -->
                <td width="{{ $margenEntre }}mm"></td>
            @endif
        @endforeach

        <!-- Margen final -->
        <td width="{{ $margenFinal }}mm"></td>
    </tr>

    <!-- Margen vertical entre filas -->
    <tr>
        <td colspan="{{ $colspan }}" height="{{ $margenVertical }}mm"></td>
    </tr>

@endforeach
</table>

</body>
</html>
