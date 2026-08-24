<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Comanda {{ $mesa->nombre }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Courier New', monospace; font-size: 13px; width: 300px; padding: 10px; }
        .center { text-align: center; }
        .bold { font-weight: bold; }
        .divider { border-top: 1px dashed #000; margin: 6px 0; }
        .badge { display:inline-block; border:1px solid #000; border-radius:4px; padding:1px 6px; font-size:11px; font-weight:bold; margin-top:2px; }
        .item-row { margin: 6px 0; }
        .item-cant { font-size: 16px; font-weight: 800; }
        .item-nombre { font-size: 14px; font-weight: bold; }
        .item-nota { font-size: 11px; margin-left: 24px; }
        .obs-box { border: 1px solid #000; padding: 5px; margin: 6px 0; font-size: 12px; }
        .nota { font-size: 10px; color: #555; text-align: center; margin-top: 8px; }
        @media print {
            @page { size: auto; margin: 0; }
            body { margin: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>

<div class="center">
    <div class="bold" style="font-size:16px;">COMANDA</div>
    @if($orden->numero_cocina_dia)
        <div>Orden #{{ $orden->numero_cocina_dia }} del día</div>
    @endif
</div>

<div class="divider"></div>

<div class="center">
    <div class="bold" style="font-size:15px;">{{ $mesa->nombre }}</div>
    @if(($orden->tipo_pedido ?? 'mesa') === 'domicilio')
        <span class="badge">🛵 DOMICILIO</span>
    @elseif(($orden->tipo_pedido ?? 'mesa') === 'para_llevar')
        <span class="badge">🥡 PARA LLEVAR</span>
    @endif
</div>
<div>Fecha: {{ now()->format('d/m/Y H:i') }}</div>
@if($orden->usuario)
<div>Atendido por: {{ $orden->usuario->name ?? '' }}</div>
@endif

@if(($orden->tipo_pedido ?? 'mesa') === 'domicilio')
<div class="divider"></div>
<div>
    @if($orden->dom_nombre)<div><span class="bold">Cliente:</span> {{ $orden->dom_nombre }}</div>@endif
    @if($orden->dom_telefono)<div><span class="bold">Tel:</span> {{ $orden->dom_telefono }}</div>@endif
    @if($orden->dom_direccion)<div><span class="bold">Dirección:</span> {{ $orden->dom_direccion }}</div>@endif
    @if($orden->dom_observaciones)<div><span class="bold">Nota domicilio:</span> {{ $orden->dom_observaciones }}</div>@endif
</div>
@endif

<div class="divider"></div>

@foreach($orden->items as $item)
<div class="item-row">
    <span class="item-cant">{{ (int) $item->cantidad }}x</span>
    <span class="item-nombre">{{ $item->producto->descripcion_larga ?? $item->producto->nombre ?? 'Producto' }}</span>
    @if($item->nota_cocina)
        <div class="item-nota">📝 {{ $item->nota_cocina }}</div>
    @endif
</div>
@endforeach

@if($orden->observaciones)
<div class="divider"></div>
<div class="obs-box">
    <div class="bold">Observaciones:</div>
    <div>{{ $orden->observaciones }}</div>
</div>
@endif

<div class="divider"></div>
<div class="nota">*** FIN COMANDA ***</div>

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
