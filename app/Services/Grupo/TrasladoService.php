<?php

namespace App\Services\Grupo;

use App\Models\ConfiguracionEmpresa;
use App\Models\Familia;
use App\Models\Product;
use App\Models\Subfamilia;
use App\Models\Traslado;
use App\Models\TrasladoItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// Traslados de mercancia entre empresas del mismo grupo (sucursales).
//
//  enviar    -> el stock SALE de la empresa de origen (kardex traslado_salida).
//  recibir   -> el stock ENTRA a la de destino (kardex traslado_entrada); el
//               producto se empareja por nombre exacto y, si no existe, se crea.
//  rechazar  -> (destino) el stock vuelve al origen.
//  cancelar  -> (origen) el stock vuelve al origen.
//
// Mientras el traslado esta "enviado" la mercancia no cuenta en ninguna de las
// dos empresas. Todo corre en una transaccion con las filas bloqueadas, y el
// estado se revisa DENTRO de la transaccion, asi que pulsar dos veces
// "Recibir" no suma el stock dos veces.
class TrasladoService
{
    public function __construct(private GrupoEmpresas $grupo)
    {
    }

    // null = se puede trasladar de $origen a $destino; si no, el motivo.
    public function motivoNoPuede(User $origen, User $destino): ?string
    {
        if (! $origen->esEmpresa() || ! $destino->esEmpresa()) {
            return 'Solo las empresas pueden hacer traslados.';
        }

        if ($origen->id === $destino->id) {
            return 'El destino debe ser otra sucursal.';
        }

        if (! in_array($destino->id, $this->grupo->idsDelGrupo($origen), true)) {
            return 'Solo se puede trasladar entre empresas del mismo grupo.';
        }

        if (! $origen->activo || ! $destino->activo) {
            return 'La empresa de origen y la de destino deben estar activas.';
        }

        foreach ([$origen, $destino] as $empresa) {
            $config = ConfiguracionEmpresa::where('empresa_id', $empresa->id)->first();

            if ($config && ($config->usa_lotes || $config->usa_variantes)) {
                return "«{$empresa->name}» usa lotes o variantes: todavía no se pueden trasladar productos con ellos.";
            }
        }

        return null;
    }

    // $lineas: [['id_producto' => int, 'cantidad' => float], ...]
    public function enviar(User $actor, User $destino, array $lineas, ?string $observacion = null): Traslado
    {
        $origen = $this->empresaDe($actor);

        if ($motivo = $this->motivoNoPuede($origen, $destino)) {
            throw new RuntimeException($motivo);
        }

        $lineas = $this->unirLineas($lineas);

        return DB::transaction(function () use ($actor, $origen, $destino, $lineas, $observacion) {
            $productos = Product::where('empresa_id', $origen->id)
                ->whereIn('id_producto', array_keys($lineas))
                ->lockForUpdate()
                ->get()
                ->keyBy('id_producto');

            foreach ($lineas as $idProducto => $cantidad) {
                $producto = $productos->get($idProducto);

                if (! $producto) {
                    throw new RuntimeException("El producto {$idProducto} no existe en tu empresa.");
                }

                if (! $producto->maneja_inventario || $producto->tipo_producto !== 'producto') {
                    throw new RuntimeException("«{$producto->descripcion_larga}» no maneja inventario y no se puede trasladar.");
                }

                if (round((float) $producto->existencias, 2) < $cantidad) {
                    throw new RuntimeException("Stock insuficiente de «{$producto->descripcion_larga}»: hay {$producto->existencias} y quieres enviar {$cantidad}.");
                }
            }

            $traslado = Traslado::create([
                'origen_id' => $origen->id,
                'destino_id' => $destino->id,
                'estado' => Traslado::ENVIADO,
                'observacion' => $observacion,
                'enviado_por' => $actor->id,
                'enviado_at' => now(),
            ]);

            foreach ($lineas as $idProducto => $cantidad) {
                $producto = $productos->get($idProducto);
                $anterior = (float) $producto->existencias;

                TrasladoItem::create([
                    'traslado_id' => $traslado->id,
                    'producto_origen_id' => $idProducto,
                    'nombre' => $producto->descripcion_larga,
                    'cantidad' => $cantidad,
                    'costo_unitario' => $producto->precio_costo ?? 0,
                    'precio_venta' => $producto->precio_venta1 ?? 0,
                ]);

                guardarKardex($idProducto, 'traslado_salida', $cantidad, $origen->id, "Traslado #{$traslado->id}", $anterior);
                $producto->update(['existencias' => round($anterior - $cantidad, 2)]);
            }

            return $traslado;
        });
    }

    public function recibir(User $actor, Traslado $traslado): Traslado
    {
        return DB::transaction(function () use ($actor, $traslado) {
            $traslado = $this->bloquear($traslado);
            $destino = $this->empresaDe($actor);

            if ((int) $traslado->destino_id !== (int) $destino->id) {
                throw new RuntimeException('Solo la empresa de destino puede recibir este traslado.');
            }

            foreach ($traslado->items as $item) {
                $producto = Product::where('empresa_id', $destino->id)
                    ->where('descripcion_larga', $item->nombre)
                    ->lockForUpdate()
                    ->first() ?? $this->crearProductoEnDestino($traslado, $item);

                if (! $producto->maneja_inventario) {
                    throw new RuntimeException("«{$producto->descripcion_larga}» no maneja inventario en tu empresa.");
                }

                $anterior = (float) $producto->existencias;

                guardarKardex($producto->id_producto, 'traslado_entrada', (float) $item->cantidad, $destino->id, "Traslado #{$traslado->id}", $anterior);
                $producto->update(['existencias' => round($anterior + (float) $item->cantidad, 2)]);

                $item->update(['producto_destino_id' => $producto->id_producto]);
            }

            return $this->cerrar($traslado, $actor, Traslado::RECIBIDO);
        });
    }

