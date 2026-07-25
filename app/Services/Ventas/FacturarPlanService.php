<?php

namespace App\Services\Ventas;

use App\Models\Actor;
use App\Models\ConfiguracionEmpresa;
use App\Models\Factura;
use App\Models\User;
use App\Services\Factus\FactusInvoiceService;
use Illuminate\Support\Facades\DB;

/**
 * Genera la factura del COBRO DE PLAN de una empresa cliente (lo que el
 * super_admin/dueno del sistema le cobra a esa empresa por su suscripcion),
 * a nombre de la "empresa emisora" marcada en EmpresaResource -- reusa la
 * misma tabla facturas/factura_detalles y el mismo ticket de impresion que
 * ya usa cada empresa para sus propias ventas, no es un sistema aparte.
 */
class FacturarPlanService
{
    public function facturar(User $empresaCliente, string $tipoFactura, string $medioPago): Factura
    {
        $emisora = User::where('tipo_usuario', 'empresa')
            ->where('es_empresa_emisora', true)
            ->first();

        if (! $emisora) {
            throw new \RuntimeException('No hay ninguna empresa marcada como "Empresa emisora". Configúrala primero en una empresa.');
        }

        if (blank($empresaCliente->valor_plan_total) || (float) $empresaCliente->valor_plan_total <= 0) {
            throw new \RuntimeException('Esta empresa no tiene un plan con valor calculado todavía.');
        }

        return DB::transaction(function () use ($empresaCliente, $emisora, $tipoFactura, $medioPago) {
            $cliente = $this->resolverClienteActor($empresaCliente, $emisora, $tipoFactura);
            $total = round((float) $empresaCliente->valor_plan_total, 2);

            $factura = Factura::create([
                'empresa_id' => $emisora->id,
                'cliente_id' => $cliente->id,
                'user_id' => auth()->id(),
                'vendedor_id' => auth()->id(),
                'cajero_id' => auth()->id(),
                'tipo_factura' => $tipoFactura,
                'tipo_pago' => 'contado',
                'medio_pago' => $medioPago,
                'fecha' => now(),
                'fecha_compra' => now(),
                'total' => 0,
                'saldo' => 0,
                'estado_pago' => 'pendiente',
                'observaciones' => 'Cobro de plan: ' . ($empresaCliente->plan?->nombre ?? 'Plan') . ' (' . ($empresaCliente->plan_meses ?? '?') . ' meses) - ' . $empresaCliente->name,
            ]);

            $factura->detalles()->create([
                'producto_id' => 0,
                'descripcion_larga' => 'Plan ' . ($empresaCliente->plan?->nombre ?? 'sin nombre') . ' - ' . $empresaCliente->name,
                'cantidad' => 1,
                'precio' => $total,
                'subtotal' => $total,
                'descuento' => 0,
            ]);

            $factura->registrarAbono(
                monto: $total,
                medio: $medioPago,
                nota: 'Pago del plan',
                userId: auth()->id(),
            );

            if ($tipoFactura === 'electronica') {
                $this->validarFacturaElectronica($factura);
            }

            return $factura->fresh();
        });
    }

    private function resolverClienteActor(User $empresaCliente, User $emisora, string $tipoFactura): Actor
    {
        $configCliente = ConfiguracionEmpresa::where('empresa_id', $empresaCliente->id)->first();

        if ($tipoFactura === 'electronica' && blank($configCliente?->nit)) {
            throw new \RuntimeException('Para facturar electrónicamente, primero completa el NIT de "' . $empresaCliente->name . '" en su configuración.');
        }

        // Identificacion estable por empresa cliente: si tiene NIT
        // configurado se usa ese (necesario para Factus/DIAN); si no, un
        // identificador sintetico -- asi no se duplica el Actor cada vez
        // que se le vuelve a facturar el plan a la misma empresa.
        $identificacion = $configCliente?->nit ?: ('CLI-' . $empresaCliente->id);

        return Actor::firstOrCreate(
            ['empresa_id' => $emisora->id, 'identificacion' => $identificacion],
            [
                'id_clip_pro' => (Actor::where('empresa_id', $emisora->id)->max('id_clip_pro') ?? 0) + 1,
                'nombre' => $empresaCliente->name,
                'razon_social' => $empresaCliente->name,
                'direccion' => $configCliente?->direccion ?: 'N/A',
                'telefono' => $empresaCliente->telefono ?: '0000000000',
                'email' => $empresaCliente->email,
                'departamento_id' => 1,
                'ciudad_id' => 1,
                'tipo_persona' => 'juridica',
                'tipo_documento_id' => 6,
                'regimen_tributario' => 'simplificado',
                'responsable_iva' => false,
                'clasificacion' => 'cliente',
                'tipo' => 1,
            ]
        );
    }

    private function validarFacturaElectronica(Factura $factura): void
    {
        $factura->load(['cliente.ciudad', 'detalles']);

        app(FactusInvoiceService::class)->validate($factura);

        $factura->refresh();

        if (blank($factura->factus_number) || blank($factura->factus_cufe) || $factura->factus_status !== 'validada') {
            throw new \RuntimeException('Factus no validó la factura electrónica. Revisa la configuración Factus de la empresa emisora.');
        }
    }
}
