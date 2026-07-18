<?php

namespace App\Imports;

use App\Models\Actor;
use App\Models\AlternateCode;
use App\Models\Compra;
use App\Models\CompraDetalle;
use App\Models\Familia;
use App\Models\Product;
use App\Models\Subfamilia;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

// Carga masiva de UNA factura de compra: el encabezado (proveedor, numero de
// factura, tipo de pago, fechas) se llena una sola vez en la pantalla
// (ImportarCompras), y el excel solo trae los items (productos) de esa
// factura. Misma matematica que CreateCompra::guardarCompra() para que el
// resultado quede identico a como si se hubiera cargado a mano y confirmado.
class CompraBulkImport implements ToCollection, WithHeadingRow, WithMultipleSheets
{
    protected int $creados = 0;
    protected array $errores = [];
    protected ?Compra $compra = null;

    public function __construct(
        protected int $empresaId,
        protected int $userId,
        protected int $proveedorActorId,   // actors.id -> compras.proveedor_id
        protected int $proveedorClip,      // actors.id_clip_pro -> products.id_proveedor
        protected string $numeroFactura,
        protected string $tipoPago,        // contado | credito
        protected string $fecha,
        protected string $fechaVencimiento,
    ) {
    }

    public function sheets(): array
    {
        return ['Items' => $this];
    }

    public function collection(Collection $rows)
    {
        $siguienteCodigo = (int) (Product::where('empresa_id', $this->empresaId)->max('id_producto') ?? 10001) + 1;

        $lineas = [];
        $subtotal = 0;
        $descuentoTotal = 0;
        $impuestoTotal = 0;
        $total = 0;

        foreach ($rows as $index => $rawRow) {
            $row = collect($rawRow)->mapWithKeys(fn ($value, $key) => [strtolower(trim((string) $key)) => $value]);
            $numeroFila = $index + 2;

            if ($row->filter(fn ($value) => trim((string) $value) !== '')->isEmpty()) {
                continue;
            }

            $nombre = trim((string) ($row['producto'] ?? ''));

            if (str_starts_with(mb_strtolower($nombre), 'ejemplo')) {
                continue;
            }

            $cantidad = $this->numero($row['cantidad'] ?? null);
            $costo = $this->numero($row['costo_unitario'] ?? null);

            if ($nombre === '') {
                $this->errores[] = "Fila {$numeroFila}: falta el nombre del producto.";
                continue;
            }

            if ($cantidad === null || $cantidad <= 0) {
                $this->errores[] = "Fila {$numeroFila} ({$nombre}): falta la Cantidad (o es 0).";
                continue;
            }

            if ($costo === null) {
                $this->errores[] = "Fila {$numeroFila} ({$nombre}): falta el Costo Unitario.";
                continue;
            }

            [$producto, $esNuevo, $siguienteCodigo] = $this->resolveProducto($nombre, $row, $siguienteCodigo);

            $descP = $this->numero($row['descuento_comercial'] ?? null) ?? 0;
            $iva = $this->numero($row['iva'] ?? null) ?? ($esNuevo ? 19.0 : (float) $producto->iva_compra);

            $precioVentaExcel = $this->numero($row['precio_de_venta'] ?? null);
            $utilidadExcel = $this->numero($row['utilidad'] ?? null);

            $lineaBruta = $cantidad * $costo;
            $lineaDescuento = $lineaBruta * ($descP / 100);
            $lineaBase = $lineaBruta - $lineaDescuento;
            $lineaImpuesto = $lineaBase * ($iva / 100);
            $lineaTotal = $lineaBase + $lineaImpuesto;

            $subtotal += $lineaBruta;
            $descuentoTotal += $lineaDescuento;
            $impuestoTotal += $lineaImpuesto;
            $total += $lineaTotal;

            $costoConDesc = round($costo * (1 - $descP / 100), 2);
            $costoConIva = round($costoConDesc * (1 + $iva / 100), 2);

            if ($precioVentaExcel !== null && $precioVentaExcel > 0) {
                $pv = $precioVentaExcel;
                $util = $utilidadExcel ?? round((($pv - $costoConIva) / max($pv, 0.01)) * 100, 2);
            } else {
                $util = $utilidadExcel ?? ($esNuevo ? 30.0 : (float) $producto->utilidad1);
                $pv = round($costoConIva * (1 + $util / 100), 2);
            }

            $precioVentaAnterior = (float) ($producto->precio_venta1 ?? 0);

            $producto->existencias = (float) ($producto->existencias ?? 0) + $cantidad;
            $producto->precio_costo_anterior = $producto->precio_costo;
            $producto->precio_venta_anterior = $producto->precio_venta1;
            $producto->precio_costo = $costo;
            $producto->descuento_comercial = $descP;
            $producto->precio_con_descuento = $costoConDesc;
            $producto->costo_iva = $costoConIva;
            $producto->iva_compra = $iva;
            $producto->iva_venta = $iva;
            $producto->utilidad1 = $util;
            $producto->precio_venta1 = $pv;
            $producto->save();

            $lineas[] = [
                'product_id' => (string) $producto->id_producto,
                'codigo_ingresado' => (string) $producto->id_producto,
                'nombre_producto' => $producto->descripcion_larga,
                'cantidad' => $cantidad,
                'costo_unitario' => $costo,
                'desc_comercial' => $descP,
                'descuento_pct' => $descP,
                'iva_pct' => $iva,
                'precio_venta_act' => $precioVentaAnterior,
                'utilidad_pct' => $util,
                'precio_venta' => $pv,
                'subtotal' => $lineaBruta,
                'impuesto' => $lineaImpuesto,
                'total' => $lineaTotal,
            ];

            $this->creados++;
        }

        if (empty($lineas)) {
            return;
        }

        DB::transaction(function () use ($lineas, $subtotal, $descuentoTotal, $impuestoTotal, $total) {
            $compra = Compra::create([
                'empresa_id' => $this->empresaId,
                'proveedor_id' => $this->proveedorActorId,
                'user_id' => $this->userId,
                'estado' => 'confirmada',
                'tipo_pago' => $this->tipoPago,
                'fecha' => $this->fecha,
                'fecha_vencimiento' => $this->fechaVencimiento,
                'numero_factura' => $this->numeroFactura,
                'subtotal' => $subtotal,
                'descuento_total' => $descuentoTotal,
                'impuesto_total' => $impuestoTotal,
                'total' => $total,
                'saldo' => $this->tipoPago === 'credito' ? $total : 0,
            ]);

            foreach ($lineas as $linea) {
                $linea['compra_id'] = $compra->id;
                CompraDetalle::create($linea);
            }

            $this->compra = $compra;
        });
    }

