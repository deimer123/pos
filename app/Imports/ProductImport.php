<?php

namespace App\Imports;

use App\Models\Actor;
use App\Models\Familia;
use App\Models\Product;
use App\Models\Subfamilia;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ProductImport implements ToCollection, WithHeadingRow, WithChunkReading
{
    protected $empresaId;

    public function __construct($empresaId)
    {
        $this->empresaId = $empresaId;
    }

    public function collection(Collection $rows)
    {
        $normalizedRows = $rows
            ->map(fn($row) => collect($row)->mapWithKeys(fn($value, $key) => [strtolower(trim($key)) => $value]))
            ->filter(fn($row) => !empty($row['idproducto']) && !empty($row['descripcionlarga']))
            ->values();

        if ($normalizedRows->isEmpty()) {
            return;
        }

        $productIds = $normalizedRows
            ->pluck('idproducto')
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values();

        $existingProducts = Product::query()
            ->where('empresa_id', $this->empresaId)
            ->whereIn('id_producto', $productIds)
            ->get(['id_producto', 'precio_costo', 'precio_venta1', 'precio_costo_anterior', 'precio_venta_anterior'])
            ->keyBy('id_producto');

        $now = now();
        $products = [];

        foreach ($normalizedRows as $row) {
            $idProducto = (int) $row['idproducto'];
            $existingProduct = $existingProducts->get($idProducto);

            $precioCosto = $this->decimalValue($row['preciocosto'] ?? 0);
            $precioVenta1 = $this->decimalValue($row['precioventa1'] ?? 0);
            $utilidad1 = $this->decimalValue($row['utilidad1'] ?? 0);

            $idFamilia1 = $this->resolveFamilia($row['idfamilia1'] ?? null);
            $idFamilia2 = $this->resolveSubfamilia($row['idfamilia2'] ?? null, $idFamilia1);
            $idProveedor = $this->resolveProveedor($row['idproveedor'] ?? null);

            $products[] = [
                'empresa_id' => $this->empresaId,
                'id_producto' => $idProducto,
                'id_familia1' => $idFamilia1,
                'id_familia2' => $idFamilia2,
                'descripcion_larga' => $row['descripcionlarga'],
                'id_proveedor' => $idProveedor,
                'iva_compra' => $row['ivacompra'] ?? 0,
                'iva_venta' => $row['ivaventa'] ?? 0,
                'existencias' => $row['existencias'] ?? 0,
                'precio_costo' => $precioCosto,
                'precio_venta1' => $precioVenta1,
                'utilidad1' => $utilidad1,
                'id_unidad_de_medida' => $row['id_unidaddemedida'] ?? 1,
                'foto' => $row['foto'] ?? null,
                'precio_costo_anterior' => $this->previousValue($existingProduct, 'precio_costo', 'precio_costo_anterior', $precioCosto),
                'precio_venta_anterior' => $this->previousValue($existingProduct, 'precio_venta1', 'precio_venta_anterior', $precioVenta1),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('products')->upsert(
            $products,
            ['id_producto', 'empresa_id'],
            [
                'id_familia1',
                'id_familia2',
                'descripcion_larga',
                'id_proveedor',
                'iva_compra',
                'iva_venta',
                'existencias',
                'precio_costo',
                'precio_venta1',
                'utilidad1',
                'id_unidad_de_medida',
                'foto',
                'precio_costo_anterior',
                'precio_venta_anterior',
                'updated_at',
            ]
        );
    }

    public function chunkSize(): int
    {
        return 500;
    }

    private function resolveProveedor($value): int
    {
        $value = trim((string) ($value ?? ''));

        $query = Actor::query()
            ->where('empresa_id', $this->empresaId)
            ->whereIn('clasificacion', ['proveedor', 'cliente_proveedor']);

        if ($value !== '') {
            $proveedor = (clone $query)
                ->where(function ($q) use ($value) {
                    if (is_numeric($value)) {
                        $q->where('id_clip_pro', (int) $value)
                            ->orWhere('id', (int) $value);
                    }

                    $q->orWhere('nombre', $value)
                        ->orWhere('razon_social', $value);
                })
                ->first();

            if ($proveedor) {
                return (int) $proveedor->id_clip_pro;
            }
        }

        return (int) $this->defaultProveedor()->id_clip_pro;
    }

    private function resolveFamilia($value): int
    {
        $value = trim((string) ($value ?? ''));

        if ($value !== '') {
            $familia = Familia::query()
                ->where('empresa_id', $this->empresaId)
                ->where(function ($q) use ($value) {
                    if (is_numeric($value)) {
                        $q->where('id', (int) $value);
                    }

                    $q->orWhere('nombre', $value);
                })
                ->first();

            if ($familia) {
                return (int) $familia->id;
            }
        }

        return (int) $this->defaultFamilia()->id;
    }

    private function resolveSubfamilia($value, int $familiaId): int
    {
        $value = trim((string) ($value ?? ''));

        if ($value !== '') {
            $subfamilia = Subfamilia::query()
                ->where('empresa_id', $this->empresaId)
                ->where(function ($q) use ($value) {
                    if (is_numeric($value)) {
                        $q->where('id_familia2', (int) $value);
                    }

                    $q->orWhere('nombre', $value);
                })
                ->first();

            if ($subfamilia) {
                return (int) $subfamilia->id_familia2;
            }
        }

        return (int) $this->defaultSubfamilia($familiaId)->id_familia2;
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

        $ultimoId = (int) (Actor::max('id_clip_pro') ?? 0);

        return Actor::create([
            'id_clip_pro' => $ultimoId + 1,
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
            ['empresa_id' => $this->empresaId, 'nombre' => 'FAMILIA DE PRUEBA']
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
            [
                'empresa_id' => $this->empresaId,
                'id_familia1' => $familiaId,
                'nombre' => 'SUBFAMILIA DE PRUEBA',
            ]
        );
    }

    private function decimalValue($value): string
    {
        return str_replace(',', '.', $value ?? 0);
    }

    private function previousValue(?Product $existingProduct, string $currentColumn, string $previousColumn, $newValue)
    {
        if (!$existingProduct) {
            return null;
        }

        if ((string) $existingProduct->{$currentColumn} !== (string) $newValue) {
            return $existingProduct->{$currentColumn};
        }

        return $existingProduct->{$previousColumn};
    }
}