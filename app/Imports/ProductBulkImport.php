<?php

namespace App\Imports;

use App\Models\Actor;
use App\Models\AlternateCode;
use App\Models\Familia;
use App\Models\Product;
use App\Models\Subfamilia;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

// Importador de la plantilla de carga masiva de productos (self-service,
// distinto del ProductImport.php que usa el superadmin en ImportarDatos.php).
// Diferencias clave: el codigo se autogenera aqui (no viene en el excel),
// y si el nombre de proveedor/departamento/subfamilia no existe se CREA de
// una con ese nombre, en vez de caer siempre al registro "DE PRUEBA" (ese
// solo se usa si la celda quedo vacia).
//
// Igual que ProductoRopaBulkImport/ProductoLoteBulkImport/CompraBulkImport:
// primero se valida TODO el archivo (pase 1, sin crear nada) y solo si NO
// hay ningun error se crean los productos (pase 2, dentro de una
// transaccion) -- si una sola fila esta mal, no se monta ningun producto,
// para poder corregir el excel y volver a subir el mismo archivo completo
// sin dejar la carga a medias.
class ProductBulkImport implements ToCollection, WithHeadingRow, WithMultipleSheets
{
    protected int $empresaId;
    protected int $creados = 0;
    protected array $errores = [];

    public function __construct(int $empresaId)
    {
        $this->empresaId = $empresaId;
    }

    // Sin esto, Laravel Excel procesa TODAS las hojas del archivo (tambien
    // la hoja "Referencia" de la plantilla, que no tiene columna de nombre
    // y por eso salia como fila con error). Se limita a la hoja de datos.
    public function sheets(): array
    {
        return ['Productos' => $this];
    }

    public function collection(Collection $rows)
    {
        [$filas, $errores] = $this->validar($rows);

        $this->errores = $errores;

        if (! empty($errores)) {
            return; // una sola fila mal y no se monta NINGUN producto del archivo
        }

        if (empty($filas)) {
            return;
        }

        $this->crear($filas);
    }

    /**
     * Pase 1: solo lectura. Valida cada fila del excel (sin crear nada
     * todavia) y devuelve [filas validas, errores]. Si $errores no queda
     * vacio, collection() no llama a crear() -- no se monta ni un solo
     * producto del archivo hasta que todas las filas esten bien.
     */
    private function validar(Collection $rows): array
    {
        $siguienteCodigo = (int) (Product::where('empresa_id', $this->empresaId)->max('id_producto') ?? 10001) + 1;
        $filas = [];
        $errores = [];
        // Nombres que ya pasaron la validacion en ESTE mismo archivo (fila
        // => numero), para detectar dos filas con el mismo nombre nuevo
        // aunque ninguna de las dos exista todavia en la base de datos
        // (antes esto se detectaba solo porque la primera ya se habia
        // guardado cuando se validaba la segunda).
        $nombresEnArchivo = [];

        foreach ($rows as $index => $rawRow) {
            $row = collect($rawRow)->mapWithKeys(fn ($value, $key) => [strtolower(trim((string) $key)) => $value]);
            $numeroFila = $index + 2; // +1 por el encabezado, +1 porque el indice empieza en 0

            // Fila sin tocar: se decide SOLO por las columnas que uno
            // realmente escribe (Nombre/Precio Costo), no por todas. Precio
            // de Venta trae formula en las 500 filas de la plantilla (ver
            // ProductBulkTemplatePlantillaSheet), asi que esa celda nunca
            // esta "vacia" de verdad -- si se revisaran todas las columnas,
            // una fila vacia de verdad salia marcada como "con datos" por
            // error (mismo bug que ya se corrigio en CompraBulkImport).
            $nombreRaw = trim((string) ($row['nombre_del_producto'] ?? ''));
            $costoRaw = trim((string) ($row['precio_costo'] ?? ''));

            if ($nombreRaw === '' && $costoRaw === '') {
                continue; // fila completamente vacia, se ignora sin avisar
            }

            $nombre = $this->textoValido($nombreRaw);

            if (str_starts_with(mb_strtolower($nombre), 'ejemplo')) {
                continue; // fila de ejemplo de la plantilla, se ignora sin avisar
            }

            // El codigo se reserva aqui, apenas se sabe que la fila es un
            // producto real (no vacia, no la fila de ejemplo) -- ANTES de
            // cualquier validacion que pueda hacer continue. Asi el codigo
            // de cada fila coincide siempre con su posicion entre los
            // productos reales del excel, aunque esa fila en particular
            // termine fallando por otro motivo (nombre repetido, falta
            // precio, etc). Como ademas no se crea nada si el archivo tiene
            // algun error, esto solo importa para que el mensaje de error le
            // diga al usuario que codigo le hubiera tocado a esa fila.
            $codigoFila = $siguienteCodigo++;

            $precioCosto = $this->numero($row['precio_costo'] ?? null);
            $precioVentaExcel = $this->numero($row['precio_de_venta'] ?? null);
            $utilidadExcel = $this->numero($row['utilidad'] ?? null);

            if ($nombre === '') {
                $errores[] = "Fila {$numeroFila}: falta el nombre del producto. (código {$codigoFila} quedó sin usar)";
                continue;
            }

            if ($precioCosto === null) {
                $errores[] = "Fila {$numeroFila} ({$nombre}): falta el Precio Costo. (código {$codigoFila} quedó sin usar)";
                continue;
            }

            if ($precioVentaExcel === null && $utilidadExcel === null) {
                $errores[] = "Fila {$numeroFila} ({$nombre}): falta la Utilidad (o el Precio de Venta). (código {$codigoFila} quedó sin usar)";
                continue;
            }

            $clave = mb_strtolower($nombre);

            if (isset($nombresEnArchivo[$clave])) {
                $errores[] = "Fila {$numeroFila}: el nombre \"{$nombre}\" esta repetido con la fila {$nombresEnArchivo[$clave]} de este mismo archivo. (código {$codigoFila} quedó sin usar)";
                continue;
            }

            if (Product::where('empresa_id', $this->empresaId)->where('descripcion_larga', $nombre)->exists()) {
                $errores[] = "Fila {$numeroFila}: ya existe un producto llamado \"{$nombre}\" en tu empresa. (código {$codigoFila} quedó sin usar)";
                continue;
            }

            $nombresEnArchivo[$clave] = $numeroFila;

            $descuento = $this->numero($row['descuento_comercial'] ?? null) ?? 0;
            $ivaCompra = $this->numero($row['iva_compra'] ?? null) ?? 19;
            $ivaVenta = $this->numero($row['iva_venta'] ?? null) ?? 19;

            // Misma formula que ProductResource::calcularValores(), para que
            // el costo con IVA salga igual a como quedaria si el producto se
            // hubiera creado a mano en el formulario. El precio de venta se
            // calcula a partir de la Utilidad (igual que en la plantilla de
            // Compras); si en vez de Utilidad viene el Precio de Venta ya
            // escrito, se usa ese y se calcula la utilidad de respaldo.
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

            $filas[] = [
                'codigo' => $codigoFila,
                'nombre' => $nombre,
                'departamento' => trim((string) ($row['departamento'] ?? '')),
                'subfamilia' => trim((string) ($row['subfamilia'] ?? '')),
                'proveedor' => trim((string) ($row['proveedor'] ?? '')),
                'unidad_de_medida' => trim((string) ($row['unidad_de_medida'] ?? '')),
                'precio_costo' => $precioCosto,
                'descuento' => $descuento,
                'iva_compra' => $ivaCompra,
                'iva_venta' => $ivaVenta,
                'costo_iva' => $costoIva,
                'costo_con_descuento' => $costoConDescuento,
                'utilidad1' => $utilidad1,
                'precio_venta' => $precioVenta,
            ];
        }

        return [$filas, $errores];
    }

