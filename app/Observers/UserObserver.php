<?php

namespace App\Observers;

use App\Models\User;
use App\Models\Actor;
use App\Models\CuentaContable;
use App\Models\Product;
use App\Models\Familia;
use App\Models\Subfamilia;
use Database\Seeders\CuentasContablesPucSeeder;

class UserObserver
{
    public function created(User $user)
    {
        if ($user->tipo_usuario !== 'empresa') {
            return;
        }

        // 🔢 Obtener último ID de Actor
        $ultimoId = Actor::max('id_clip_pro') ?? 0;

        // 👤 Cliente: CONSUMIDOR FINAL
        $consumidorFinal = Actor::create([
            'id_clip_pro'        => ++$ultimoId,
            'empresa_id'         => $user->id,
            'tipo_documento_id'  => 6,
            'identificacion'     => '222222222',
            'nombre'             => 'CONSUMIDOR FINAL',
            'razon_social'       => 'CONSUMIDOR FINAL',
            'direccion'          => 'N/A',
            'telefono'           => '0000000000',
            'email'              => 'consumidor@final.com',
            'departamento_id'    => 1,
            'ciudad_id'          => 1,
            'tipo_persona'       => 'natural',
            'regimen_tributario' => 'simplificado',
            'responsable_iva'    => 0,
            'clasificacion'      => 'cliente',
            'tipo'               => 1,
        ]);

        // 🧾 Proveedor: PROVEEDOR DE PRUEBA
        $proveedor = Actor::create([
            'id_clip_pro'        => ++$ultimoId,
            'empresa_id'         => $user->id,
            'tipo_documento_id'  => 6,
            'identificacion'     => '111111111',
            'nombre'             => 'PROVEEDOR DE PRUEBA',
            'razon_social'       => 'PROVEEDOR DE PRUEBA',
            'direccion'          => 'N/A',
            'telefono'           => '0000000000',
            'email'              => 'proveedor@prueba.com',
            'departamento_id'    => 1,
            'ciudad_id'          => 1,
            'tipo_persona'       => 'juridica',
            'regimen_tributario' => 'simplificado',
            'responsable_iva'    => 0,
            'clasificacion'      => 'proveedor',
            'tipo'               => 3,
        ]);

        // 📦 Familia: FAMILIA DE PRUEBA
        $familia = Familia::create([
            'empresa_id' => $user->id,
            'nombre'     => 'FAMILIA DE PRUEBA',
        ]);

        // 📦 Subfamilia: SUBFAMILIA DE PRUEBA
        $subfamilia = Subfamilia::create([
            'empresa_id'    => $user->id,
            'id_familia1'   => $familia->id,
            'nombre'        => 'SUBFAMILIA DE PRUEBA',
        ]);

        // 🧪 Producto de prueba: código 10001
        Product::create([
            'id_producto'         => '10001',
            'empresa_id'          => $user->id,
            'descripcion_larga'   => 'PRODUCTO DE PRUEBA',
            'id_proveedor'        => $proveedor->id_clip_pro,
            'iva_compra'          => 0,
            'iva_venta'           => 0,
            'existencias'         => 0,
            'precio_costo'        => 0,
            'precio_venta1'       => 0,
            'utilidad1'           => 0,
            'id_unidad_de_medida' => 1,
            'id_familia1'         => $familia->id,
            'id_familia2'         => $subfamilia->id_familia2,
        ]);

        // 📊 Plan de cuentas contables básico (PUC), para que la empresa
        // tenga de una vez las cuentas principales para asignar en
        // productos, cajas, etc.
        foreach (CuentasContablesPucSeeder::cuentasBase() as $cuenta) {
            CuentaContable::firstOrCreate(
                [
                    'empresa_id' => $user->id,
                    'codigo'     => $cuenta['codigo'],
                ],
                [
                    'nombre'    => $cuenta['nombre'],
                    'tipo'      => $cuenta['tipo'],
                    'categoria' => $cuenta['categoria'],
                    'activo'    => true,
                ]
            );
        }
    }
}