    // El destino rechaza: la mercancia vuelve al origen.
    public function rechazar(User $actor, Traslado $traslado): Traslado
    {
        return $this->devolver($actor, $traslado, Traslado::RECHAZADO, 'destino_id');
    }

    // El origen cancela antes de que lo reciban: la mercancia vuelve al origen.
    public function cancelar(User $actor, Traslado $traslado): Traslado
    {
        return $this->devolver($actor, $traslado, Traslado::CANCELADO, 'origen_id');
    }

    private function devolver(User $actor, Traslado $traslado, string $estadoFinal, string $columnaActor): Traslado
    {
        return DB::transaction(function () use ($actor, $traslado, $estadoFinal, $columnaActor) {
            $traslado = $this->bloquear($traslado);

            if ((int) $traslado->{$columnaActor} !== (int) $this->empresaDe($actor)->id) {
                throw new RuntimeException('No tienes permiso para esta acción sobre el traslado.');
            }

            foreach ($traslado->items as $item) {
                $producto = Product::where('empresa_id', $traslado->origen_id)
                    ->where('id_producto', $item->producto_origen_id)
                    ->lockForUpdate()
                    ->first();

                // Si el producto ya no existe en el origen no se pierde el stock
                // en silencio: se bloquea y se avisa.
                if (! $producto) {
                    throw new RuntimeException("«{$item->nombre}» ya no existe en la empresa de origen; no se puede devolver el stock automáticamente.");
                }

                $anterior = (float) $producto->existencias;

                guardarKardex($producto->id_producto, 'traslado_devolucion', (float) $item->cantidad, $traslado->origen_id, "Traslado #{$traslado->id}", $anterior);
                $producto->update(['existencias' => round($anterior + (float) $item->cantidad, 2)]);
            }

            return $this->cerrar($traslado, $actor, $estadoFinal);
        });
    }

    // Traslado bloqueado y con su estado verificado dentro de la transaccion.
    private function bloquear(Traslado $traslado): Traslado
    {
        $bloqueado = Traslado::whereKey($traslado->id)->lockForUpdate()->firstOrFail();

        if (! $bloqueado->estaEnviado()) {
            throw new RuntimeException("Este traslado ya fue {$bloqueado->estado}.");
        }

        return $bloqueado->load('items');
    }

    private function cerrar(Traslado $traslado, User $actor, string $estado): Traslado
    {
        $traslado->update([
            'estado' => $estado,
            'resuelto_por' => $actor->id,
            'resuelto_at' => now(),
        ]);

        return $traslado;
    }

    // Crea en el destino el producto que no tenia, copiando lo del origen.
    private function crearProductoEnDestino(Traslado $traslado, TrasladoItem $item): Product
    {
        $destinoId = (int) $traslado->destino_id;
        $original = Product::where('empresa_id', $traslado->origen_id)
            ->where('id_producto', $item->producto_origen_id)
            ->first();

        $familia = Familia::firstOrCreate(['empresa_id' => $destinoId, 'nombre' => 'TRASLADOS RECIBIDOS']);
        $subfamilia = Subfamilia::firstOrCreate([
            'empresa_id' => $destinoId,
            'id_familia1' => $familia->id,
            'nombre' => 'TRASLADOS RECIBIDOS',
        ]);

        return Product::create([
            'id_producto' => (int) (Product::where('empresa_id', $destinoId)->max('id_producto') ?? 0) + 1,
            'empresa_id' => $destinoId,
            'id_familia1' => $familia->id,
            'id_familia2' => $subfamilia->id_familia2,
            'descripcion_larga' => $item->nombre,
            'tipo_producto' => 'producto',
            'vende_por' => $original?->vende_por ?? 'unidad',
            'maneja_inventario' => true,
            'permite_fraccion' => $original?->permite_fraccion ?? false,
            'iva_compra' => $original?->iva_compra ?? 0,
            'iva_venta' => $original?->iva_venta ?? 0,
            'existencias' => 0,
            'precio_costo' => $item->costo_unitario,
            'precio_venta1' => $item->precio_venta,
            'utilidad1' => $original?->utilidad1 ?? 0,
            'id_unidad_de_medida' => $original?->id_unidad_de_medida ?? 1,
        ]);
    }

    // La empresa con la que trabaja el usuario: el admin de la empresa o,
    // dentro de una sucursal, esa misma. Solo el admin_empresa puede operar.
    private function empresaDe(User $actor): User
    {
        if (! $actor->hasRole('admin_empresa')) {
            throw new RuntimeException('Solo el administrador de la empresa puede manejar traslados.');
        }

        return $actor->empresaPrincipal();
    }

    // Suma las lineas repetidas y valida cantidades: [id_producto => cantidad].
    private function unirLineas(array $lineas): array
    {
        $unidas = [];

        foreach ($lineas as $linea) {
            $id = (int) ($linea['id_producto'] ?? 0);
            $cantidad = round((float) ($linea['cantidad'] ?? 0), 2);

            if ($id <= 0 || $cantidad <= 0) {
                throw new RuntimeException('Cada producto debe tener una cantidad mayor que cero.');
            }

            $unidas[$id] = round(($unidas[$id] ?? 0) + $cantidad, 2);
        }

        if ($unidas === []) {
            throw new RuntimeException('Agrega al menos un producto al traslado.');
        }

        return $unidas;
    }
}
