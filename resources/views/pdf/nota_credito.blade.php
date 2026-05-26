<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Nota Crédito {{ $nota->numero }}</title>

<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 12px; margin: 25px; }
    .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; }
    .logo { width: 120px; margin-bottom: 5px; }
    .empresa { font-size: 16px; font-weight: bold; }
    .info { margin-top: 10px; }
    table { width: 100%; border-collapse: collapse; margin-top: 15px; }
    th, td { padding: 8px; border-bottom: 1px solid #ccc; }
    th { background: #f0f0f0; }
    .right { text-align: right; }
    .center { text-align: center; }
</style>
</head>
<body>

<div class="header">

    @if(!empty($empresa?->logo))
        <img class="logo" src="{{ public_path('storage/' . $empresa->logo) }}">
    @endif

    <div class="empresa">{{ $empresa->nombre_empresa ?? 'EMPRESA SIN CONFIGURAR' }}</div>
    <div>NIT: {{ $empresa->nit ?? '' }}</div>
    <div>{{ $empresa->direccion ?? '' }}</div>
    <div>{{ $empresa->telefono ?? '' }}</div>
</div>

<h2 class="center">NOTA CRÉDITO</h2>
<h3 class="center">{{ $nota->numero }}</h3>

<table>
    <tr>
        <td><strong>Factura asociada:</strong> {{ $nota->compra->numero_factura ?? 'SIN FACTURA' }}</td>
        <td><strong>Proveedor:</strong> {{ $nota->empresa_deudora ?? 'SIN PROVEEDOR' }}</td>
    </tr>
    <tr>
        <td><strong>Fecha creación:</strong> {{ $nota->created_at->format('d/m/Y H:i') }}</td>
        <td><strong>Fecha cobro:</strong>
            {{ $nota->fecha_cobro ? date('d/m/Y H:i', strtotime($nota->fecha_cobro)) : 'SIN COBRAR' }}
        </td>
    </tr>
    <tr>
        <td colspan="2"><strong>Motivo:</strong> {{ $nota->motivo }}</td>
    </tr>
</table>

<h3>Detalle de devolución</h3>

<table>
    <thead>
        <tr>
            <th>Producto</th>
            <th class="center">Cant</th>
            <th class="right">Valor + IVA</th>
        </tr>
    </thead>
    <tbody>
    @foreach($nota->items as $item)
        <tr>
            <td>{{ $item->nombre }}</td>
            <td class="center">{{ number_format($item->cantidad, 2) }}</td>
            <td class="right">$ {{ number_format($item->subtotal_con_impuesto, 0, ',', '.') }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<h3 class="right">TOTAL DEVUELTO: $ {{ number_format($nota->total, 0, ',', '.') }}</h3>

</body>
</html>
