<?php

namespace App\Imports;

use App\Models\Product;
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

            $products[] = [
                'empresa_id' => $this->empresaId,
                'id_producto' => $idProducto,
                'id_familia1' => $row['idfamilia1'] ?? 1,
                'id_familia2' => $row['idfamilia2'] ?? 1,
                'descripcion_larga' => $row['descripcionlarga'],
                'id_proveedor' => $row['idproveedor'] ?? null,
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
