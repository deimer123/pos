<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Salida de mercancía</title>
  <style>
    body { font-family: monospace; font-size:12px; width:250px; margin:auto; }
    .header,.footer { text-align:center; }
    .items td { padding:2px 0; }
    .advertencia { text-align:center; font-weight:bold; font-size:13px; margin-top:12px; text-transform:uppercase; }
    .logo { display:block; margin:0 auto 6px auto; max-width:120px; height:auto; }
    .empresa { font-weight:700; }
    .lema { font-size:11px; margin-top:2px; }
    .sep { margin:6px 0; }
    @media print { @page { size:auto; margin:0; } body { margin:0; } }
  </style>
</head>
<body onload="window.print()">
@php
  /** @var \App\Models\Factura $factura */
  use Illuminate\Support\Facades\Storage;
  use Carbon\Carbon;

  // Configuración de empresa vinculada a la factura
  $config   = $factura->configuracionEmpresa ?? null;
  $logoUrl  = $config?->logo ? Storage::disk('public')->url($config->logo) : null;

  // Fechas robustas (soporta string o Carbon)
  $fechaDoc = $factura->fecha ? Carbon::parse($factura->fecha) : $factura->created_at;

  // Pago: contado / crédito y vencimiento (si aplica)
  $tipoPago   = strtolower($factura->tipo_pago ?? 'contado');
  $esCredito  = $tipoPago === 'credito';
  $vencCarbon = $esCredito && $factura->fecha_vencimiento
                  ? Carbon::parse($factura->fecha_vencimiento)
                  : null;
@endphp

<div class="header">
  {{-- Logo (opcional) --}}
  @if ($logoUrl)
    <img src="{{ $logoUrl }}" alt="Logo" class="logo">
  @endif

  {{-- Datos de empresa --}}
  <div class="empresa">{{ $config->nombre_empresa ?? 'EMPRESA' }}</div>
  <div>NIT {{ $config->nit ?? 'N/A' }}</div>
  @if($config?->lema)       <div class="lema">{{ $config->lema }}</div> @endif
  @if($config?->direccion)  <div>{{ $config->direccion }}</div> @endif
  @if($config?->telefono)   <div>Tel: {{ $config->telefono }}</div> @endif

  <div class="sep">------------------------</div>

  <strong>SALIDA #{{ $factura->id }}</strong><br>
  Cliente: {{ optional($factura->cliente)->nombre ?? 'Sin cliente' }}<br>
  Fecha: {{ $fechaDoc->format('Y-m-d H:i') }}<br>
  Pago: {{ $esCredito ? 'Crédito' : 'Contado' }}<br>
  @if($esCredito)
    Vence: {{ $vencCarbon ? $vencCarbon->format('Y-m-d') : '—' }}<br>
  @endif

  <div class="sep">------------------------</div>
</div>

<table class="items" width="100%">
  @foreach ($factura->detalles as $d)
    <tr><td colspan="2">{{ $d->descripcion_larga }}</td></tr>
    <tr>
      <td>{{ (int)$d->cantidad }} x ${{ number_format($d->precio, 0, ',', '.') }}</td>
      <td style="text-align:right;">${{ number_format($d->subtotal, 0, ',', '.') }}</td>
    </tr>
  @endforeach
</table>

<br>
<div style="text-align:right;">
  <strong>Total: ${{ number_format($factura->total, 0, ',', '.') }}</strong>
</div>

@if($factura->observaciones)
  <div style="margin-top:8px;">
    <strong>Obs:</strong> {{ $factura->observaciones }}
  </div>
@endif

<div class="footer">
  <div class="sep">------------------------</div>
  ¡Gracias!
</div>

<div class="advertencia">Documento no fiscal — salida de mercancía</div>

<script>
  window.onload = () => {
    window.print();
    setTimeout(() => window.close(), 1200);
  };
</script>
</body>
</html>
