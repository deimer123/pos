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
    ->action(function ($livewire) {

        // 🔥 Validación manual (solo español, sin mensajes en inglés)
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

        // Si falla validar → NO pasa de aquí
        if ($this->guardarCompra('borrador') === false) {
            return false;
        }

        // ✓ Guardado correcto
        $this->dispatch('clear-form-cache');

        $this->redirect(CompraResource::getUrl('index'));
    }),
Actions\Action::make('confirmar')
    ->label('✅ Confirmar factura')
    ->color('primary')
    ->button()
    ->requiresConfirmation()
    ->action(function ($livewire) {

        // ========================
        // 🔥 VALIDACIÓN
        // ========================
        $tipoPago = data_get($this->form->getRawState(), 'tipo_pago');

        $livewire->validate([
            'data.numero_factura'        => 'required',
            'data.tipo_pago'             => 'required',
            'data.fecha'                 => 'required',
            'data.fecha_vencimiento'     => 'required_if:data.tipo_pago,credito',
            'data.detalles.*.costo_unitario' => 'required|numeric|min:1',
            'data.detalles.*.desc_comercial' => 'required|numeric|min:0',
            'data.detalles.*.iva_pct'        => 'required',
            'data.detalles.*.cantidad'       => 'required|numeric|min:1',
        ]);

        if ($tipoPago === 'credito') {
            $livewire->validate([
                'data.fecha_vencimiento' => 'required',
            ]);
        }

        // ========================
        // 🔥 OBTENER DATA ACTUAL DEL FORMULARIO
        // ========================
        $data = $this->form->getState();

        // Validar que existan productos
        if (empty($data['detalles']) || count($data['detalles']) === 0) {
            Notification::make()
                ->title('No hay productos en la compra')
                ->danger()
                ->send();
            return;
        }

        // ========================
        // 🔥 APLICAR LA MISMA LÓGICA DEL EDIT
        // ========================
        $subtotal = 0;
        $descuentoTotal = 0;
        $impuestoTotal = 0;
        $total = 0;

      foreach ($data['detalles'] as $index => $detalle)  {

    // 1️⃣ Si el usuario ingresó un código manual → usar ese SIEMPRE
    if (!empty($detalle['codigo_ingresado'])) {
        $detalle['product_id'] = (string) $detalle['codigo_ingresado'];
    }

    // 2️⃣ Si viene desde el buscador, el campo product_id todavía es el ID interno
    elseif (!empty($detalle['product_id'])) {
        $codigoReal = Product::where('empresa_id', $data['empresa_id'])
            ->where('id', $detalle['product_id']) // ID interno
            ->value('id_producto');

        if ($codigoReal) {
            $detalle['product_id'] = (string)$codigoReal;
        } else {
            // fallback
            $detalle['product_id'] = (string)$detalle['product_id'];
        }
    }

    // Droguería: resuelve/crea el lote de esta entrada (find-or-create por
    // numero de lote, ver CompraResource::resolverLoteId()) -- product_id
    // ya es el id_producto (codigo) en este punto, se necesita el id
    // interno real para la FK de producto_lotes. $data['empresa_id']
    // todavia no esta poblado aca (se asigna mas abajo), se resuelve
    // aparte con el mismo criterio.
    if (!empty($detalle['lote_texto'])) {
        $empresaIdLote = auth()->user()->tipo_usuario === 'empresa'
            ? auth()->id()
            : (auth()->user()->empresa_id ?? null);

        $productoLote = Product::where('empresa_id', $empresaIdLote)
            ->where('id_producto', $detalle['product_id'])
            ->first();

        if ($productoLote) {
            $detalle['producto_lote_id'] = CompraResource::resolverLoteId(
                $productoLote->id,
                $empresaIdLote,
                $detalle['lote_texto'],
                $detalle['lote_fecha_vencimiento'] ?? null
            );
        }
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
    $data['detalles'][$index] = $detalle;
    // Sumar a totales
    $subtotal += $lineaBruta;
    $descuentoTotal += $lineaDescuento;
    $impuestoTotal += $lineaImpuesto;
    $total += $lineaTotal;
}

        // Guardar cifras totales
        $data['subtotal']        = $subtotal;
        $data['descuento_total'] = $descuentoTotal;
        $data['impuesto_total']  = $impuestoTotal;
        $data['total']           = $total;
        $data['saldo']           = ($data['tipo_pago'] === 'credito') ? $total : 0;


        // ===================================
// 🔹 ASIGNAR EMPRESA Y USUARIO LOGUEADO
// ===================================
$user = auth()->user();

// ID del usuario que crea la compra
$data['user_id'] = $user->id;

// ID de la empresa (misma lógica que usas en otras partes)
$data['empresa_id'] = $user->tipo_usuario === 'empresa'
    ? $user->id
    : ($user->empresa_id ?? null);

        // ========================
// 🔧 OBLIGATORIO: RESOLVER proveedor_id
// ========================
if (empty($data['proveedor_id'])) {

    // Buscar proveedor desde el estado real del formulario
    $raw = $this->form->getRawState();

    if (!empty($raw['proveedor_id'])) {
        $data['proveedor_id'] = $raw['proveedor_id'];
    }
}

// Validar proveedor
if (empty($data['proveedor_id'])) {
    Notification::make()
        ->title('Debe seleccionar un proveedor.')
        ->danger()
        ->send();
    return;
}

$data['proveedor_id'] = $this->resolverProveedorId((int) $data['proveedor_id'], (int) $data['empresa_id']);

if (empty($data['proveedor_id'])) {
    Notification::make()
        ->title('Proveedor no valido para esta empresa.')
        ->danger()
        ->send();
    return;
}

// ========================
// 🔥 GUARDAR CABECERA
// ========================
$data['subtotal'] = $subtotal;
$data['descuento_total'] = $descuentoTotal;
$data['impuesto_total'] = $impuestoTotal;
$data['total'] = $total;
$data['saldo'] = ($data['tipo_pago'] === 'credito') ? $total : 0;

$compra = Compra::create($data);

// ========================
// 🔥 GUARDAR DETALLES
// ========================
foreach ($data['detalles'] as $detalle) {
    $detalle['compra_id'] = $compra->id;
    CompraDetalle::create($detalle);
}

// ========================
// 🔥 CONFIRMAR SI ES CONFIRMADA
// ========================
 $compra->confirmar();

// ========================
// 🔥 LIMPIAR FORM & CACHE
// ========================
$this->dispatch('clear-form-cache');
$this->form->fill([]);
$this->reset('record');

        Notification::make()
            ->title('Compra confirmada correctamente.')
            ->success()
            ->send();

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
        $data['proveedor_id'] = $this->resolverProveedorId((int) $data['proveedor_id'], (int) $data['empresa_id']);

        if (empty($data['proveedor_id'])) {
            Notification::make()
                ->title('Proveedor no valido para esta empresa.')
                ->danger()
                ->send();
            return false;
        }

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

    // Droguería: resuelve/crea el lote de esta entrada -- ver el mismo
    // bloque en la accion "confirmar" de arriba.
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

        try {
            DB::transaction(fn () => $this->record->anular());
        } catch (\RuntimeException $e) {
            Notification::make()
                ->title($e->getMessage())
                ->danger()
                ->send();

            return;
        }

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
            $proveedor = \App\Models\Actor::query()
                ->where('empresa_id', $data['empresa_id'])
                ->whereKey((int) $data['proveedor_id'])
                ->first();
            if ($proveedor) {
                $data['proveedor_id'] = $proveedor->id;
            } else {
                $data['proveedor_id'] = null;
            }
        } else {
            $data['proveedor_id'] = null;
        }

        return $data;
    }

    public static function canCreateAnother(): bool
    {
        return false;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    private function resolverProveedorId(int $valor, int $empresaId): ?int
    {
        if ($valor <= 0) {
            return null;
        }

        $proveedor = \App\Models\Actor::query()
            ->where('empresa_id', $empresaId)
            ->whereIn('clasificacion', ['proveedor', 'cliente_proveedor'])
            ->whereKey($valor)
            ->first();

        if ($proveedor) {
            return (int) $proveedor->id;
        }

        $proveedor = \App\Models\Actor::query()
            ->where('empresa_id', $empresaId)
            ->whereIn('clasificacion', ['proveedor', 'cliente_proveedor'])
            ->where('id_clip_pro', $valor)
            ->first();

        return $proveedor ? (int) $proveedor->id : null;
    }

}
