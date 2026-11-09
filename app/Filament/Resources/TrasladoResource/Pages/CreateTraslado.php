<?php

namespace App\Filament\Resources\TrasladoResource\Pages;

use App\Filament\Resources\TrasladoResource;
use App\Models\User;
use App\Services\Grupo\TrasladoService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateTraslado extends CreateRecord
{
    protected static string $resource = TrasladoResource::class;

    protected static bool $canCreateAnother = false;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    // El traslado se crea ENVIANDOLO: el servicio descuenta el stock del
    // origen y registra el kardex en la misma transaccion.
    protected function handleRecordCreation(array $data): Model
    {
        try {
            $destino = User::findOrFail($data['destino_id']);

            return app(TrasladoService::class)->enviar(
                auth()->user(),
                $destino,
                $data['items'] ?? [],
                $data['observacion'] ?? null,
            );
        } catch (\Throwable $e) {
            Notification::make()
                ->title('No se pudo enviar el traslado')
                ->body($e->getMessage())
                ->danger()
                ->send();

            $this->halt();
        }
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Traslado enviado. La otra sucursal debe confirmarlo para que entre a su inventario.';
    }
}
