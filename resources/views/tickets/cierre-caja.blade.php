<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cierre de Caja</title>
    <style>
        body { font-family: monospace; font-size: 14px; }
        .titulo { font-size: 18px; font-weight: bold; text-align: center; }
        .resumen { margin: 10px 0; }
        .totales { font-weight: bold; }
    </style>
</head>
<body>
    <div class="titulo">CIERRE DE CAJA</div>
    <div>Fecha: {{ $fecha }}</div>
   <div>Cajero: {{ $usuario }}</div>
    <div class="resumen">
        <div>Efectivo: ${{ number_format($efectivo, 0, ',', '.') }}</div>
        <div>Transferencia: ${{ number_format($transferencia, 0, ',', '.') }}</div>
        <div>Crédito: ${{ number_format($credito, 0, ',', '.') }}</div>
        <div>Entradas efectivo: ${{ number_format($entradas_efectivo ?? 0, 0, ',', '.') }}</div>
        <div>Salidas efectivo: - ${{ number_format($salidas_efectivo ?? 0, 0, ',', '.') }}</div>
        <div>Entradas transf.: ${{ number_format($entradas_transferencia ?? 0, 0, ',', '.') }}</div>
        <div>Salidas transf.: - ${{ number_format($salidas_transferencia ?? 0, 0, ',', '.') }}</div>
        <div>Total contado: ${{ number_format($total_contado, 0, ',', '.') }}</div>
        <div>Total ventas: ${{ number_format($total, 0, ',', '.') }}</div>
    </div>
    <div class="totales">
        <div>Monto de cierre: ${{ number_format($monto_cierre, 0, ',', '.') }}</div>
        <div>Diferencia contra efectivo: ${{ number_format($diferencia, 0, ',', '.') }}</div>
    </div>
    <div style="margin-top:20px;text-align:center;">¡Gracias!</div>
    <script>
        window.onload = function() { window.print(); }
    </script>
</body>
</html>
