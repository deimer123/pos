<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <style>
    body { font-family: Arial, Helvetica, sans-serif; font-size:14px; color:#111; background:#f4f4f4; margin:0; padding:24px; }
    .box { max-width:520px; margin:0 auto; background:#fff; border-radius:8px; padding:24px; }
    .empresa { font-weight:700; font-size:18px; margin-bottom:4px; }
    .total { font-size:22px; font-weight:700; margin:16px 0; }
    .cufe { font-size:11px; color:#666; word-break:break-all; margin-top:16px; }
    .btn { display:inline-block; background:#111; color:#fff; text-decoration:none; padding:10px 18px; border-radius:6px; margin-top:12px; }
    .footer { font-size:11px; color:#999; margin-top:24px; text-align:center; }
  </style>
</head>
<body>
  <div class="box">
    <div class="empresa">{{ $empresa?->nombre_empresa ?? 'Empresa' }}</div>
    <p>Hola {{ $cliente?->nombre ?? 'cliente' }}, adjuntamos tu factura electrónica.</p>

    <div>Factura: <strong>{{ $factura->numero_visual }}</strong></div>
    <div class="total">Total: ${{ number_format($factura->total, 0, ',', '.') }}</div>

    @if($factura->ubl21_pdf_url)
      <a class="btn" href="{{ $factura->ubl21_pdf_url }}">Ver factura en PDF</a>
    @endif

    @if($factura->ubl21_cufe)
      <div class="cufe">CUFE: {{ $factura->ubl21_cufe }}</div>
    @endif

    <div class="footer">Este correo se genera automáticamente, por favor no responder.</div>
  </div>
</body>
</html>
