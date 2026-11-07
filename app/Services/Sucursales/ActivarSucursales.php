<?php

namespace App\Services\Sucursales;

use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// Activa el modulo de sucursales para UNA empresa sin perder ni mover datos
// de otras empresas:
//   1. crea la sucursal "Principal",
//   2. le asigna todo lo que la empresa ya tenia (sucursal_id NULL -> Principal),
//   3. asigna a la Principal a los empleados existentes,
//   4. enciende users.usa_sucursales.
// Todo corre en UNA transaccion y al final verifica los conteos; si algo no
// cuadra lanza una excepcion y la base queda exactamente como estaba.
// Es idempotente: volver a correrlo no duplica la Principal y solo asigna
// lo que haya quedado en NULL.
class ActivarSucursales
{
    // Modulos que mueven su propio stock y todavia no son compatibles con
    // sucursales en esta primera version: recetas, variantes, lotes, taller y
    // servicio tecnico descuentan existencias por su cuenta; hotel falta por
    // revisar.
    //
    // Mesas y cocina NO estan aqui a proposito: no tocan el stock (venden por
    // el flujo normal). OJO para el paso de crear sucursales: sus tablas
    // (mesas, ordenes_mesas, orden_mesa_items) todavia no tienen sucursal_id,
    // asi que antes de permitir una SEGUNDA sucursal en una empresa con mesas
    // hay que agregarselo, o las mesas de una sucursal se verian en las demas.
    public const MODULOS_INCOMPATIBLES = [
        'usa_recetas' => 'recetas',
        'usa_variantes' => 'variantes',
        'usa_lotes' => 'lotes',
        'usa_taller' => 'taller',
        'usa_hotel' => 'hotel',
        'usa_servicio_tecnico' => 'servicio técnico',
    ];

    // Tablas operativas que reciben sucursal_id (ver migracion
    // 2026_11_06_000004). Las hijas (detalles, pagos) heredan del padre.
    public const TABLAS = [
        'facturas',
        'nota_creditos',
        'prefacturas',
        'devoluciones',
        'compras',
        'devolucion_compras',
        'cajas',
        'gastos',
        'ajustes_inventario',
        'stock_movimientos',
        'kardex',
    ];

    // null = se puede activar. Si no, el motivo en texto para mostrarselo al
    // super_admin. $tipoEdicion permite validar la edicion que se esta
    // guardando en el mismo formulario, antes de que quede persistida.
    public function motivoNoPuedeActivar(User $empresa, ?string $tipoEdicion = null): ?string
    {
        if (! $empresa->esEmpresa()) {
            return 'Solo las empresas pueden usar sucursales.';
        }

        if (($tipoEdicion ?? $empresa->tipo_edicion) !== 'online') {
            return 'Las sucursales solo están disponibles en la edición Online.';
        }

        $config = $empresa->configuracion;
        $activos = [];

        foreach (self::MODULOS_INCOMPATIBLES as $campo => $nombre) {
            if ($config && $config->{$campo}) {
                $activos[] = $nombre;
            }
        }

        if ($activos !== []) {
            return 'Todavía no está disponible para empresas que usan: ' . implode(', ', $activos) . '.';
        }

        return null;
    }

    // Que se haria, sin cambiar nada (lo usa --dry-run).
    public function resumen(User $empresa): array
    {
        return [
            'motivo' => $this->motivoNoPuedeActivar($empresa),
            'ya_activa' => (bool) $empresa->usa_sucursales,
            'tiene_principal' => Sucursal::where('empresa_id', $empresa->id)->where('es_principal', true)->exists(),
            'empleados' => $this->idsEmpleados($empresa)->count(),
            'tablas' => $this->conteos($empresa->id),
        ];
    }

