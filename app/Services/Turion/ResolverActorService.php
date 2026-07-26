<?php

namespace App\Services\Turion;

use App\Models\Actor;

/**
 * Encuentra (o crea) en el droplet el Actor correspondiente a un cliente
 * que llega desde Turion -- se usa tanto al subir una prefactura
 * (PosSyncController::prefacturaGuardar()) como al subir un cliente creado
 * directo (PosSyncController::actorCrear()).
 *
 * Orden de resolucion: por id (valido si ya existia al ultimo emparejar,
 * ya que el catalogo preserva los ids reales), si no por identificacion,
 * si no por nombre; si nada matchea y hay datos de cliente, se crea uno
 * nuevo con esos datos. Sin datos de cliente, null.
 */
class ResolverActorService
{
    public static function resolverOCrear(int $empresaId, array $datos, ?int $clienteIdSugerido = null): ?int
    {
        if (! empty($clienteIdSugerido)) {
            $existente = Actor::where('empresa_id', $empresaId)->where('id', $clienteIdSugerido)->exists();
            if ($existente) {
                return (int) $clienteIdSugerido;
            }
        }

        $identificacion = trim((string) ($datos['identificacion'] ?? ''));
        $nombre = trim((string) ($datos['nombre'] ?? ''));

        if ($identificacion !== '') {
            $id = Actor::where('empresa_id', $empresaId)->where('identificacion', $identificacion)->value('id');
            if ($id) {
                return (int) $id;
            }
        }

        if ($nombre !== '') {
            $id = Actor::where('empresa_id', $empresaId)
                ->whereRaw('LOWER(TRIM(nombre)) = ?', [mb_strtolower($nombre)])
                ->value('id');
            if ($id) {
                return (int) $id;
            }
        }

        if ($nombre === '') {
            return null;
        }

        $nuevo = Actor::create([
            'id_clip_pro' => (int) Actor::where('empresa_id', $empresaId)->max('id_clip_pro') + 1,
            'empresa_id' => $empresaId,
            'tipo' => 1,
            'clasificacion' => 'cliente',
            'tipo_documento_id' => $datos['tipo_documento_id'] ?? 3,
            'identificacion' => $identificacion !== '' ? $identificacion : null,
            'nombre' => $nombre,
            'razon_social' => $datos['razon_social'] ?? null,
            'telefono' => $datos['telefono'] ?? null,
            'email' => $datos['email'] ?? null,
            'direccion' => $datos['direccion'] ?? null,
            'departamento_id' => $datos['departamento_id'] ?? null,
            'ciudad_id' => $datos['ciudad_id'] ?? null,
            'tipo_persona' => $datos['tipo_persona'] ?? null,
            'regimen_tributario' => $datos['regimen_tributario'] ?? null,
            'responsable_iva' => $datos['responsable_iva'] ?? false,
        ]);

        return $nuevo->id;
    }
}
