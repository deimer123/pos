<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class SubfamiliaImport implements ToCollection, WithHeadingRow, WithChunkReading
{
    protected $empresaId;

    public function __construct($empresaId)
    {
        $this->empresaId = $empresaId;
    }

    public function collection(Collection $rows)
    {
        $now = now();
        $subfamilias = [];

        foreach ($rows as $row) {
            if (empty($row['idfamilia2']) || empty($row['nfamilia2'])) {
                continue;
            }

            $subfamilias[] = [
                'id_familia2' => $row['idfamilia2'],
                'id_familia1' => $row['idfamilia1'] ?? 0,
                'empresa_id' => $this->empresaId,
                'nombre' => $row['nfamilia2'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (empty($subfamilias)) {
            return;
        }

        DB::table('subfamilias')->upsert(
            $subfamilias,
            ['id_familia2'],
            ['id_familia1', 'empresa_id', 'nombre', 'updated_at']
        );
    }

    public function chunkSize(): int
    {
        return 1000;
    }
}
