<?php

namespace App\Filament\Resources\CompraResource\Pages;

use App\Filament\Resources\CompraResource;
use Filament\Actions;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use App\Models\Pago;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Filament\Forms;
use App\Models\NotaCredito;
use App\Models\NotaCreditoItem;



class ViewCompra extends ViewRecord
{
    protected static string $resource = CompraResource::class;

    public function mount($record): void
    {
        parent::mount($record);

        // Si es borrador, redirige a Create con ?draft={id}
        if ($this->record?->estado === 'borrador') {
            $this->redirect(
                CompraResource::getUrl('create', [
                    'draft' => $this->record->getKey(),
                ])
            );
        }
    }

    public function infolist(Infolist $infolist): Infolist
    {
        // Evita N+1 en detalles
        $this->record->load('detalles');

        return $infolist->schema(
            CompraResource::viewInfolistSchema()
        );
    }

   public function getHeaderActions(): array
{
    return [
        Actions\EditAction::make()
            ->visible(fn(): bool => $this->record->estado !== 'anulada'),

        // 💰 Registrar pago
        // 💰 Registrar pago
Actions\Action::make('registrarPago')
    ->label('💰 Registrar pago')
    ->color('success')
    ->icon('heroicon-o-currency-dollar')
    ->visible(fn() =>
    $this->record->tipo_pago === 'credito' &&
    !in_array($this->record->estado, ['pagada', 'anulada'])
)
    ->form(function () {

        // ✅ ahora el saldo es directo, sin recalcular
        $saldoPendiente = $this->record->total ?? 0;

        return [
            Forms\Components\Placeholder::make('saldo')
                ->label('💵 Saldo pendiente')
                ->content(fn() => '$ ' . number_format($saldoPendiente, 0, ',', '.'))
                ->columnSpanFull(),

            Forms\Components\Grid::make(2)->schema([
                Forms\Components\DatePicker::make('fecha')
                    ->label('Fecha')
                    ->default(now())
                    ->required(),

                Forms\Components\TextInput::make('referencia')
                    ->label('Referencia')
                    ->placeholder('Comprobante o nota')
                    ->maxLength(150),
            ]),

            Forms\Components\Grid::make(2)->schema([
                Forms\Components\TextInput::make('monto')
                    ->label('Monto a pagar')
                    ->numeric()
                    ->required()
                    ->reactive()
                     ->prefix(fn ($state) => '$ ' . number_format((float)$state, 0, ',', '.'))
                    ->afterStateUpdated(function ($state, callable $set) use ($saldoPendiente) {
                        if ($state > $saldoPendiente) {
                            Notification::make()
                                ->title('El monto ingresado supera el saldo pendiente.')
                                ->danger()
                                ->send();

                            $set('monto', $saldoPendiente);
                        }
                    }),

                Forms\Components\Select::make('metodo_pago')
                    ->label('Método')
                    ->options([
                        'efectivo' => 'Efectivo',
                        'transferencia' => 'Transferencia',
                        'tarjeta' => 'Tarjeta',
                        'otro' => 'Otro',
                    ])
                    ->required(),
            ]),

            Forms\Components\Textarea::make('observaciones')
                ->label('Observaciones')
                ->rows(2)
                ->columnSpanFull(),
        ];
    })
    ->action(function (array $data) {
        $compra = $this->record;
        $user = Auth::user();

        // ✅ saldo correcto
        $saldoPendiente = $compra->total;

        if ($data['monto'] > $compra->saldo) {
            Notification::make()
                ->title('El monto supera el saldo pendiente.')
                ->danger()
                ->send();
            return;
        }

        Pago::create([
            'empresa_id'   => $user->empresa_id ?? $user->id,
            'compra_id'    => $compra->id,
            'user_id'      => $user->id,
            'fecha'        => $data['fecha'],
            'monto'        => $data['monto'],
            'metodo_pago'  => $data['metodo_pago'],
            'referencia'   => $data['referencia'] ?? null,
            'observaciones'=> $data['observaciones'] ?? null,
        ]);

        // ✅ recalcular correctamente
        $compra->refresh();
        $compra->recalcularTotalesConDevolucion();
        $compra->refresh();

        // ✅ Estado automático
                    if ($compra->saldo <= 0) {
                        $compra->update(['estado' => 'pagada']);
                    }

                    $this->dispatch('$refresh');

        Notification::make()
            ->title('Pago registrado correctamente.')
            ->success()
            ->send();

       $this->dispatch('close-modal'); // Cierra el modal correctamente
$this->redirect(CompraResource::getUrl('view', ['record' => $compra->id])); // Refresca la vista real

    })
    ->modalHeading('Registrar nuevo pago')
    ->modalButton('Guardar pago'),

    // ↩️ Registrar devolución
Actions\Action::make('registrarDevolucion')
    ->label('↩️ Registrar devolución')
    ->color('danger')
    ->icon('heroicon-o-arrow-uturn-left')
    ->visible(fn() => !$this->record->devuelta_total && $this->record->estado !== 'anulada')
    ->form(function () {
    $compra = $this->record;
    $detalles = $compra->detalles()->with(['producto', 'variante'])->get();

    return [

        // ✅ Primero selecciona el tipo (sin seleccionar nada inicialmente)
        \Filament\Forms\Components\Select::make('tipo')
            ->label('Tipo de devolución')
            ->placeholder('Seleccione el tipo de devolución')
            ->options([
                'total' => 'Total (devolver todo)',
                'parcial' => 'Parcial (seleccionar items)',
            ])
            ->required()
            ->reactive()
            ->default(null), // ← IMPORTANTE

        \Filament\Forms\Components\Textarea::make('motivo')
            ->label('Motivo de la devolución')
            ->required()
            ->rows(2)
            ->columnSpanFull(),

        // ✅ Resumen al final y solo se muestra si hay tipo seleccionado
        \Filament\Forms\Components\Placeholder::make('resumen_devolucion')
    ->label('Resumen de la devolución')
    ->visible(fn($get) => $get('tipo') !== null)
    ->content(function (callable $get) use ($compra) {

        $tipo = $get('tipo');

        // ✅ Devolución TOTAL
        if ($tipo === 'total') {

           $items = $compra->detalles->sum(function($d){
    $devueltoAntes = \App\Models\DevolucionCompraDetalle::where('compra_detalle_id', $d->id)->sum('cantidad');
    return max(0, $d->cantidad - $devueltoAntes);
});

$total = $compra->detalles->sum(function ($d) {
    $devueltoAntes = \App\Models\DevolucionCompraDetalle::where('compra_detalle_id', $d->id)->sum('cantidad');
    $cant = max(0, $d->cantidad - $devueltoAntes);
    $costo = (float)$d->costo_unitario;
    $desc = (float)$d->desc_comercial;
    $iva  = (float)$d->iva_pct;
    $cDesc = $costo * (1 - $desc / 100);
    $base = $cant * $cDesc;
    return $base * (1 + $iva / 100);
});

            return new \Illuminate\Support\HtmlString(
                "📦 Ítems: <b>{$items}</b> &nbsp;&nbsp;|&nbsp;&nbsp; 💰 Total devolución estimada: <b>$" . number_format($total, 0, ',', '.') . "</b>"
            );
        }

        // ✅ Devolución PARCIAL
        $detalles = $get('detalles') ?? [];

        $items = collect($detalles)->sum(fn($i) => (float)($i['cantidad'] ?? 0));

        $total = collect($detalles)->sum(function ($i) {
            $cant = (float)($i['cantidad'] ?? 0);
            $costo = (float)($i['costo_unitario'] ?? 0);
            $desc = (float)($i['desc_comercial'] ?? 0);
            $iva  = (float)($i['iva_pct'] ?? 0);

            $cDesc = $costo * (1 - $desc / 100);
            $base = $cant * $cDesc;
            return $base * (1 + $iva / 100);
        });

        return new \Illuminate\Support\HtmlString(
            "📦 Ítems: <b>{$items}</b> &nbsp;&nbsp;|&nbsp;&nbsp; 💰 Total devolución estimada: <b>$" . number_format($total, 0, ',', '.') . "</b>"
        );
    })
    ->columnSpanFull()
    ->reactive(),

        // ✅ Repeater solo cuando se seleccione PARCIAL
        \Filament\Forms\Components\Repeater::make('detalles')
            ->label('Productos a devolver')
            ->visible(fn($get) => $get('tipo') === 'parcial')
            ->disableItemCreation()
            ->disableItemDeletion()
            ->reactive()
            ->default(function () use ($detalles) {

                return $detalles->map(function ($d) {

                    $devueltoAntes = \App\Models\DevolucionCompraDetalle::where('compra_detalle_id', $d->id)->sum('cantidad');
                    $restante = max(0, $d->cantidad - $devueltoAntes);

                    $nombreBase = $d->nombre_producto ?? $d->producto->descripcion_larga;

                    return [
                        'compra_detalle_id' => $d->id,
                        'product_id' => $d->product_id,
                        'producto_variante_id' => $d->producto_variante_id,
                        'codigo_ingresado' => $d->codigo_ingresado,
                        'nombre_producto' => $d->variante ? $nombreBase . ' — ' . $d->variante->nombre : $nombreBase,
                        'cantidad_original' => $restante,
                        'cantidad' => 0,
                        'costo_unitario' => $d->costo_unitario,
                        'desc_comercial' => $d->desc_comercial,
                        'iva_pct' => $d->iva_pct,
                    ];
                })->filter(fn($row) => $row['cantidad_original'] > 0);
            })
             ->schema([
        \Filament\Forms\Components\TextInput::make('codigo_ingresado')
            ->label('Código')
            ->disabled()
            ->columnSpan(2),

        \Filament\Forms\Components\TextInput::make('nombre_producto')
            ->label('Producto')
            ->disabled()
            ->columnSpan(9), // 👈 más ancho para que se vea completo el nombre

        \Filament\Forms\Components\TextInput::make('cantidad_original')
            ->label('Cant. Dispo.')
            ->disabled()
            ->columnSpan(2),

        \Filament\Forms\Components\TextInput::make('cantidad')
            ->label('Cant. Devol.')
            ->numeric()
            ->minValue(0)
            ->required()
            ->columnSpan(2),
    ])
    ->columns(15),
    ];
})
    ->action(function (array $data) {
        $compra = $this->record;
        $user = auth()->user();

        if (($data['tipo'] === 'parcial') && collect($data['detalles'])->sum('cantidad') <= 0) {
            \Filament\Notifications\Notification::make()
                ->title('No se seleccionaron cantidades válidas')
                ->danger()
                ->send();
            return;
        }

        $devolucion = \App\Models\DevolucionCompra::create([
            'compra_id' => $compra->id,
            'empresa_id' => $compra->empresa_id,
            'user_id' => $user->id,
            'tipo' => $data['tipo'],
            'motivo' => $data['motivo'],
        ]);

        $totalDevolucion = 0;

        // 🧮 FUNCIÓN para calcular totales con descuento comercial y IVA
        $calcularTotales = function ($detalle, $cantidad) {
            $costoBase = $detalle->costo_unitario ?? 0;
            $descCom = $detalle->desc_comercial ?? 0;
            $iva = $detalle->iva_pct ?? 0;

            // Aplicar descuento comercial directamente sobre el costo unitario
            $costoConDesc = $costoBase - ($costoBase * ($descCom / 100));

            $base = $cantidad * $costoConDesc;
            $valorImpuesto = $base * ($iva / 100);
            $subtotalConIva = $base + $valorImpuesto;

            return [
                'costo_unitario' => $costoConDesc,
                'base' => $base,
                'valorImpuesto' => $valorImpuesto,
                'subtotalConIva' => $subtotalConIva,
                'iva' => $iva,
            ];
        };

        // 🧾 Devolución total
        if ($data['tipo'] === 'total') {

            
foreach ($compra->detalles as $detalle) {

    // ✅ Cantidad ya devuelta previamente
    $devueltoAntes = \App\Models\DevolucionCompraDetalle::where('compra_detalle_id', $detalle->id)
        ->sum('cantidad');

    // ✅ Cantidad restante disponible para devolver
    $cantidadRestante = max(0, $detalle->cantidad - $devueltoAntes);

    // ❌ Si no queda nada para devolver, pasar al siguiente
    if ($cantidadRestante <= 0) {
        continue;
    }

    $totales = $calcularTotales($detalle, $cantidadRestante);

    \App\Models\DevolucionCompraDetalle::create([
        'devolucion_compra_id' => $devolucion->id,
        'compra_detalle_id' => $detalle->id,
        'product_id' => (string) $detalle->product_id,
        'producto_variante_id' => $detalle->producto_variante_id,
        'cantidad' => $cantidadRestante,
        'costo_unitario' => $totales['costo_unitario'],
        'porcentaje_impuesto' => $totales['iva'],
        'valor_impuesto' => $totales['valorImpuesto'],
        'subtotal_sin_impuesto' => $totales['base'],
        'subtotal_con_impuesto' => $totales['subtotalConIva'],
    ]);

    $totalDevolucion += $totales['subtotalConIva'];

    // 🔻 Actualizar existencias
    if ($detalle->product_id) {
        $producto = \App\Models\Product::where('id_producto', $detalle->product_id)
            ->where('empresa_id', $compra->empresa_id)
            ->first();

       if ($producto) {

    $stockAnterior = (float)$producto->existencias;

    // 🔥 KARDEX
    guardarKardex(
        $detalle->product_id,
        'devolucion_compra',
        $cantidadRestante,
        $compra->empresa_id,
        $devolucion->id,
        $stockAnterior
    );

    // 🔻 ACTUALIZAR INVENTARIO
    $producto->existencias = $stockAnterior - $cantidadRestante;
    $producto->save();

    // 🎨 si la linea era de una variante puntual (talla/color), tambien
    // se descuenta el stock de ESA variante, no solo el del producto
    if (! empty($detalle->producto_variante_id)) {
        \App\Models\ProductoVariante::where('id', $detalle->producto_variante_id)
            ->where('empresa_id', $compra->empresa_id)
            ->decrement('stock', $cantidadRestante);
    }
}
    }
}

            $compra->update([
    'estado' => 'anulada',            // ✅ La compra queda anulada
    'devuelta_total' => true,
    'devuelta_at' => now(),
    'motivo_devol' => $data['motivo'],
    'saldo' => 0,                     // ✅ Si era crédito o contado → cancela saldo
    'total' => 0,                     // ✅ El total pasa a 0
    'subtotal' => 0,
    'descuento_total' => 0,
    'impuesto_total' => 0,
]);
        }
        // 🧾 Devolución parcial
        else {
            foreach ($data['detalles'] ?? [] as $item) {
                $cantidad = (float) ($item['cantidad'] ?? 0);
                if ($cantidad <= 0) continue;

                $detalle = \App\Models\CompraDetalle::find($item['compra_detalle_id']);
                if (!$detalle) continue;

                $totales = $calcularTotales($detalle, $cantidad);

                \App\Models\DevolucionCompraDetalle::create([
                    'devolucion_compra_id' => $devolucion->id,
                    'compra_detalle_id' => $detalle->id,
                    'product_id' => (string) $detalle->product_id,
                    'producto_variante_id' => $detalle->producto_variante_id,
                    'cantidad' => $cantidad,
                    'costo_unitario' => $totales['costo_unitario'],
                    'porcentaje_impuesto' => $totales['iva'],
                    'valor_impuesto' => $totales['valorImpuesto'],
                    'subtotal_sin_impuesto' => $totales['base'],
                    'subtotal_con_impuesto' => $totales['subtotalConIva'],
                ]);

                $totalDevolucion += $totales['subtotalConIva'];

                // 🔻 Actualizar existencias
                if ($detalle->product_id) {
                    $producto = \App\Models\Product::where('id_producto', $detalle->product_id)
                        ->where('empresa_id', $compra->empresa_id)
                        ->first();
                    if ($producto) {

    $stockAnterior = (float)$producto->existencias;

    // 🔥 KARDEX
    guardarKardex(
        $detalle->product_id,
        'devolucion_compra',
        $cantidad,
        $compra->empresa_id,
        $devolucion->id,
        $stockAnterior
    );

    // 🔻 ACTUALIZAR INVENTARIO
    $producto->existencias = $stockAnterior - $cantidad;
    $producto->save();

    // 🎨 si la linea era de una variante puntual (talla/color), tambien
    // se descuenta el stock de ESA variante, no solo el del producto
    if (! empty($detalle->producto_variante_id)) {
        \App\Models\ProductoVariante::where('id', $detalle->producto_variante_id)
            ->where('empresa_id', $compra->empresa_id)
            ->decrement('stock', $cantidad);
    }
}
                }
            }
        }

        // ✅ Guardar total final de la devolución
        $devolucion->update(['total' => $totalDevolucion]);

        $compra->refresh();
$compra->recalcularTotalesConDevolucion();
$compra->refresh();

// ✅ Forzar que Filament actualice los datos en pantalla
$this->dispatch('$refresh');

        // ✅ Actualizar totales de la compra
        try {
            $compra->refresh();
            if (method_exists($compra, 'recalcularTotalesConDevolucion')) {
                $compra->recalcularTotalesConDevolucion();
            }
            $compra->refresh();

            
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Error al recalcular totales')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }

        
      $saldoPendiente = $this->record->fresh()->saldo ?? 0;



// 2️⃣ Crear Nota Crédito SOLO si deseas para 'contado'
//    (si quieres también para crédito, quita la condición)
 

    // Generar número correlativo sencillo
    $ultimoId = \App\Models\NotaCredito::where('empresa_id', $compra->empresa_id)->max('id') ?? 0;
    $numeroNC = 'NC-' . str_pad($ultimoId + 1, 6, '0', STR_PAD_LEFT);

    // Crear Nota Crédito
    $proveedor = $compra->proveedor->nombre ?? null;

$notaCredito = NotaCredito::create([
    'empresa_id'       => $compra->empresa_id,
    'compra_id'        => $compra->id,
    'user_id'          => $user->id,
    'numero'           => $numeroNC,
    'total'            => $totalDevolucion,
    'motivo'           => $data['motivo'],
    'empresa_deudora'  => $proveedor,
]);

    // Amarrar la NC a la devolución
    $devolucion->update([
        'nota_credito_id' => $notaCredito->id,
    ]);

    // 3️⃣ Crear los items de la Nota Crédito a partir de devolucion_compra_detalles
    $itemsDevolucion = \App\Models\DevolucionCompraDetalle::where('devolucion_compra_id', $devolucion->id)->get();

    foreach ($itemsDevolucion as $itemDev) {
        \App\Models\NotaCreditoItem::create([
            'nota_credito_id'        => $notaCredito->id,
            'devolucion_detalle_id'  => $itemDev->id,
            'product_id'             => $itemDev->product_id,
            'cantidad'               => $itemDev->cantidad,
            'costo_unitario'         => $itemDev->costo_unitario,
            'valor_impuesto'         => $itemDev->valor_impuesto,
            'subtotal_sin_impuesto'  => $itemDev->subtotal_sin_impuesto,
            'subtotal_con_impuesto'  => $itemDev->subtotal_con_impuesto,
        ]);
    }


// ✅ Notificación principal
Notification::make()
    ->title('Devolución registrada correctamente.')
    ->body('Total devuelto: $' . number_format($totalDevolucion, 0, ',', '.'))
    ->success()
    ->send();
    })
    ->modalHeading('Registrar devolución de compra')
    ->modalButton('Guardar devolución'),

    ];
}



public static function getRelations(): array
{
    return [
        RelationManagers\PagosRelationManager::class,
    ];
}
    
}
