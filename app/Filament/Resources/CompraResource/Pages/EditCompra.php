<?php

namespace App\Filament\Resources\CompraResource\Pages;

use App\Filament\Resources\CompraResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Actions;
use Filament\Notifications\Notification;
use App\Models\Product;
use App\Models\Compra;
use App\Models\CompraDetalle;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;

class EditCompra extends EditRecord
{
    protected static string $resource = CompraResource::class;

   protected function getHeaderActions(): array
    {
        return [
            // 🧹 LIMPIAR CACHE
            Actions\Action::make('limpiarCache')
                ->label('🧹 Limpiar ')
                ->color('warning')
                ->button()
                ->requiresConfirmation()
                ->action(function () {
                    $this->dispatch('clear-form-cache');

                    if ($this->record && $this->record->exists) {
                        $this->record->delete();
                    }

                    $this->form->fill([]);

                    Notification::make()
                        ->title('Compra borrador eliminada y cache limpiado.')
                        ->success()
                        ->send();

                    $this->redirect(CompraResource::getUrl('index'));
                }),

            // 💾 GUARDAR BORRADOR
            Actions\Action::make('guardarBorrador')
    ->label('💾 Guardar borrador')
    ->color('gray')
    ->button()
    ->visible(fn() => $this->record?->estado === 'borrador')
    ->action(function ($livewire) {

        // 🔥 VALIDACIÓN SOLO EDIT
       $tipoPago = data_get($this->form->getRawState(), 'tipo_pago');

// 🔹 Validación base (siempre obligatoria)
$livewire->validate([

    // CAMPOS CABECERA
    'data.numero_factura' => 'required',
    'data.tipo_pago'      => 'required',
    'data.fecha'          => 'required',

    // SI ES CRÉDITO → validar vencimiento
    'data.fecha_vencimiento' => 'required_if:data.tipo_pago,credito',

    // CAMPOS DEL DETALLE
    'data.detalles.*.costo_unitario' => 'required|numeric|min:1',
    'data.detalles.*.desc_comercial' => 'required|numeric|min:0',
    'data.detalles.*.iva_pct'        => 'required',
    'data.detalles.*.cantidad'       => 'required|numeric|min:1',

], [

    // MENSAJES CABECERA
    'data.numero_factura.required' => 'Debe ingresar el número de factura.',
    'data.tipo_pago.required'      => 'Debe seleccionar el tipo de pago.',
    'data.fecha.required'          => 'Debe ingresar la fecha de compra.',
    'data.fecha_vencimiento.required_if' => 'Debe ingresar la fecha de vencimiento si la compra es a crédito.',

    // MENSAJES DETALLES
    'data.detalles.*.costo_unitario.required' => 'El costo es obligatorio.',
    'data.detalles.*.costo_unitario.min'      => 'El costo debe ser mayor a 0.',

    'data.detalles.*.desc_comercial.required' => 'El descuento es obligatorio.',
    
    'data.detalles.*.iva_pct.required'        => 'Debe seleccionar el IVA.',

    'data.detalles.*.cantidad.required'       => 'Debe ingresar la cantidad.',
    'data.detalles.*.cantidad.min'            => 'La cantidad debe ser mayor que 0.',

]);

// 🔹 Validación adicional SOLO SI ES CRÉDITO
if ($tipoPago === 'credito') {
    $livewire->validate([
        'data.fecha_vencimiento' => 'required',
    ], [
        'data.fecha_vencimiento.required' => 'Debe ingresar la fecha de vencimiento.',
    ]);
}

        // --- NO MODIFIQUÉ NADA DE ABAJO ---
        $data = (array) $this->form->getRawState();
        $data['detalles'] = is_array($data['detalles'] ?? null) ? $data['detalles'] : [];

        if (count($data['detalles']) === 0) {
            Notification::make()->title('No hay productos en la compra')->body('Agrega al menos un producto para guardar el borrador.')->danger()->send();
            return;
        }

        $subtotal = $descuentoTotal = $impuestoTotal = $total = 0;
foreach ($data['detalles'] as $index => $detalle)  {

   // 🔥 Resolver siempre el producto REAL a partir del código_ingresado
$codigo = trim((string)($detalle['codigo_ingresado'] ?? ''));

// Si hay código, buscar el producto por código (id_producto)
$producto = Product::where('empresa_id', $data['empresa_id'])
    ->where('id_producto', $codigo)
    ->first();

if ($producto) {
    // Guardar SIEMPRE ambos valores correctos:
    $detalle['product_id'] = $producto->id;               // ID REAL
    $detalle['codigo_ingresado'] = $producto->id_producto; // CÓDIGO REAL
} else {

    // ⚠️ Si no lo encuentra (caso producto temporal),
    // product_id debe quedar NULL para no corromper datos
    $detalle['product_id'] = null;

    // Pero sí guardamos el código escrito por el usuario
    $detalle['codigo_ingresado'] = $codigo;
}

    // 3️⃣ Cálculo de totales del ítem
    $cantidad = (float)($detalle['cantidad'] ?? 0);
    $costo = (float)($detalle['costo_unitario'] ?? 0);
    $desc = (float)($detalle['desc_comercial'] ?? 0);
    $iva = (float)($detalle['iva_pct'] ?? 0);

    $lineaBruta = $cantidad * $costo;
    $lineaDescuento = $lineaBruta * ($desc / 100);
    $lineaBase = $lineaBruta - $lineaDescuento;
    $lineaImpuesto = $lineaBase * ($iva / 100);
    $lineaTotal = $lineaBase + $lineaImpuesto;

    $detalle['subtotal'] = $lineaBruta;
    $detalle['impuesto'] = $lineaImpuesto;
    $detalle['total'] = $lineaTotal;

    // Sumar a totales
    $subtotal += $lineaBruta;
    $descuentoTotal += $lineaDescuento;
    $impuestoTotal += $lineaImpuesto;
    $total += $lineaTotal;
}

        $data['subtotal'] = $subtotal;
        $data['descuento_total'] = $descuentoTotal;
        $data['impuesto_total'] = $impuestoTotal;
        $data['total'] = $total;
        $data['saldo'] = ($data['tipo_pago'] ?? '') === 'credito' ? $total : 0;

        $this->record->update($data);

        $this->guardarCompra('borrador');

        $this->dispatch('clear-form-cache');

        Notification::make()->title('Compra guardada como borrador.')->success()->send();

        $this->redirect(CompraResource::getUrl('index'));
    }),

            // ✅ CONFIRMAR COMPRA
            Actions\Action::make('confirmar')
    ->label('✅ Confirmar compra')
    ->color('success')
    ->button()
    ->visible(fn() => $this->record?->estado === 'borrador')
    ->requiresConfirmation()
    ->action(function ($livewire) {

        // 🔥 VALIDACIÓN SOLO EDIT
       $tipoPago = data_get($this->form->getRawState(), 'tipo_pago');

// 🔹 Validación base (siempre obligatoria)
$livewire->validate([

    // CAMPOS CABECERA
    'data.numero_factura' => 'required',
    'data.tipo_pago'      => 'required',
    'data.fecha'          => 'required',

    // SI ES CRÉDITO → validar vencimiento
    'data.fecha_vencimiento' => 'required_if:data.tipo_pago,credito',

    // CAMPOS DEL DETALLE
    'data.detalles.*.costo_unitario' => 'required|numeric|min:1',
    'data.detalles.*.desc_comercial' => 'required|numeric|min:0',
    'data.detalles.*.iva_pct'        => 'required',
    'data.detalles.*.cantidad'       => 'required|numeric|min:1',

], [

    // MENSAJES CABECERA
    'data.numero_factura.required' => 'Debe ingresar el número de factura.',
    'data.tipo_pago.required'      => 'Debe seleccionar el tipo de pago.',
    'data.fecha.required'          => 'Debe ingresar la fecha de compra.',
    'data.fecha_vencimiento.required_if' => 'Debe ingresar la fecha de vencimiento si la compra es a crédito.',

    // MENSAJES DETALLES
    'data.detalles.*.costo_unitario.required' => 'El costo es obligatorio.',
    'data.detalles.*.costo_unitario.min'      => 'El costo debe ser mayor a 0.',

    'data.detalles.*.desc_comercial.required' => 'El descuento es obligatorio.',
    
    'data.detalles.*.iva_pct.required'        => 'Debe seleccionar el IVA.',

    'data.detalles.*.cantidad.required'       => 'Debe ingresar la cantidad.',
    'data.detalles.*.cantidad.min'            => 'La cantidad debe ser mayor que 0.',

]);

// 🔹 Validación adicional SOLO SI ES CRÉDITO
if ($tipoPago === 'credito') {
    $livewire->validate([
        'data.fecha_vencimiento' => 'required',
    ], [
        'data.fecha_vencimiento.required' => 'Debe ingresar la fecha de vencimiento.',
    ]);
}

        // --- NO TOQUÉ NADA DE ABAJO ---
           $data = (array) $this->form->getRawState();
        $data['detalles'] = is_array($data['detalles'] ?? null) ? $data['detalles'] : [];

        if (count($data['detalles']) === 0) {
            Notification::make()
                ->title('No hay productos en la compra')
                ->body('Agrega productos para confirmar la compra.')
                ->danger()
                ->send();
            return;
        }

        // Resolver product_id a partir de codigo_ingresado para evitar inconsistencias
        foreach ($data['detalles'] as $index => $detalle)  {
            $codigo = trim((string)($detalle['codigo_ingresado'] ?? ''));

            $producto = Product::where('empresa_id', $data['empresa_id'])
                ->where('id_producto', $codigo)
                ->first();

            if ($producto) {
                // <-- Guardar exactamente el código (id_producto) como product_id
                // para que quede igual a codigo_ingresado (tal como solicitaste)
                $detalle['product_id'] = $producto->id_producto;
                $detalle['codigo_ingresado'] = $producto->id_producto;
            } else {
                $detalle['product_id'] = null;
                $detalle['codigo_ingresado'] = $codigo;
            }
        }

       $this->guardarCompra('confirmada');
       return;

        $this->dispatch('clear-form-cache');

        Notification::make()
            ->title('Compra confirmada correctamente.')
            ->success()
            ->send();

        $this->redirect(\App\Filament\Resources\CompraResource::getUrl('index'));
    }),

        ];
    }