    /**
     * Pase 2: solo se llega aqui si TODAS las filas del pase 1 fueron
     * validas. Aqui si se crean los productos, dentro de una transaccion.
     */
    private function crear(array $filas): void
    {
        DB::transaction(function () use ($filas) {
            foreach ($filas as $fila) {
                $idFamilia1 = $this->resolveFamilia($fila['departamento']);
                $idFamilia2 = $this->resolveSubfamilia($fila['subfamilia'], $idFamilia1);
                $idProveedor = $this->resolveProveedor($fila['proveedor']);
                $idUnidad = $this->resolveUnidad($fila['unidad_de_medida']);

                $producto = Product::create([
                    'empresa_id' => $this->empresaId,
                    'id_producto' => $fila['codigo'],
                    'descripcion_larga' => $fila['nombre'],
                    'id_proveedor' => $idProveedor,
                    'id_familia1' => $idFamilia1,
                    'id_familia2' => $idFamilia2,
                    'id_unidad_de_medida' => $idUnidad,
                    'precio_costo' => $fila['precio_costo'],
                    'descuento_comercial' => $fila['descuento'],
                    'precio_con_descuento' => $fila['costo_con_descuento'],
                    'iva_compra' => $fila['iva_compra'],
                    'iva_venta' => $fila['iva_venta'],
                    'costo_iva' => $fila['costo_iva'],
                    'utilidad1' => $fila['utilidad1'],
                    'precio_venta1' => $fila['precio_venta'],
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

                $this->creados++;
            }
        });
    }

    public function resumen(): array
    {
        return [
            'creados' => $this->creados,
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

    // Si el nombre viene con pinta de formula (empieza con =, +, - o @),
    // se trata como si viniera vacio en vez de crear un proveedor/
    // departamento/subfamilia con ese texto (por si una fila de excel
    // trae una celda con formula sin calcular en vez de su resultado).
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
            return (int) $this->defaultFamilia()->id;
        }

        $familia = Familia::query()
            ->where('empresa_id', $this->empresaId)
            ->whereRaw('LOWER(nombre) = ?', [mb_strtolower($nombre)])
            ->first();

        if ($familia) {
            return (int) $familia->id;
        }

        return (int) Familia::create([
            'empresa_id' => $this->empresaId,
            'nombre' => $nombre,
        ])->id;
    }

    private function resolveSubfamilia(string $nombre, int $familiaId): int
    {
        $nombre = $this->textoValido($nombre);

        if ($nombre === '') {
            return (int) $this->defaultSubfamilia($familiaId)->id_familia2;
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

    private function defaultFamilia(): Familia
    {
        return Familia::query()->firstOrCreate(
            ['empresa_id' => $this->empresaId, 'nombre' => 'FAMILIA DE PRUEBA'],
        );
    }

    private function defaultSubfamilia(int $familiaId): Subfamilia
    {
        return Subfamilia::query()->firstOrCreate(
            [
                'empresa_id' => $this->empresaId,
                'id_familia1' => $familiaId,
                'nombre' => 'SUBFAMILIA DE PRUEBA',
            ],
        );
    }
}
