<?php

namespace App\Filament\Resources\CompraResource\Pages;

use App\Filament\Resources\CompraResource;
use App\Models\Compra;
use App\Models\CompraDetalle;
use App\Models\Product;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Actions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;

class CreateCompra extends CreateRecord
{
    protected static string $resource = CompraResource::class;

    /**
     * 🔹 Botones personalizados
     */
    protected function getFormActions(): array
    {
        $record = $this->record;

        if ($record && $record->estado === 'confirmada') {
            return [
                Actions\Action::make('anular')
                    ->label('❌ Anular compra')
                    ->color('danger')
                    ->button()
                    ->requiresConfirmation()
                    ->action(fn() => $this->anularCompra()),
            ];
        }

        return [
          Actions\Action::make('guardarBorrador')
    ->label('💾 Guardar borrador')
    ->color('gray')
    ->button()
    ->action(function () {

        // Llamamos a guardarCompra, si devuelve false → no hacemos nada
        if ($this->guardarCompra('borrador') === false) {
            return false; // ⛔ NO REDIRIGIR, NO LIMPIAR, NO ENVIAR ÉXITO
        }

        // ✅ Si llegó aquí significa que se guardó correctamente
        $this->dispatch('clear-form-cache');


        $this->redirect(CompraResource::getUrl('index'));
    }),
Actions\Action::make('confirmar')
    ->label('✅ Confirmar factura')
    ->color('primary')
    ->button()
    ->requiresConfirmation()
    ->action(function () {

        // ⛔ Si falla una validación → NO continuar
        if ($this->guardarCompra('confirmada') === false) {
            return;
        }

        $compra = Compra::latest('id')
            ->where('user_id', auth()->id())
            ->first();

        if ($compra) {
            $compra->confirmar();
        }

        $this->dispatch('clear-form-cache');

       

        $this->redirect(CompraResource::getUrl('index'));
    }),



            Actions\Action::make('limpiarCache')
                ->label('🧹 Limpiar')
                ->color('warning')
                ->button()
                ->requiresConfirmation()
                ->action(function () {
                    // ✅ Forma correcta en Filament 3
                    $this->dispatch('clear-form-cache');

                    // 🔹 Limpiar formulario
                    $this->form->fill([]);

                    // 🔹 Notificación
                    \Filament\Notifications\Notification::make()
                        ->title('Cache limpiado correctamente.')
                        ->success()
                        ->send();

                    $this->redirect(\App\Filament\Resources\CompraResource::getUrl('index'));
                }),
        ];
    }

    /**
     * 🔹 Crear el registro principal de compra
     */
    protected function handleRecordCreation(array $data): Model
    {
        return Compra::create($data);
    }