    public function resumen(): array
    {
        return [
            'compra' => $this->compra,
            'items' => $this->creados,
            'errores' => $this->errores,
        ];
    }

    private function numero($value): ?float
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $value = str_replace(',', '.', (string) $value);

        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * @return array{0: Product, 1: bool, 2: int} [producto resuelto/creado, es_nuevo, siguiente codigo disponible]
     */
    private function resolveProducto(string $nombre, Collection $row, int $siguienteCodigo): array
    {
        $producto = Product::query()
            ->where('empresa_id', $this->empresaId)
            ->where('id_proveedor', $this->proveedorClip)
            ->whereRaw('LOWER(descripcion_larga) = ?', [mb_strtolower($nombre)])
            ->first();

        if ($producto) {
            return [$producto, false, $siguienteCodigo];
        }

        $idFamilia1 = $this->resolveFamilia(trim((string) ($row['departamento'] ?? '')));
        $idFamilia2 = $this->resolveSubfamilia(trim((string) ($row['subfamilia'] ?? '')), $idFamilia1);
        $idUnidad = $this->resolveUnidad(trim((string) ($row['unidad_de_medida'] ?? '')));

        $producto = Product::create([
            'empresa_id' => $this->empresaId,
            'id_producto' => $siguienteCodigo,
            'descripcion_larga' => $nombre,
            'id_proveedor' => $this->proveedorClip,
            'id_familia1' => $idFamilia1,
            'id_familia2' => $idFamilia2,
            'id_unidad_de_medida' => $idUnidad,
            'precio_costo' => 0,
            'descuento_comercial' => 0,
            'iva_compra' => 19,
            'iva_venta' => 19,
            'utilidad1' => 0,
            'precio_venta1' => 0,
            'existencias' => 0,
            'tipo_producto' => 'producto',
            'vende_por' => 'unidad',
            'maneja_inventario' => true,
            'mostrar_en_catalogo' => true,
        ]);

        AlternateCode::create([
            'empresa_id' => $this->empresaId,
            'product_id' => $producto->id,
            'code' => (string) $producto->id_producto,
        ]);

        return [$producto, true, $siguienteCodigo + 1];
    }

    private function resolveFamilia(string $nombre): int
    {
        if ($nombre === '') {
            return (int) Familia::query()->firstOrCreate(
                ['empresa_id' => $this->empresaId, 'nombre' => 'FAMILIA DE PRUEBA'],
            )->id;
        }

        $familia = Familia::query()
            ->where('empresa_id', $this->empresaId)
            ->whereRaw('LOWER(nombre) = ?', [mb_strtolower($nombre)])
            ->first();

        if ($familia) {
            return (int) $familia->id;
        }

        return (int) Familia::create(['empresa_id' => $this->empresaId, 'nombre' => $nombre])->id;
    }

    private function resolveSubfamilia(string $nombre, int $familiaId): int
    {
        if ($nombre === '') {
            return (int) Subfamilia::query()->firstOrCreate([
                'empresa_id' => $this->empresaId,
                'id_familia1' => $familiaId,
                'nombre' => 'SUBFAMILIA DE PRUEBA',
            ])->id_familia2;
        }

        $subfamilia = Subfamilia::query()
            ->where('empresa_id', $this->empresaId)
            ->where('id_familia1', $familiaId)
            ->whereRaw('LOWER(nombre) = ?', [mb_strtolower($nombre)])
            ->first();

        if ($subfamilia) {
            return (int) $subfamilia->id_familia2;
        }

        return (int) Subfamilia::create([
            'empresa_id' => $this->empresaId,
            'id_familia1' => $familiaId,
            'nombre' => $nombre,
        ])->id_familia2;
    }

    private function resolveUnidad(string $texto): int
    {
        $mapa = [
            'pieza' => 1, 'piezas' => 1, 'unidad' => 1, 'u' => 1,
            'kilogramo' => 2, 'kilogramos' => 2, 'kg' => 2,
            'litro' => 3, 'litros' => 3, 'l' => 3,
            'metro' => 4, 'metros' => 4, 'm' => 4,
            'hora' => 5, 'horas' => 5, 'h' => 5,
        ];

        return $mapa[mb_strtolower($texto)] ?? 1;
    }
}
