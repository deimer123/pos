<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class FamiliaImport implements ToCollection, WithHeadingRow, WithChunkReading
{
    protected $empresaId;

    public function __construct($empresaId)
    {
        $this->empresaId = $empresaId;
    }

    public function collection(Collection $rows)
    {
        $now = now();
        $familias = [];

        foreach ($rows as $row) {
            if (empty($row['idfamilia1']) || empty($row['nfamilia1'])) {
                continue;
            }

            $familias[] = [
                'id' => $row['idfamilia1'],
                'empresa_id' => $this->empresaId,
                'nombre' => $row['nfamilia1'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (empty($familias)) {
            return;
        }

        DB::table('familias')->upsert(
            $familias,
            ['id'],
            ['empresa_id', 'nombre', 'updated_at']
        );
    }

    public function chunkSize(): int
    {
        return 1000;
    }
}
