<?php

namespace App\Imports;

use App\Models\Actor;
use App\Models\AlternateCode;
use App\Models\Familia;
use App\Models\Product;
use App\Models\ProductoVariante;
use App\Models\Subfamilia;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

// Cargue masivo de productos con variantes (talla/color), para empresas con
// tipo_negocio = ropa_calzado. Cada fila es UNA variante; varias filas con
// el mismo "Nombre del producto" se agrupan en un solo producto con varias
// variantes. Igual que CompraBulkImport: primero se valida TODO (pase 1,
// sin tocar la base de datos) y solo si no hay ningun error se crea algo
// (pase 2) -- asi se puede corregir el excel y volver a subir el mismo
// archivo sin dejar productos a medias.
class ProductoRopaBulkImport implements ToCollection, WithHeadingRow, WithMultipleSheets
{
    protected int $creados = 0;
    protected int $variantesCreadas = 0;
    protected array $errores = [];

    public function __construct(protected int $empresaId)
    {
    }

    public function sheets(): array
    {
        return ['Productos' => $this];
    }

    public function collection(Collection $rows)
    {
        $grupos = $this->agrupar($rows);

        if (! empty($this->errores)) {
            return;
        }

        if (empty($grupos)) {
            return;
        }

        $this->confirmar($grupos);
    }

    /**
     * Pase 1: solo lectura. Agrupa las filas por nombre de producto y
     * valida cada grupo completo, sin escribir nada en la base de datos.
     */
    private function agrupar(Collection $rows): array
    {
        $grupos = [];

        foreach ($rows as $index => $rawRow) {
            $numeroFila = $index + 2;
            $row = collect($rawRow)->mapWithKeys(fn ($value, $key) => [strtolower(trim((string) $key)) => $value]);

            // Fila sin tocar: se decide SOLO por las columnas que uno
            // realmente escribe (Nombre/Talla/Color), no por todas.
            // Precio de Venta trae formula en las 500 filas de la plantilla
            // (ver ProductoRopaBulkTemplatePlantillaSheet), asi que esa
            // celda nunca esta "vacia" de verdad -- si se revisaran todas
            // las columnas, una fila vacia de verdad salia marcada como
            // "con datos" por error (mismo bug que ya se corrigio en
            // CompraBulkImport).
            $nombreRaw = trim((string) ($row['nombre_del_producto'] ?? ''));
            $tallaRaw = trim((string) ($row['talla'] ?? ''));
            $colorRaw = trim((string) ($row['color'] ?? ''));

            if ($nombreRaw === '' && $tallaRaw === '' && $colorRaw === '') {
                continue;
            }

            $nombre = $this->textoValido($nombreRaw);

            if (str_starts_with(mb_strtolower($nombre), 'ejemplo')) {
                continue;
            }

            if ($nombre === '') {
                $this->errores[] = "Fila {$numeroFila}: falta el nombre del producto.";
                continue;
            }

            $talla = trim((string) ($row['talla'] ?? ''));
            $color = trim((string) ($row['color'] ?? ''));

            if ($talla === '' && $color === '') {
                $this->errores[] = "Fila {$numeroFila} ({$nombre}): falta Talla o Color (al menos uno de los dos).";
                continue;
            }

            $clave = mb_strtolower($nombre);

            if (! isset($grupos[$clave])) {
                $precioCosto = $this->numero($row['precio_costo'] ?? null);
                $precioVentaExcel = $this->numero($row['precio_de_venta'] ?? null);
                $utilidadExcel = $this->numero($row['utilidad'] ?? null);

                if ($precioCosto === null) {
                    $this->errores[] = "Producto \"{$nombre}\" (fila {$numeroFila}): falta el Precio Costo.";
                    continue;
                }

                if ($precioVentaExcel === null && $utilidadExcel === null) {
                    $this->errores[] = "Producto \"{$nombre}\" (fila {$numeroFila}): falta la Utilidad (o el Precio de Venta).";
                    continue;
                }

                if (Product::where('empresa_id', $this->empresaId)->where('descripcion_larga', $nombre)->exists()) {
                    $this->errores[] = "Fila {$numeroFila}: ya existe un producto llamado \"{$nombre}\" en tu empresa.";
                    continue;
                }

                $descuento = $this->numero($row['descuento_comercial'] ?? null) ?? 0;
                $ivaCompra = $this->numero($row['iva_compra'] ?? null) ?? 19;
                $ivaVenta = $this->numero($row['iva_venta'] ?? null) ?? 19;

                // Precio de venta a partir de la Utilidad (igual que en la
                // plantilla de Compras); si en vez de Utilidad viene el
                // Precio de Venta ya escrito, se usa ese y se calcula la
                // utilidad de respaldo.
                $costoConDescuento = round($precioCosto * (1 - $descuento / 100), 2);
                $costoIva = round($costoConDescuento * (1 + $ivaVenta / 100), 2);

                if ($precioVentaExcel !== null && $precioVentaExcel > 0) {
                    $precioVenta = $precioVentaExcel;
                    $utilidad1 = $utilidadExcel ?? round((($precioVenta - $costoIva) / max($precioVenta, 0.01)) * 100, 2);
                } else {
                    $utilidad1 = $utilidadExcel;
                    $precioVenta = $utilidad1 >= 100
                        ? $costoIva
                        : round($costoIva / (1 - $utilidad1 / 100) / 100) * 100;
                }

                $grupos[$clave] = [
                    'nombre' => $nombre,
                    'proveedor' => trim((string) ($row['proveedor'] ?? '')),
                    'departamento' => trim((string) ($row['departamento'] ?? '')),
                    'subfamilia' => trim((string) ($row['subfamilia'] ?? '')),
                    'precio_costo' => $precioCosto,
                    'descuento' => $descuento,
                    'iva_compra' => $ivaCompra,
                    'iva_venta' => $ivaVenta,
                    'precio_venta' => $precioVenta,
                    'utilidad1' => $utilidad1,
                    'variantes' => [],
                ];
            }

            $clavesVariante = collect($grupos[$clave]['variantes'])->map(fn ($v) => mb_strtolower($v['talla'].'|'.$v['color']));
            if ($clavesVariante->contains(mb_strtolower($talla.'|'.$color))) {
                $this->errores[] = "Fila {$numeroFila} ({$nombre}): la combinacion Talla \"{$talla}\" / Color \"{$color}\" esta repetida.";
                continue;
            }

            $grupos[$clave]['variantes'][] = [
                'talla' => $talla,
                'color' => $color,
            ];
        }

        return $grupos;
    }

