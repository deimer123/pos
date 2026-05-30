<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ActorImport implements ToCollection, WithHeadingRow, WithChunkReading
{
    protected $empresaId;

    public function __construct($empresaId)
    {
        $this->empresaId = $empresaId;
    }

    public function collection(Collection $rows)
    {
        $now = now();
        $actors = [];

        foreach ($rows as $row) {
            $ciudadId = intval($row['idciudad']);
            $departamentoId = intval($row['iddepartamento']);

            if ($ciudadId === 0 || $departamentoId === 0 || empty($row['idclipro'])) {
                continue;
            }

            $clasificacion = match ((int) $row['idtipo']) {
                1, 2 => 'cliente',
                3 => 'proveedor',
                default => null,
            };

            $tipoPersona = match ((int) $row['idregimen']) {
                1 => 'juridica',
                2 => 'natural',
                default => 'natural',
            };

            $regimen = match ((int) $row['idregimen']) {
                1 => 'comun',
                2 => 'simplificado',
                default => 'otro',
            };

            $actors[] = [
                'id_clip_pro' => $row['idclipro'],
                'empresa_id' => $this->empresaId,
                'tipo' => $row['idtipo'],
                'identificacion' => $row['noidentificacion'],
                'nombre' => $row['nombrecompleto'],
                'razon_social' => $row['razonsocial'],
                'direccion' => $row['direccionclipro'],
                'telefono' => $row['telefono1clipro'],
                'email' => $row['emailclipro1'],
                'clasificacion' => $clasificacion,
                'tipo_persona' => $tipoPersona,
                'tipo_documento_id' => (int) $row['idtiponit'],
                'regimen_tributario' => $regimen,
                'responsable_iva' => (int) $row['ivaventa'] > 0,
                'departamento_id' => $departamentoId,
                'ciudad_id' => $ciudadId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (empty($actors)) {
            return;
        }

        DB::table('actors')->upsert(
            $actors,
            ['empresa_id', 'id_clip_pro'],
            [
                'tipo',
                'identificacion',
                'nombre',
                'razon_social',
                'direccion',
                'telefono',
                'email',
                'clasificacion',
                'tipo_persona',
                'tipo_documento_id',
                'regimen_tributario',
                'responsable_iva',
                'departamento_id',
                'ciudad_id',
                'updated_at',
            ]
        );
    }

    public function chunkSize(): int
    {
        return 1000;
    }
}
