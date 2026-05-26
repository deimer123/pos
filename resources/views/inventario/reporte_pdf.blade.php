<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial; font-size: 12px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #000; padding: 5px; }
        th { background: #eee; }
        h2, h4 { text-align: center; }
    </style>
</head>
<body>

<h2>{{ $empresa->name }}</h2>
<h4>REPORTE DE INVENTARIO</h4>

<hr>

<p><strong>Tipo:</strong> {{ $ajuste->tipo }}</p>
<p><strong>Fecha:</strong> {{ $ajuste->created_at }}</p>
<p><strong>Observación:</strong> {{ $ajuste->observacion }}</p>

<table>
    <thead>
        <tr>
            <th>Código</th>
            <th>Producto</th>
            <th>Stock Anterior</th>
            <th>Stock Nuevo</th>
        </tr>
    </thead>
    <tbody>
        @foreach($detalles as $item)
        <tr>
            <td>{{ $item->codigo }}</td>
            <td>{{ $item->nombre }}</td>
            <td>{{ $item->cantidad_anterior }}</td>
            <td>{{ $item->cantidad_nueva }}</td>
        </tr>
        @endforeach
    </tbody>
</table>

</body>
</html>