    /**
     * Pase 2: solo se llega aqui si TODOS los grupos del pase 1 fueron
     * validos. Aqui si se crean los productos y sus variantes.
     */
    private function confirmar(array $grupos): void
    {
        $siguienteCodigo = (int) (Product::where('empresa_id', $this->empresaId)->max('id_producto') ?? 10001) + 1;

        DB::transaction(function () use ($grupos, &$siguienteCodigo) {
            foreach ($grupos as $grupo) {
                $idFamilia1 = $this->resolveFamilia($grupo['departamento']);
                $idFamilia2 = $this->resolveSubfamilia($grupo['subfamilia'], $idFamilia1);
                $idProveedor = $this->resolveProveedor($grupo['proveedor']);

                $costoConDescuento = round($grupo['precio_costo'] * (1 - $grupo['descuento'] / 100), 2);
                $costoIva = round($costoConDescuento * (1 + $grupo['iva_venta'] / 100), 2);

                $producto = Product::create([
                    'empresa_id' => $this->empresaId,
                    'id_producto' => $siguienteCodigo,
                    'descripcion_larga' => $grupo['nombre'],
                    'id_proveedor' => $idProveedor,
                    'id_familia1' => $idFamilia1,
                    'id_familia2' => $idFamilia2,
                    'id_unidad_de_medida' => 1,
                    'precio_costo' => $grupo['precio_costo'],
                    'descuento_comercial' => $grupo['descuento'],
                    'precio_con_descuento' => $costoConDescuento,
                    'iva_compra' => $grupo['iva_compra'],
                    'iva_venta' => $grupo['iva_venta'],
                    'costo_iva' => $costoIva,
                    'utilidad1' => $grupo['utilidad1'],
                    'precio_venta1' => $grupo['precio_venta'],
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

                $codigosEnUso = [];

                foreach ($grupo['variantes'] as $v) {
                    $nombreVariante = trim(
                        ($v['talla'] !== '' ? 'Talla '.$v['talla'] : '').
                        ($v['talla'] !== '' && $v['color'] !== '' ? ' - ' : '').
                        $v['color'],
                        ' -'
                    );

                    // Codigo propio para cada variante (producto + talla +
                    // color), asi todas quedan con codigo sin obligar a
                    // cargarlo aparte despues.
                    $codigo = \App\Support\VariantCodeGenerator::sugerir(
                        (string) $producto->id_producto,
                        $v['talla'],
                        $v['color'],
                        $codigosEnUso
                    );
                    $codigosEnUso[] = $codigo;

                    // Stock siempre en 0: el cargue de productos no ingresa
                    // existencias, eso se hace despues por la plantilla de
                    // Compras (que reconoce Talla/Color de la variante).
                    ProductoVariante::create([
                        'empresa_id' => $this->empresaId,
                        'product_id' => $producto->id,
                        'nombre' => $nombreVariante,
                        'atributos' => ['talla' => $v['talla'], 'color' => $v['color']],
                        'codigo' => $codigo,
                        'precio_extra' => 0,
                        'stock' => 0,
                        'activo' => true,
                    ]);

                    $this->variantesCreadas++;
                }

                $this->creados++;
                $siguienteCodigo++;
            }
        });
    }

    public function resumen(): array
    {
        return [
            'creados' => $this->creados,
            'variantes_creadas' => $this->variantesCreadas,
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

    private function textoValido(string $nombre): string
    {
        return preg_match('/^[=+\-@]/', $nombre) ? '' : $nombre;
    }

    private function resolveProveedor(string $nombre): int
    {
        $nombre = $this->textoValido($nombre);

        if ($nombre === '') {
            return (int) $this->defaultProveedor()->id_clip_pro;
        }

        $proveedor = Actor::query()
            ->where('empresa_id', $this->empresaId)
            ->whereIn('clasificacion', ['proveedor', 'cliente_proveedor'])
            ->whereRaw('LOWER(nombre) = ?', [mb_strtolower($nombre)])
            ->first();

        if ($proveedor) {
            return (int) $proveedor->id_clip_pro;
        }

        $nuevoId = (int) (Actor::max('id_clip_pro') ?? 10000) + 1;

        $creado = Actor::create([
            'id_clip_pro' => $nuevoId,
            'empresa_id' => $this->empresaId,
            'tipo_documento_id' => 6,
            'identificacion' => (string) $nuevoId,
            'nombre' => $nombre,
            'razon_social' => $nombre,
            'direccion' => 'N/A',
            'telefono' => '0000000000',
            'email' => null,
            'departamento_id' => 1,
            'ciudad_id' => 1,
            'tipo_persona' => 'juridica',
            'regimen_tributario' => 'simplificado',
            'responsable_iva' => 0,
            'clasificacion' => 'proveedor',
            'tipo' => 3,
        ]);

        return (int) $creado->id_clip_pro;
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

    private function defaultProveedor(): Actor
    {
        $proveedor = Actor::query()
            ->where('empresa_id', $this->empresaId)
            ->whereIn('clasificacion', ['proveedor', 'cliente_proveedor'])
            ->orderByRaw("CASE WHEN nombre = 'PROVEEDOR DE PRUEBA' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->first();

        if ($proveedor) {
            return $proveedor;
        }

        $nuevoId = (int) (Actor::max('id_clip_pro') ?? 10000) + 1;

        return Actor::create([
            'id_clip_pro' => $nuevoId,
            'empresa_id' => $this->empresaId,
            'tipo_documento_id' => 6,
            'identificacion' => '111111111',
            'nombre' => 'PROVEEDOR DE PRUEBA',
            'razon_social' => 'PROVEEDOR DE PRUEBA',
            'direccion' => 'N/A',
            'telefono' => '0000000000',
            'email' => 'proveedor@prueba.com',
            'departamento_id' => 1,
            'ciudad_id' => 1,
            'tipo_persona' => 'juridica',
            'regimen_tributario' => 'simplificado',
            'responsable_iva' => 0,
            'clasificacion' => 'proveedor',
            'tipo' => 3,
        ]);
    }
}
