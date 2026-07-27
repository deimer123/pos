<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cuenta Mesa {{ $mesa->nombre }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Courier New', monospace; font-size: 12px; width: 300px; padding: 10px; }
        .center { text-align: center; }
        .bold { font-weight: bold; }
        .divider { border-top: 1px dashed #000; margin: 6px 0; }
        .row { display: flex; justify-content: space-between; margin: 2px 0; }
        .row-header { display: flex; justify-content: space-between; margin: 2px 0; font-weight: bold; font-size: 11px; }
        .total-row { display: flex; justify-content: space-between; font-weight: bold; font-size: 14px; margin-top: 4px; }
        .empresa { font-size: 15px; font-weight: bold; }
        .nota { font-size: 10px; color: #555; text-align: center; margin-top: 8px; }
        @media print {
            @page { size: auto; margin: 0; }
            body { width: 100%; margin: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>

<div class="center">
    <div class="empresa">{{ $config->nombre_empresa ?? 'Punto de Venta' }}</div>
    @if($config && $config->nit)
        <div>NIT: {{ $config->nit }}</div>
    @endif
    @if($config && $config->direccion)
        <div>{{ $config->direccion }}</div>
    @endif
</div>

<div class="divider"></div>

<div class="center bold" style="font-size:14px;">CUENTA</div>
<div class="row">
    <span>Mesa:</span>
    <span>{{ $mesa->nombre }} {{ $mesa->zona ? '('.$mesa->zona.')' : '' }}</span>
</div>
<div class="row">
    <span>Fecha:</span>
    <span>{{ now()->format('d/m/Y H:i') }}</span>
</div>
@if($orden->usuario)
<div class="row">
    <span>Atendido por:</span>
    <span>{{ $orden->usuario->name ?? '' }}</span>
</div>
@endif

<div class="divider"></div>

<div class="row-header">
    <span style="flex:2;">Producto</span>
    <span style="flex:0.5; text-align:center;">Cant</span>
    <span style="flex:1; text-align:right;">Precio</span>
    <span style="flex:1; text-align:right;">Total</span>
</div>
<div class="divider"></div>

@foreach($items as $item)
<div class="row">
    <span style="flex:2; word-break:break-word;">{{ $item->producto->descripcion_larga ?? 'Producto' }}</span>
    <span style="flex:0.5; text-align:center;">{{ number_format($item->cantidad, 0) }}</span>
    <span style="flex:1; text-align:right;">${{ number_format($item->precio_unitario, 0, ',', '.') }}</span>
    <span style="flex:1; text-align:right;">${{ number_format($item->subtotal, 0, ',', '.') }}</span>
</div>
@endforeach

<div class="divider"></div>

<div class="total-row">
    <span>TOTAL:</span>
    <span>${{ number_format($total, 0, ',', '.') }}</span>
</div>

<div class="divider"></div>
<div class="nota">* Esta cuenta no es una factura *<br>Gracias por su visita</div>

<div class="no-print" style="margin-top:20px; text-align:center;">
    <button onclick="window.print()" style="padding:8px 20px; background:#4338ca; color:white; border:none; border-radius:6px; cursor:pointer; font-size:14px;">
        Imprimir
    </button>
</div>

<script>
    window.onload = function() {
        window.print();
    };
</script>
</body>
</html>
