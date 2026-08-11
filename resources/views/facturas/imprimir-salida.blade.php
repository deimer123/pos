<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>{{ $factura->tipo_factura === 'electronica' ? 'Factura electronica' : 'Salida de mercancia' }}</title>
  <style>
    body { font-family: monospace; font-size:12px; width:250px; margin:auto; color:#111; }
    .header,.footer { text-align:center; }
    .items td { padding:2px 0; vertical-align:top; }
    .advertencia { text-align:center; font-weight:bold; font-size:13px; margin-top:12px; text-transform:uppercase; }
    .logo { display:block; margin:0 auto 6px auto; max-width:120px; height:auto; }
    .empresa { font-weight:700; }
    .lema { font-size:11px; margin-top:2px; }
    .sep { margin:6px 0; }
    .factus-box { border-top:1px dashed #111; border-bottom:1px dashed #111; padding:6px 0; margin-top:8px; text-align:left; }
    .small { font-size:10px; line-height:1.25; }
    .break { overflow-wrap:anywhere; word-break:break-word; }
    .qr { display:block; width:145px; height:145px; margin:7px auto 2px auto; }
    @media print { @page { size:auto; margin:0; } body { margin:0; } }
  </style>
</head>
<body onload="window.print()">
<div class="header">
  @if ($logoUrl)
    <img src="{{ $logoUrl }}" alt="Logo" class="logo">
  @endif

  <div class="empresa">{{ $config->nombre_empresa ?? 'EMPRESA' }}</div>
  <div>NIT {{ $config->nit ?? 'N/A' }}</div>
  @if($config?->lema) <div class="lema">{{ $config->lema }}</div> @endif
  @if($config?->direccion) <div>{{ $config->direccion }}</div> @endif
  @if($config?->telefono) <div>Tel: {{ $config->telefono }}</div> @endif

  @if($esElectronica && ($resolucionDianElectronica || $prefijoElectronico))
    <div class="small" style="margin-top:4px;">
      @if($resolucionDianElectronica)
        Resolucion DIAN {{ $resolucionDianElectronica }}<br>
      @endif
      @if($prefijoElectronico || $rangoDesdeElectronico || $rangoHastaElectronico)
        {{ $prefijoElectronico ? 'Prefijo '.$prefijoElectronico.' - ' : '' }}Rango autorizado {{ $rangoDesdeElectronico ?? '-' }} al {{ $rangoHastaElectronico ?? '-' }}
      @endif
    </div>
  @endif

  <div class="sep">------------------------</div>

  <strong>{{ $factura->numero_visual }}</strong><br>
  @if($esElectronica)
    <strong>FACTURA ELECTRONICA</strong><br>
  @endif
  Cliente: {{ optional($factura->cliente)->nombre ?? 'Sin cliente' }}<br>
  @if(optional($factura->cliente)->identificacion)
    ID: {{ optional($factura->cliente)->identificacion }}<br>
  @endif
  Fecha: {{ $fechaDoc->format('Y-m-d H:i') }}<br>
  Pago: {{ $esCredito ? 'Credito' : 'Contado' }}<br>
  @if($esCredito)
    Vence: {{ $vencCarbon ? $vencCarbon->format('Y-m-d') : '-' }}<br>
  @endif

  {{-- Datos domicilio --}}
  @if($esDomicilio)
    <div class="sep">------------------------</div>
    <strong>🛵 DOMICILIO</strong><br>
    @if($factura->dom_nombre)
      Destinatario: {{ $factura->dom_nombre }}<br>
    @endif
    @if($factura->dom_telefono)
      Tel: {{ $factura->dom_telefono }}<br>
    @endif
    @if($factura->dom_direccion)
      Dir: {{ $factura->dom_direccion }}<br>
    @endif
    @if($factura->dom_observaciones)
      Obs domicilio: {{ $factura->dom_observaciones }}<br>
    @endif
  @endif

  <div class="sep">------------------------</div>
</div>

<table class="items" width="100%">
  @foreach ($factura->detalles as $i => $d)
    <tr><td colspan="2">{{ $i + 1 }}. {{ $d->descripcion_larga }}</td></tr>
    <tr>
      <td>Cantidad: {{ $fmtCant($d->cantidad) }}</td>
      <td style="text-align:right;">${{ number_format($d->subtotal, 0, ',', '.') }}</td>
    </tr>
  @endforeach
</table>

<br>
<div style="text-align:right;">
  @if($mostrarIvaSeparado)
    Subtotal: ${{ number_format($subtotalBaseSalida, 0, ',', '.') }}<br>
    IVA: ${{ number_format($ivaTotalSalida, 0, ',', '.') }}<br>
  @endif
  @if($costoEmpaque > 0)
    Subtotal productos: ${{ number_format($subtotalProductos, 0, ',', '.') }}<br>
    @if($esDomicilio)
      @if($costoDesechables > 0)
        Desechables: ${{ number_format($costoDesechables, 0, ',', '.') }}<br>
      @endif
      Domicilio: ${{ number_format($costoDomicilio, 0, ',', '.') }}<br>
    @else
      Desechables / empaque: ${{ number_format($costoEmpaque, 0, ',', '.') }}<br>
    @endif
  @endif
  <div style="border-top:1px solid #111; margin-top:4px; padding-top:4px;">
    <strong style="font-size:16px;">Total: ${{ number_format($factura->total, 0, ',', '.') }}</strong>
  </div>
</div>

@if($factura->propina_sugerida > 0)
  {{-- Solo sugerencia para el cliente: no esta incluida en el Total ni se cobra --}}
  <div style="text-align:right; font-size:11px; margin-top:2px;">
    Propina sugerida (voluntaria): ${{ number_format($factura->propina_sugerida, 0, ',', '.') }}
  </div>
@endif

@if($factura->observaciones)
  <div style="margin-top:8px;">
    <strong>Obs:</strong> {{ $factura->observaciones }}
  </div>
@endif

@if($esElectronica)
  <div class="factus-box small">
    @if(strtolower((string) $estadoDocumentoElectronico) !== 'validada')
      <div><strong>Estado:</strong> {{ strtoupper($estadoDocumentoElectronico ?: 'pendiente') }}</div>
    @endif
    @if($cufeDocumentoElectronico)
      <div><strong>CUFE:</strong></div>
      <div class="break">{{ $cufeDocumentoElectronico }}</div>
    @endif
    @if($qrImagenDocumentoElectronico)
      <div class="center" style="margin-top:6px;"><strong>QR DIAN</strong></div>
      <img class="qr" src="{{ trim($qrImagenDocumentoElectronico) }}" alt="QR DIAN">
    @endif
  </div>
@endif

<div class="footer">
  <div class="sep">------------------------</div>
  Gracias!

  @if($nombreVendedorAsignado || $nombreCajero)
    <div style="margin-top:10px; font-size:11px; text-align:left;">
        @if($nombreVendedorAsignado)
            <div>Vendedor: {{ $nombreVendedorAsignado }}</div>
        @endif
        @if($nombreCajero)
            <div>Cajero: {{ $nombreCajero }}</div>
        @endif
    </div>
  @endif
</div>

@if($esElectronica)
  <div class="advertencia">Factura electronica validada</div>
@else
  <div class="advertencia">Documento no fiscal - salida de mercancia</div>
@endif

<script>
  window.onload = () => {
    window.print();
    setTimeout(() => window.close(), 1200);
  };
</script>
</body>
</html>