    public function activar(User $empresa): array
    {
        if ($motivo = $this->motivoNoPuedeActivar($empresa)) {
            throw new RuntimeException($motivo);
        }

        return DB::transaction(function () use ($empresa) {
            // Bloquea la fila de la empresa para que dos activaciones
            // simultaneas no se pisen.
            User::whereKey($empresa->id)->lockForUpdate()->first();

            $principal = Sucursal::firstOrCreate(
                ['empresa_id' => $empresa->id, 'es_principal' => true],
                [
                    'nombre' => 'Principal',
                    'direccion' => $empresa->direccion,
                    'telefono' => $empresa->telefono,
                    'activo' => true,
                ],
            );

            $antes = $this->conteos($empresa->id);
            $asignadas = [];

            foreach (self::TABLAS as $tabla) {
                // update() de query builder no toca updated_at, asi que los
                // registros conservan su fecha original.
                $asignadas[$tabla] = $this->deLaEmpresa($tabla, $empresa->id)
                    ->whereNull('sucursal_id')
                    ->update(['sucursal_id' => $principal->id]);
            }

            $despues = $this->conteos($empresa->id);

            foreach (self::TABLAS as $tabla) {
                if ($despues[$tabla]['total'] !== $antes[$tabla]['total']) {
                    throw new RuntimeException("Verificación fallida en {$tabla}: cambió la cantidad de registros ({$antes[$tabla]['total']} -> {$despues[$tabla]['total']}).");
                }

                if ($despues[$tabla]['pendientes'] !== 0) {
                    throw new RuntimeException("Verificación fallida en {$tabla}: quedaron {$despues[$tabla]['pendientes']} registros sin sucursal.");
                }

                if ($asignadas[$tabla] !== $antes[$tabla]['pendientes']) {
                    throw new RuntimeException("Verificación fallida en {$tabla}: se esperaba asignar {$antes[$tabla]['pendientes']} y se asignaron {$asignadas[$tabla]}.");
                }
            }

            $empleados = $this->idsEmpleados($empresa);
            $principal->usuarios()->syncWithoutDetaching($empleados->all());

            $empresa->usa_sucursales = true;
            $empresa->saveQuietly();

            return [
                'sucursal_id' => $principal->id,
                'asignadas' => $asignadas,
                'empleados' => $empleados->count(),
            ];
        });
    }

    // [tabla => ['total' => registros de la empresa, 'pendientes' => los que
    // aun no tienen sucursal]].
    public function conteos(int $empresaId): array
    {
        $conteos = [];

        foreach (self::TABLAS as $tabla) {
            $conteos[$tabla] = [
                'total' => $this->deLaEmpresa($tabla, $empresaId)->count(),
                'pendientes' => $this->deLaEmpresa($tabla, $empresaId)->whereNull('sucursal_id')->count(),
            ];
        }

        return $conteos;
    }

    // Registros de UNA empresa. La mayoria de tablas tiene empresa_id; en las
    // que puede ser NULL (cajas, nota_creditos, devolucion_compras) se deduce
    // por el usuario o por la compra, y solo cuando empresa_id es NULL para
    // no tocar jamas lo que ya esta marcado como de otra empresa.
    private function deLaEmpresa(string $tabla, int $empresaId): Builder
    {
        $query = DB::table($tabla);

        return match ($tabla) {
            'cajas' => $query->where(fn ($w) => $w
                ->where('empresa_id', $empresaId)
                ->orWhere(fn ($n) => $n->whereNull('empresa_id')->whereIn(
                    'user_id',
                    DB::table('users')
                        ->where(fn ($u) => $u->where('id', $empresaId)->orWhere('empresa_id', $empresaId))
                        ->select('id')
                ))),
            'nota_creditos', 'devolucion_compras' => $query->where(fn ($w) => $w
                ->where('empresa_id', $empresaId)
                ->orWhere(fn ($n) => $n->whereNull('empresa_id')->whereIn(
                    'compra_id',
                    DB::table('compras')->where('empresa_id', $empresaId)->select('id')
                ))),
            default => $query->where('empresa_id', $empresaId),
        };
    }

    private function idsEmpleados(User $empresa)
    {
        return User::where('empresa_id', $empresa->id)
            ->where('tipo_usuario', 'empleado')
            ->pluck('id');
    }
}
