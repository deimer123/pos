<?php

namespace App\Console\Commands;

use App\Models\Actor;
use App\Models\Familia;
use App\Models\Product;
use App\Models\Subfamilia;
use App\Models\User;
use App\Services\Grupo\GrupoEmpresas;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

// Productos, clientes y proveedores de relleno para PROBAR sucursales (inventario del grupo y, mas
// adelante, traslados) en una base de desarrollo. Se niega a correr en
// produccion. No borra ni cambia nada existente: solo agrega lo que falte
// (por nombre) y deja el stock de lo que ya exista como esta.
class GrupoDemo extends Command
{
    protected $signature = 'grupo:demo {matriz? : Correo o id de la empresa dueña (por defecto, la primera que tenga sucursales)}';

    protected $description = 'Crea productos, clientes y proveedores de relleno en una empresa dueña y sus sucursales, para probar sucursales (solo desarrollo)';

    // nombre => [precio_costo, precio_venta, stock matriz, stock por sucursal (en orden)]
    // null = ese producto no existe en esa empresa.
    private const PRODUCTOS = [
        'Pan x12'              => [4000, 6000, 120, [40, 25, 60]],
        'Pan integral'         => [4500, 6500, 60, [20, 0, 15]],
        'Croissant'            => [1800, 3000, 80, [35, 12, null]],
        'Torta de chocolate'   => [22000, 35000, 10, [4, null, 6]],
        'Almojábana'           => [1200, 2000, 150, [60, 45, 30]],
        'Pandebono'            => [1300, 2200, 140, [50, 0, 55]],
        'Empanada de pollo'    => [1500, 2500, 90, [30, 40, null]],
        'Galletas de avena'    => [900, 1800, 200, [80, 75, 90]],
        'Gaseosa 400ml'        => [1500, 2500, 96, [48, 24, 36]],
        'Jugo de naranja'      => [2200, 4000, 40, [10, null, 14]],
        'Café americano'       => [800, 2500, 300, [100, 90, 120]],
        'Leche 1L'             => [3200, 4200, 48, [24, 12, 0]],
        'Huevos x30'           => [14000, 18000, 20, [8, 6, 10]],
        'Mantequilla 250g'     => [5500, 7500, 25, [null, 9, 12]],
        'Azúcar 1kg'           => [3500, 4800, 50, [20, 25, 18]],
        'Producto solo matriz' => [1000, 2000, 33, [null, null, null]],
    ];

    public function handle(GrupoEmpresas $grupo): int
    {
        if (app()->environment('production')) {
            $this->error('Este comando es solo para desarrollo y se niega a correr en producción.');

            return self::FAILURE;
        }

        $matriz = $this->buscarMatriz();

        if (! $matriz) {
            $this->error('No encontré una empresa dueña con sucursales. Crea una sucursal desde Empresas → "Es sucursal de" o indica el correo/id.');

            return self::FAILURE;
        }

        $sucursales = $matriz->sucursales()->orderBy('id')->get();

        if ($sucursales->isEmpty()) {
            $this->error("«{$matriz->name}» no tiene sucursales ligadas.");

            return self::FAILURE;
        }

        $this->info("Empresa dueña: {$matriz->name} (id {$matriz->id})");

        $creados = $this->sembrar($matriz, 2);
        $actores = $this->sembrarActores($matriz);

        foreach ($sucursales as $i => $sucursal) {
            $this->info("Sucursal: {$sucursal->name} (id {$sucursal->id})");
            $creados += $this->sembrar($sucursal, 3, $i);
            $actores += $this->sembrarActores($sucursal);
        }

        $this->newLine();
        $this->info("Listo: {$creados} productos y {$actores} clientes/proveedores nuevos. Lo que ya existía no se tocó.");

        return self::SUCCESS;
    }

    private function buscarMatriz(): ?User
    {
        $dato = $this->argument('matriz');

        if ($dato !== null) {
            return User::where('tipo_usuario', 'empresa')
                ->where(fn ($q) => $q->where('email', $dato)->orWhere('id', is_numeric($dato) ? (int) $dato : 0))
                ->first();
        }

        return User::where('tipo_usuario', 'empresa')
            ->whereIn('id', User::whereNotNull('empresa_matriz_id')->select('empresa_matriz_id'))
            ->orderBy('id')
            ->first();
    }

