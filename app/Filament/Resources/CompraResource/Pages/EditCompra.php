<?php

namespace App\Filament\Resources\CompraResource\Pages;

use App\Filament\Resources\CompraResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Actions;
use Filament\Notifications\Notification;

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
                ->action(function () {

                    // ✅ Obtener estado seguro
                    $data = (array) $this->form->getRawState();
                    $data['detalles'] = is_array($data['detalles'] ?? null) ? $data['detalles'] : [];

                    // ❌ Bloquear si no hay productos
                    if (count($data['detalles']) === 0) {
                        Notification::make()
                            ->title('No hay productos en la compra')
                            ->body('Agrega al menos un producto para guardar el borrador.')
                            ->danger()
                            ->send();
                        return;
                    }

                    // 🔸 Cálculo de totales
                    $subtotal = $descuentoTotal = $impuestoTotal = $total = 0;

                    foreach ($data['detalles'] as &$detalle) {
                        $cantidad = (float)($detalle['cantidad'] ?? 0);
                        $costo = (float)($detalle['costo_unitario'] ?? 0);
                        $iva = (float)($detalle['iva_pct'] ?? 0);
                        $descP = (float)($detalle['desc_comercial'] ?? 0);

                        $lineaBruta = $cantidad * $costo;
                        $lineaDesc = $lineaBruta * ($descP / 100);
                        $lineaBase = $lineaBruta - $lineaDesc;
                        $lineaImp = $lineaBase * ($iva / 100);
                        $lineaTotal = $lineaBase + $lineaImp;

                        $detalle['subtotal'] = $lineaBruta;
                        $detalle['impuesto'] = $lineaImp;
                        $detalle['total'] = $lineaTotal;

                        $subtotal += $lineaBruta;
                        $descuentoTotal += $lineaDesc;
                        $impuestoTotal += $lineaImp;
                        $total += $lineaTotal;
                    }

                    $data['subtotal'] = $subtotal;
                    $data['descuento_total'] = $descuentoTotal;
                    $data['impuesto_total'] = $impuestoTotal;
                    $data['total'] = $total;
                    $data['saldo'] = ($data['tipo_pago'] ?? '') === 'credito' ? $total : 0;

                    // Guardar en BD
                    $this->record->update($data);

                    // Estado
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
                ->action(function () {

                    // ✅ Usar getRawState en Edit
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

                    // 🧩 1️⃣ Obtener todos los datos actuales del formulario
                    $data = $this->form->getState();

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

                        $detalle['subtotal'] = $lineaBruta;
                        $detalle['impuesto'] = $lineaImpuesto;
                        $detalle['total'] = $lineaTotal;

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
                    // 🔸 FIN CÁLCULO DE TOTALES

                    // 🧩 3️⃣ Actualizar el registro de la compra
                    if ($this->record) {
                        $this->record->update($data);
                    }

                    // 🧩 4️⃣ Cambiar el estado a confirmada
                   $this->record->confirmar();

                    // 🧩 5️⃣ Limpiar cache temporal
                    $this->dispatch('clear-form-cache');

                    // 🧩 6️⃣ Notificación visual
                    \Filament\Notifications\Notification::make()
                        ->title('Compra confirmada correctamente.')
                        ->success()
                        ->send();

                    // 🧩 7️⃣ Redirigir al listado
                    $this->redirect(\App\Filament\Resources\CompraResource::getUrl('index'));
                }),
        ];
    }

    /**
     * 👉 Guarda cambios en la compra existente
     */
    protected function guardarCompra(string $estado)
    {
        $this->record->update(['estado' => $estado]);
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
