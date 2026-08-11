<?php

namespace App\Support;

use App\Models\Factura;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Calcula los datos derivados que ambas vistas de impresion (tira POS y
 * tamano carta) necesitan a partir de una Factura -- se centraliza aqui
 * en vez de un @include de Blade porque las variables que se calculan
 * DENTRO de un parcial incluido no se comparten de vuelta a la vista
 * principal (@include solo pasa datos hacia adentro, no hacia afuera).
 *
 * @return array<string, mixed>
 */
class FacturaImpresionData
{
    public static function calcular(Factura $factura): array
    {
        $config = $factura->configuracionEmpresa ?? null;
        $esDomicilio = ($factura->tipo_pedido ?? '') === 'domicilio';
        $esParaLlevar = ($factura->tipo_pedido ?? '') === 'para_llevar';
        $costoEmpaque = (float) ($factura->costo_empaque ?? 0);
        $costoDomicilio = (float) ($factura->dom_costo_domicilio ?? 0);
        $costoDesechables = (float) ($factura->dom_costo_desechables ?? 0);

        // Fallback: si no hay columnas separadas usar costo_empaque completo como domicilio
        if ($esDomicilio && $costoDomicilio === 0.0 && $costoDesechables === 0.0 && $costoEmpaque > 0) {
            $costoDomicilio = $costoEmpaque;
        }

        $subtotalProductos = $factura->total - $costoEmpaque;
        $logoUrl = $config?->logo ? Storage::disk('public')->url($config->logo) : null;
        $fechaDoc = $factura->fecha ? Carbon::parse($factura->fecha) : $factura->created_at;
        $tipoPago = strtolower($factura->tipo_pago ?? 'contado');
        $esCredito = $tipoPago === 'credito';
        $vencCarbon = $esCredito && $factura->fecha_vencimiento ? Carbon::parse($factura->fecha_vencimiento) : null;
        $esElectronica = $factura->tipo_factura === 'electronica';

        // El ticket no debe amarrarse a un proveedor de facturacion
        // electronica en particular (Factus o el proveedor alterno UBL
        // 2.1, ver App\Services\Ubl21): estas variables toman lo que haya
        // quedado guardado en la factura, sin importar cual de los dos la
        // valido -- ver App\Services\Ventas\FacturarVentaService::validarFacturaElectronica().
        $factusBill = data_get($factura->factus_response, 'data.bill', []);
        $numeroDocumentoElectronico = $factura->factus_number ?: data_get($factusBill, 'number') ?: $factura->ubl21_document_number;
        $cufeDocumentoElectronico = $factura->factus_cufe ?: data_get($factusBill, 'cufe') ?: $factura->ubl21_cufe;
        $qrImagenDocumentoElectronico = data_get($factusBill, 'qr_image');
        $qrTextoDocumentoElectronico = data_get($factusBill, 'qr') ?: $factura->ubl21_qr;
        $urlDocumentoElectronico = data_get($factusBill, 'public_url') ?: $factura->ubl21_pdf_url;
        $validadoDocumentoElectronico = $factura->factus_validated_at ?: data_get($factusBill, 'validated') ?: $factura->ubl21_validated_at;
        $estadoDocumentoElectronico = $factura->factus_status ?: $factura->ubl21_status;
        $resolucionDianElectronica = $config?->numero_resolucion ?: $config?->ubl21_resolution_number;
        $prefijoElectronico = $config?->prefijo ?: $config?->ubl21_prefix;
        $rangoDesdeElectronico = $config?->rango_desde ?: $config?->ubl21_numbering_from;
        $rangoHastaElectronico = $config?->rango_hasta ?: $config?->ubl21_numbering_to;
        $fmtCant = function ($cantidad) {
            $value = (float) $cantidad;

            return rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',');
        };

        $mostrarIvaSeparado = (bool) ($config->mostrar_iva_separado ?? false);
        $subtotalBaseSalida = 0;
        $ivaTotalSalida = 0;

        if ($mostrarIvaSeparado) {
            $idsProductosSalida = $factura->detalles->pluck('producto_id')->filter(fn ($id) => is_numeric($id))->unique();
            $ivaPorProductoSalida = Product::where('empresa_id', $factura->empresa_id)
                ->whereIn('id_producto', $idsProductosSalida)
                ->pluck('iva_venta', 'id_producto');

            foreach ($factura->detalles as $d) {
                $ivaPctSalida = (float) ($ivaPorProductoSalida[$d->producto_id] ?? 0);
                $baseLineaSalida = $ivaPctSalida > 0 ? round($d->subtotal / (1 + $ivaPctSalida / 100), 2) : (float) $d->subtotal;
                $subtotalBaseSalida += $baseLineaSalida;
                $ivaTotalSalida += round($d->subtotal - $baseLineaSalida, 2);
            }
        }

        $nombreCajero = optional($factura->cajero)->name ?: optional($factura->vendedor)->name;
        $nombreVendedorAsignado = optional($factura->vendedorAsignado)->name;

        return compact(
            'config',
            'esDomicilio',
            'esParaLlevar',
            'costoEmpaque',
            'costoDomicilio',
            'costoDesechables',
            'subtotalProductos',
            'logoUrl',
            'fechaDoc',
            'tipoPago',
            'esCredito',
            'vencCarbon',
            'esElectronica',
            'numeroDocumentoElectronico',
            'cufeDocumentoElectronico',
            'qrTextoDocumentoElectronico',
            'qrImagenDocumentoElectronico',
            'urlDocumentoElectronico',
            'validadoDocumentoElectronico',
            'estadoDocumentoElectronico',
            'resolucionDianElectronica',
            'prefijoElectronico',
            'rangoDesdeElectronico',
            'rangoHastaElectronico',
            'fmtCant',
            'mostrarIvaSeparado',
            'subtotalBaseSalida',
            'ivaTotalSalida',
            'nombreCajero',
            'nombreVendedorAsignado',
        );
    }
}
