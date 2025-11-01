<?php

namespace App\Imports;

use App\Models\Actor;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ActorImport implements ToCollection, WithHeadingRow
{
    protected $empresaId;

    public function __construct($empresaId)
    {
        $this->empresaId = $empresaId;
    }

    public function collection(Collection $rows)
    {
      

        foreach ($rows as $row) {
            $ciudadId = intval($row['idciudad']);
            $departamentoId = intval($row['iddepartamento']);

            // Saltar si no hay ciudad o departamento válido
            if ($ciudadId === 0 || $departamentoId === 0) {
                continue;
            }

            // Clasificación
            $clasificacion = match((int) $row['idtipo']) {
                1, 2 => 'cliente',
                3 => 'proveedor',
                default => null
            };

            // Tipo de persona
            $tipo_persona = match((int) $row['idregimen']) {
                1 => 'juridica',
                2 => 'natural',
                default => 'natural',
            };

            // Tipo de documento
            $tipo_documento_id = (int) $row['idtiponit'];

            // Régimen tributario
            $regimen = match((int) $row['idregimen']) {
                1 => 'comun',
                2 => 'simplificado',
                default => 'otro'
            };

           

            $actor = Actor::firstOrNew([
                'id_clip_pro' => $row['idclipro'],
                'empresa_id'  => $this->empresaId,
            ]);

            // 👉 Evitar asignación automática de empresa_id desde el modelo
            $actor->omitEmpresaAutoAssign = true;

            // Asignar datos
            $actor->fill([
                'empresa_id'         => $this->empresaId,
                'tipo'               => $row['idtipo'],
                'identificacion'     => $row['noidentificacion'],
                'nombre'             => $row['nombrecompleto'],
                'razon_social'       => $row['razonsocial'],
                'direccion'          => $row['direccionclipro'],
                'telefono'           => $row['telefono1clipro'],
                'email'              => $row['emailclipro1'],
                'clasificacion'      => $clasificacion,
                'tipo_persona'       => $tipo_persona,
                'tipo_documento_id'  => $tipo_documento_id,
                'regimen_tributario' => $regimen,
                'responsable_iva'    => (int) $row['ivaventa'] > 0,
                'departamento_id'    => $departamentoId,
                'ciudad_id'          => $ciudadId,
            ]);

            $actor->save();

           
        }
    }
}
