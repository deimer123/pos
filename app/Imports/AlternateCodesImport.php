<?php

namespace App\Imports;

use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class AlternateCodesImport implements ToCollection, WithHeadingRow, WithChunkReading
{
    protected $empresaId;

    public function __construct($empresaId)
    {
        $this->empresaId = $empresaId;
    }

    public function collection(Collection $rows)
    {
        $rows = $rows
            ->map(fn($row) => collect($row)->mapWithKeys(fn($value, $key) => [strtolower(trim($key)) => $value]))
            ->filter(fn($row) => !empty($row['idproducto']) && !empty($row['codigoalterno']))
            ->values();

        if ($rows->isEmpty()) {
            return;
        }

        $products = Product::query()
            ->where('empresa_id', $this->empresaId)
            ->whereIn('id_producto', $rows->pluck('idproducto')->map(fn($id) => (int) $id)->unique())
            ->pluck('id', 'id_producto');

        $now = now();
        $codes = [];

        foreach ($rows as $row) {
            $productId = $products->get((int) $row['idproducto']);

            if (!$productId) {
                continue;
            }

            $codes[] = [
                'product_id' => $productId,
                'code' => trim((string) $row['codigoalterno']),
                'empresa_id' => $this->empresaId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (empty($codes)) {
            return;
        }

        DB::table('alternate_codes')->upsert(
            $codes,
            ['product_id', 'code', 'empresa_id'],
            ['updated_at']
        );
    }

    public function chunkSize(): int
    {
        return 1000;
    }
}