    // [nombre, identificacion, telefono]
    private const CLIENTES = [
        ['Ana María Rodríguez', '1090123456', '3101112233'],
        ['Carlos Pérez', '1090234567', '3102223344'],
        ['Tienda La Esquina', '900123456', '3103334455'],
        ['Restaurante El Sabor', '900234567', '3104445566'],
        ['Luisa Fernanda Gómez', '1090345678', '3105556677'],
    ];

    private const PROVEEDORES = [
        ['Distribuidora de Harinas del Oriente', '900345678', '3201112233'],
        ['Lácteos La Sabana', '900456789', '3202223344'],
        ['Bebidas y Gaseosas del Llano', '900567890', '3203334455'],
    ];

    // Los mismos clientes/proveedores en cada empresa del grupo, mas un cliente
    // exclusivo por empresa para comprobar que no se mezclan.
    private function sembrarActores(User $empresa): int
    {
        $creados = 0;

        $clientes = self::CLIENTES;
        $clientes[] = ['Cliente exclusivo de ' . $empresa->name, '1099' . str_pad((string) $empresa->id, 6, '0', STR_PAD_LEFT), '3109990000'];

        foreach ($clientes as [$nombre, $identificacion, $telefono]) {
            $creados += $this->actor($empresa, 'cliente', 1, $nombre, $identificacion, $telefono);
        }

        foreach (self::PROVEEDORES as [$nombre, $identificacion, $telefono]) {
            $creados += $this->actor($empresa, 'proveedor', 3, $nombre, $identificacion, $telefono);
        }

        return $creados;
    }

    private function actor(User $empresa, string $clasificacion, int $tipo, string $nombre, string $identificacion, string $telefono): int
    {
        $existente = Actor::where('empresa_id', $empresa->id)
            ->where('identificacion', $identificacion)
            ->where('clasificacion', $clasificacion)
            ->exists();

        if ($existente) {
            return 0;
        }

        Actor::create([
            'id_clip_pro' => (Actor::max('id_clip_pro') ?? 0) + 1,
            'empresa_id' => $empresa->id,
            'tipo_documento_id' => 6,
            'identificacion' => $identificacion,
            'nombre' => $nombre,
            'razon_social' => $nombre,
            'direccion' => 'Calle 1 # 2-3',
            'telefono' => $telefono,
            'email' => Str::slug($nombre) . '@demo.test',
            'departamento_id' => 1,
            'ciudad_id' => 1,
            'tipo_persona' => 'natural',
            'regimen_tributario' => 'simplificado',
            'responsable_iva' => 0,
            'clasificacion' => $clasificacion,
            'tipo' => $tipo,
        ]);

        return 1;
    }

    // $columna: 2 = stock de la matriz; 3 = lista de sucursales, tomando $indice.
    private function sembrar(User $empresa, int $columna, int $indice = 0): int
    {
        $familia = Familia::firstOrCreate(['empresa_id' => $empresa->id, 'nombre' => 'DEMO SUCURSALES']);
        $subfamilia = Subfamilia::firstOrCreate([
            'empresa_id' => $empresa->id,
            'id_familia1' => $familia->id,
            'nombre' => 'DEMO SUCURSALES',
        ]);

        $creados = 0;
        $codigo = 20000;

        foreach (self::PRODUCTOS as $nombre => $datos) {
            $codigo++;
            $stock = $columna === 2 ? $datos[2] : ($datos[3][$indice] ?? null);

            if ($stock === null) {
                continue;
            }

            $producto = Product::firstOrCreate(
                ['empresa_id' => $empresa->id, 'descripcion_larga' => $nombre],
                [
                    'id_producto' => $codigo,
                    'id_familia1' => $familia->id,
                    'id_familia2' => $subfamilia->id_familia2,
                    'iva_compra' => 0,
                    'iva_venta' => 0,
                    'existencias' => $stock,
                    'precio_costo' => $datos[0],
                    'precio_venta1' => $datos[1],
                    'utilidad1' => 0,
                    'id_unidad_de_medida' => 1,
                ],
            );

            $creados += $producto->wasRecentlyCreated ? 1 : 0;
        }

        return $creados;
    }
}