    /**
     * 👉 Guarda cambios en la compra existente
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
    ->where('numero_factura', $data['numero_factura'])
    ->where('empresa_id', $data['empresa_id'])
    ->where('estado', '!=', 'anulada')
    ->where('id', '!=', $this->record->id) // 👈 EXCLUIR LA MISMA
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

           if (!empty($detalle['product_id'])) {

                // Si viene como id interno numérico → convertir a id_producto
                if (is_numeric($detalle['product_id'])) {

                    $codigo = \App\Models\Product::where('id', (int)$detalle['product_id'])
                        ->where('empresa_id', $data['empresa_id'])
                        ->value('id_producto');

                    if ($codigo) {
                        $detalle['product_id'] = $codigo;
                    }
                }
            }

            // Si se ingresó manualmente un código → respetarlo
            if (!empty($detalle['codigo_ingresado'])) {
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

        // 🧩 Crear compra principal
        if ($this->record) {
            // ✅ ESTAMOS EDITANDO → actualizar cabecera
            $this->record->update($data);

            // ✅ Eliminar detalles anteriores
            $this->record->detalles()->delete();

            $compra = $this->record;
        } else {
            // ✅ NUEVA COMPRA
            $compra = Compra::create($data);
        }

        // ✅ Guardar detalles NUEVOS
        foreach ($data['detalles'] ?? [] as $detalle) {
            $detalle['compra_id'] = $compra->id;

            // 🔹 Normalizar el identificador del producto
            if (!empty($detalle['codigo_ingresado'])) {
                // Si vino con código manual
                $detalle['product_id'] = $detalle['codigo_ingresado'];
            } else {
                // Si vino con id interno, convertirlo a id_producto
                $codigo = \App\Models\Product::where('id', (int)$detalle['product_id'])
                    ->where('empresa_id', $data['empresa_id'])
                    ->value('id_producto');

                if ($codigo) {
                    $detalle['product_id'] = $codigo;
                }
            }

            // Droguería: resuelve/crea el lote de esta entrada -- ver
            // CompraResource::resolverLoteId() y el mismo bloque en
            // CreateCompra.
            if (!empty($detalle['lote_texto'])) {
                $productoLote = Product::where('empresa_id', $data['empresa_id'])
                    ->where('id_producto', $detalle['product_id'])
                    ->first();

                if ($productoLote) {
                    $detalle['producto_lote_id'] = CompraResource::resolverLoteId(
                        $productoLote->id,
                        $data['empresa_id'],
                        $detalle['lote_texto'],
                        $detalle['lote_fecha_vencimiento'] ?? null
                    );
                }
            }

            // ✅ Crear detalle con product_id ya normalizado
            $nuevoDetalle = CompraDetalle::create($detalle);

            // ✅ Si está confirmada, actualizar existencias
            if ($estado === 'confirmada' && !empty($detalle['product_id'])) {
                $producto = Product::where('empresa_id', $data['empresa_id'])
                    ->where('id_producto', $detalle['product_id'])
                    ->first();

                if ($producto) {
                    // Aumentar existencias
                    $producto->existencias += $detalle['cantidad'] ?? 0;

                    // Lote puntual de esta linea (droguería): mismo criterio
                    // que Compra::confirmar() ya aplica para variantes.
                    if (! empty($detalle['producto_lote_id'])) {
                        \App\Models\ProductoLote::where('id', $detalle['producto_lote_id'])
                            ->where('empresa_id', $data['empresa_id'])
                            ->increment('stock', $detalle['cantidad'] ?? 0);
                    }

                    // 🔹 Actualizar precios igual que en confirmar()
                    $producto->precio_costo_anterior = $producto->precio_costo;
                    $producto->precio_venta_anterior = $producto->precio_venta1;

                    $costo = (float)($detalle['costo_unitario'] ?? 0);
                    $desc  = (float)($detalle['desc_comercial'] ?? 0);
                    $iva   = (float)($detalle['iva_pct'] ?? 0);
                    $util  = (float)($detalle['utilidad_pct'] ?? 0);
                    $pv    = (float)($detalle['precio_venta'] ?? 0);

                    $costoConDesc = round($costo * (1 - $desc / 100), 2);
                    $costoConIva  = round($costoConDesc * (1 + $iva / 100), 2);

                    if ($pv <= 0) {
                        $pv = round($costoConIva * (1 + $util / 100), 2);
                    }

                    $producto->precio_costo = $costo;
                    $producto->descuento_comercial = $desc;
                    $producto->precio_con_descuento = $costoConDesc;
                    $producto->costo_iva = $costoConIva;
                    $producto->iva_compra = $iva;
                    $producto->iva_venta = $iva;
                    $producto->utilidad1 = $util;
                    $producto->precio_venta1 = $pv;

                    $producto->save();
                }
            }
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
    $this->dispatch('clear-form-cache');

      $this->redirect(CompraResource::getUrl('index'));
}

    protected function hasFullPageFormActions(): bool
    {
        // ❌ Desactiva los botones estándar (Guardar / Cancelar)
        return false;
    }

    protected function getFormActions(): array
    {
        // 🔸 Borra los botones inferiores completamente
        return [];
    }
}