    /**
     * 🔹 Guarda compra + detalles
     */
    public function guardarCompra(string $estado)
    {
       $data = $this->form->getRawState() ?? [];
        $user = Auth::user();

        /* ✅ VALIDAR QUE EXISTA AL MENOS UN ÍTEM */
    $items = collect($data['detalles'] ?? [])
    ->map(function ($row) {

        // Si viene como modelo → convertirlo
        if ($row instanceof \Illuminate\Database\Eloquent\Model) {
            $row = $row->toArray();
        }

        // Asegurar que product_id siempre exista
        if (empty($row['product_id']) && !empty($row['codigo_ingresado'])) {
            $row['product_id'] = $row['codigo_ingresado'];
        }

        return $row;
    })
    ->filter(fn($row) => !empty($row['product_id']))
    ->count();

    if ($items === 0) {
        Notification::make()
            ->title('No hay productos en la compra')
            ->body('Debe agregar al menos un ítem para guardar o confirmar.')
            ->danger()
            ->send();

        return false; // ⛔ Detener aquí
    }

        // 🧠 Determinar empresa_id según tipo_usuario
        if ($user->tipo_usuario === 'empresa') {
            $data['empresa_id'] = $user->id;
        } else {
            $data['empresa_id'] = $user->empresa_id ?? null;
        }

        // 👤 Usuario actual
        $data['user_id'] = $user->id;

        // 🧩 Asegurar que el proveedor_id se obtenga correctamente del formulario
        if (empty($data['proveedor_id'])) {
            $data['proveedor_id'] = $this->data['proveedor_id'] ?? null;
        }

        // ⚠️ Validar proveedor seleccionado
        if (empty($data['proveedor_id'])) {
            Notification::make()
                ->title('Debe seleccionar un proveedor antes de guardar la compra.')
                ->danger()
                ->send();
            return false;
        }

        // ✅ Validar fechas
        if (($data['tipo_pago'] ?? 'contado') === 'credito') {

    if (empty($data['fecha_vencimiento']) || $data['fecha_vencimiento'] <= $data['fecha']) {

        Notification::make()
            ->title('Error en las fechas')
            ->body('La fecha de vencimiento debe ser mayor que la fecha de compra en compras a crédito.')
            ->danger()
            ->send();

        return false; // ⛔️ IMPORTANTE
    }

} else {
    // Si es contado, no necesitamos fecha de vencimiento
   $data['fecha_vencimiento'] = $data['fecha'] ?? now();
}

        // ✅ Evitar factura duplicada para mismo proveedor
        $duplicada = Compra::where('proveedor_id', $data['proveedor_id'])
            ->where('numero_factura', $data['numero_factura'] ?? null)
            ->where('empresa_id', $data['empresa_id'])
            ->where('estado', '!=', 'anulada')
            ->exists();

        if ($duplicada) {
            Notification::make()
                ->title('Factura duplicada')
                ->body('Ya existe una factura con este número para este proveedor.')
                ->danger()
                ->send();
            return false;
        }

        // ✅ Guardar compra + detalles
       DB::transaction(function () use ($data, $estado) {

    // 🔸 INICIO CÁLCULO DE TOTALES
    $subtotal = 0;
    $descuentoTotal = 0;
    $impuestoTotal = 0;
    $total = 0;

    foreach ($data['detalles'] ?? [] as &$detalle) {
        $cantidad = (float)($detalle['cantidad'] ?? 0);
        $costo = (float)($detalle['costo_unitario'] ?? 0);
        $iva = (float)($detalle['iva_pct'] ?? 0);
        $descP = (float)($detalle['desc_comercial'] ?? 0);

        $lineaBruta = $cantidad * $costo;
        $lineaDescuento = $lineaBruta * ($descP / 100);
        $lineaBase = $lineaBruta - $lineaDescuento;
        $lineaImpuesto = $lineaBase * ($iva / 100);
        $lineaTotal = $lineaBase + $lineaImpuesto;

        // Guardar valores calculados en el detalle
        $detalle['subtotal'] = $lineaBruta;
        $detalle['impuesto'] = $lineaImpuesto;
        $detalle['total'] = $lineaTotal;

        // Acumular totales
        $subtotal += $lineaBruta;
        $descuentoTotal += $lineaDescuento;
        $impuestoTotal += $lineaImpuesto;
        $total += $lineaTotal;

        // 🧩 Forzar código del producto como identificador real
        if (isset($detalle['codigo_ingresado']) && !empty($detalle['codigo_ingresado'])) {
            $detalle['product_id'] = $detalle['codigo_ingresado'];
        }
    }

    // Asignar a los campos reales de la tabla
    $data['estado'] = $estado;
    $data['subtotal'] = $subtotal;
    $data['descuento_total'] = $descuentoTotal;
    $data['impuesto_total'] = $impuestoTotal;
    $data['total'] = $total;

    // Si es crédito, el saldo es el total pendiente
    $data['saldo'] = ($data['tipo_pago'] ?? '') === 'credito' ? $total : 0;

    // 🔸 FIN CÁLCULO DE TOTALES

    // 🧩 Crear compra principal
    if ($this->record) {
    // ✅ ESTAMOS EDITANDO → actualizar cabecera
    $this->record->update($data);

    // ✅ Eliminar detalles anteriores
    $this->record->detalles()->delete();

    $compra = $this->record;
} else {
    // ✅ NUEVA COMPRA
    $compra = $this->handleRecordCreation($data);
}

// ✅ Guardar detalles NUEVOS
foreach ($data['detalles'] ?? [] as $detalle) {
    $detalle['compra_id'] = $compra->id;

    if (!empty($detalle['codigo_ingresado'])) {
        $detalle['product_id'] = $detalle['codigo_ingresado'];
    }

    CompraDetalle::create($detalle);
        
    }
});

        // 🟢 Notificación final
        $mensaje = $estado === 'borrador'
            ? 'Compra guardada como borrador.'
            : 'Compra confirmada y existencias actualizadas.';

        Notification::make()
            ->title($mensaje)
            ->success()
            ->send();

        // 🧹 Limpieza del formulario
        $this->limpiarFormulario();
    }

    /**
     * 🔹 Anular compra confirmada
     */
    public function anularCompra()
    {
        if (!$this->record) {
            return;
        }

        DB::transaction(function () {
            $compra = $this->record;

            // Devolver existencias
            foreach ($compra->detalles as $detalle) {
                $producto = Product::find($detalle->product_id);
                if ($producto) {
                    $producto->existencias -= $detalle->cantidad;
                    $producto->save();
                }
            }

            $compra->update([
                'estado' => 'anulada',
                'anulada_at' => now(),
            ]);
        });

        Notification::make()
            ->title('Compra anulada y existencias actualizadas.')
            ->success()
            ->send();

        $this->redirect(CompraResource::getUrl('index'));
    }

    /**
     * 🔹 Limpiar formulario
     */
    public function limpiarFormulario(): void
    {
        $this->form->fill([]);
        $this->reset('record');
    }

    /**
     * 🔹 Mutar datos antes de crear (refuerzo adicional)
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();

        // 🔹 Determinar empresa_id según tipo de usuario
        if ($user->tipo_usuario === 'empresa') {
            $data['empresa_id'] = $user->id;
        } else {
            $data['empresa_id'] = $user->empresa_id ?? null;
        }

        // 🔹 Guardar el usuario que crea la compra
        $data['user_id'] = $user->id;

        // 🔹 Asegurar que proveedor_id venga como entero o nulo
        if (isset($data['proveedor_id']) && filled($data['proveedor_id'])) {
            $proveedor = \App\Models\Actor::where('id_clip_pro', $data['proveedor_id'])->first();
            if ($proveedor) {
                $data['proveedor_id'] = $proveedor->id_clip_pro;
            } else {
                $data['proveedor_id'] = null;
            }
        } else {
            $data['proveedor_id'] = null;
        }

        return $data;
    }
}
