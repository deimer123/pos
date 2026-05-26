<?php

namespace App\Filament\Resources\NotaCreditoResource\Pages;

use App\Filament\Resources\NotaCreditoResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Actions;
use Filament\Notifications\Notification;
use Illuminate\Support\Carbon;

class ViewNotaCredito extends ViewRecord
{
    protected static string $resource = NotaCreditoResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->record;

        $data['factura_numero'] = $record->compra->numero_factura ?? 'SIN FACTURA';
        $data['proveedor_nombre'] = $record->empresa_deudora ?? 'SIN PROVEEDOR';

        $data['created_at'] = $record->created_at
            ? $record->created_at->format('d/m/Y')
            : '';

        $data['fecha_cobro'] = $record->fecha_cobro
            ? date('d/m/Y', strtotime($record->fecha_cobro))
            : 'SIN COBRAR';

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [

            Actions\Action::make('cobrar')
                ->label('Marcar como cobrada')
                ->color('success')
                ->icon('heroicon-o-check-circle')
                ->visible(fn () => $this->record->estado === 'pendiente')
                ->requiresConfirmation()
                ->action(function () {

                    $this->record->update([
                        'estado'      => 'cobrada',
                        'fecha_cobro' => Carbon::now(),
                    ]);

                    Notification::make()
                        ->title('Nota crédito marcada como COBRADA')
                        ->success()
                        ->send();

                    $this->redirect($this->getResource()::getUrl('index'));
                }),

            Actions\EditAction::make()->disabled(),

        ];
    }
}
