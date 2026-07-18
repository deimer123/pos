<?php

namespace App\Imports;

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
//
// Todo o nada: primero se valida CADA fila sin escribir nada en la base de
// datos; si una sola fila falla, no se crea la compra ni se toca ningun
// producto -- asi se puede corregir el excel y volver a subir el MISMO
// archivo con el mismo numero de factura sin chocar con "factura duplicada"
// (antes se creaba la compra solo con las filas validas, dejando la compra
// incompleta y bloqueando un segundo intento).
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
        $pendientes = $this->validar($rows);

        if (! empty($this->errores)) {
            // Alguna fila fallo: no se crea nada, ni la compra ni productos.
            return;
        }

        if (empty($pendientes)) {
            return;
        }

        $this->confirmar($pendientes);
    }

    /**
     * Pase 1: solo lectura. Valida cada fila y calcula todos los valores,
     * sin crear/actualizar nada en la base de datos todavia.
     */
    private function validar(Collection $rows): array
    {
        $pendientes = [];

        foreach ($rows as $index => $rawRow) {
            $numeroFila = $index + 2;

            $row = collect($rawRow)->mapWithKeys(fn ($value, $key) => [strtolower(trim((string) $key)) => $value]);

            $nombre = trim((string) ($row['producto'] ?? ''));
            $cantidad = $this->numero($row['cantidad'] ?? null);
            $costo = $this->numero($row['costo_unitario'] ?? null);

            // Fila sin tocar: se decide SOLO por las columnas que uno
            // realmente escribe (Producto/Cantidad/Costo), no por todas.
            // Las demas columnas tienen formula y su valor calculado no
            // siempre se lee igual que su resultado (a veces llega el
            // texto de la formula en vez de "", segun como se guardo el
            // archivo) -- si se revisaran esas tambien, una fila vacia de
            // verdad podia salir marcada como "con datos" por error.
            if ($nombre === '' && $cantidad === null && $costo === null) {
                continue;
            }

            if (str_starts_with(mb_strtolower($nombre), 'ejemplo')) {
                continue;
            }

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

            $productoExistente = $this->buscarProducto($nombre);
            $esNuevo = $productoExistente === null;

            $descP = $this->numero($row['descuento_comercial'] ?? null) ?? 0;
            $iva = $this->numero($row['iva'] ?? null) ?? ($esNuevo ? 19.0 : (float) $productoExistente->iva_compra);

            $precioVentaExcel = $this->numero($row['precio_de_venta'] ?? null);
            $utilidadExcel = $this->numero($row['utilidad'] ?? null);

            $costoConDesc = round($costo * (1 - $descP / 100), 2);
            $costoConIva = round($costoConDesc * (1 + $iva / 100), 2);

            if ($precioVentaExcel !== null && $precioVentaExcel > 0) {
                $pv = $precioVentaExcel;
                $util = $utilidadExcel ?? round((($pv - $costoConIva) / max($pv, 0.01)) * 100, 2);
            } else {
                // Respaldo por si el excel llega sin la formula de Precio de
                // Venta (ej. csv, o el usuario la borro). Misma formula que
                // la plantilla: margen sobre el precio de venta (no markup
                // sobre el costo), redondeado a la centena.
                $util = $utilidadExcel ?? ($esNuevo ? 30.0 : (float) $productoExistente->utilidad1);
                $pv = $util >= 100
                    ? $costoConIva
                    : round($costoConIva / (1 - $util / 100) / 100) * 100;
            }

            $pendientes[] = [
                'numero_fila' => $numeroFila,
                'nombre' => $nombre,
                'es_nuevo' => $esNuevo,
                'departamento' => trim((string) ($row['departamento'] ?? '')),
                'subfamilia' => trim((string) ($row['subfamilia'] ?? '')),
                'unidad_texto' => trim((string) ($row['unidad_de_medida'] ?? '')),
                'cantidad' => $cantidad,
                'costo' => $costo,
                'descuento' => $descP,
                'iva' => $iva,
                'utilidad' => $util,
                'precio_venta' => $pv,
                'costo_con_descuento' => $costoConDesc,
                'costo_con_iva' => $costoConIva,
            ];
        }

        return $pendientes;
    }

    /**
     * Pase 2: solo se llega aqui si TODAS las filas del pase 1 fueron
     * validas. Aqui si se crean/actualizan productos y la compra.
     */
    private function confirmar(array $pendientes): void
    {
        $siguienteCodigo = (int) (Product::where('empresa_id', $this->empresaId)->max('id_producto') ?? 10001) + 1;

        DB::transaction(function () use ($pendientes, &$siguienteCodigo) {
            $lineas = [];
            $subtotal = 0;
            $descuentoTotal = 0;
            $impuestoTotal = 0;
            $total = 0;

            foreach ($pendientes as $p) {
                [$producto, $siguienteCodigo] = $this->resolveProducto(
                    $p['nombre'],
                    $p['departamento'],
                    $p['subfamilia'],
                    $p['unidad_texto'],
                    $siguienteCodigo,
                );

                $lineaBruta = $p['cantidad'] * $p['costo'];
                $lineaDescuento = $lineaBruta * ($p['descuento'] / 100);
                $lineaBase = $lineaBruta - $lineaDescuento;
                $lineaImpuesto = $lineaBase * ($p['iva'] / 100);
                $lineaTotal = $lineaBase + $lineaImpuesto;

                $subtotal += $lineaBruta;
                $descuentoTotal += $lineaDescuento;
                $impuestoTotal += $lineaImpuesto;
                $total += $lineaTotal;

                $precioVentaAnterior = (float) ($producto->precio_venta1 ?? 0);

                $producto->existencias = (float) ($producto->existencias ?? 0) + $p['cantidad'];
                $producto->precio_costo_anterior = $producto->precio_costo;
                $producto->precio_venta_anterior = $producto->precio_venta1;
                $producto->precio_costo = $p['costo'];
                $producto->descuento_comercial = $p['descuento'];
                $producto->precio_con_descuento = $p['costo_con_descuento'];
                $producto->costo_iva = $p['costo_con_iva'];
                $producto->iva_compra = $p['iva'];
                $producto->iva_venta = $p['iva'];
                $producto->utilidad1 = $p['utilidad'];
                $producto->precio_venta1 = $p['precio_venta'];
                $producto->save();

                $lineas[] = [
                    'product_id' => (string) $producto->id_producto,
                    'codigo_ingresado' => (string) $producto->id_producto,
                    'nombre_producto' => $producto->descripcion_larga,
                    'cantidad' => $p['cantidad'],
                    'costo_unitario' => $p['costo'],
                    'desc_comercial' => $p['descuento'],
                    'descuento_pct' => $p['descuento'],
                    'iva_pct' => $p['iva'],
                    'precio_venta_act' => $precioVentaAnterior,
                    'utilidad_pct' => $p['utilidad'],
                    'precio_venta' => $p['precio_venta'],
                    'subtotal' => $lineaBruta,
                    'impuesto' => $lineaImpuesto,
                    'total' => $lineaTotal,
                ];

                $this->creados++;
            }

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

    // Solo lectura: busca el producto por nombre, primero con ESTE
    // proveedor y si no con cualquier otro (el nombre es unico por
    // empresa). No crea ni modifica nada.
    private function buscarProducto(string $nombre): ?Product
    {
        return Product::query()
            ->where('empresa_id', $this->empresaId)
            ->where('id_proveedor', $this->proveedorClip)
            ->whereRaw('LOWER(descripcion_larga) = ?', [mb_strtolower($nombre)])
            ->first()
            ?? Product::query()
                ->where('empresa_id', $this->empresaId)
                ->whereRaw('LOWER(descripcion_larga) = ?', [mb_strtolower($nombre)])
                ->first();
    }

    /**
     * @return array{0: Product, 1: int} [producto resuelto/creado, siguiente codigo disponible]
     */
    private function resolveProducto(string $nombre, string $departamento, string $subfamilia, string $unidadTexto, int $siguienteCodigo): array
    {
        $producto = $this->buscarProducto($nombre);

        if ($producto) {
            // Si existe pero con OTRO proveedor, se reasigna a este --el
            // nombre es unico por empresa sin importar proveedor, no se
            // puede crear un duplicado, y en la vida real esto es
            // simplemente que el producto cambio de proveedor.
            if ((int) $producto->id_proveedor !== $this->proveedorClip) {
                $producto->id_proveedor = $this->proveedorClip;
            }

            return [$producto, $siguienteCodigo];
        }

        $idFamilia1 = $this->resolveFamilia($departamento);
        $idFamilia2 = $this->resolveSubfamilia($subfamilia, $idFamilia1);
        $idUnidad = $this->resolveUnidad($unidadTexto);

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

        return [$producto, $siguienteCodigo + 1];
    }

    // Si el nombre viene con pinta de formula (empieza con =, +, - o @),
    // se trata como si viniera vacio en vez de crear un Departamento o
    // Subfamilia con ese texto -- eso paso una vez por un bug ya
    // corregido (se leyo el texto de una formula en vez de su
    // resultado), y dejo un nombre corrupto guardado.
    private function textoValido(string $nombre): string
    {
        return preg_match('/^[=+\-@]/', $nombre) ? '' : $nombre;
    }

    private function resolveFamilia(string $nombre): int
    {
        $nombre = $this->textoValido($nombre);

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
        $nombre = $this->textoValido($nombre);

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
