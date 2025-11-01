<?php

namespace App\Filament\Resources\EmpleadoResource\Pages;

use App\Filament\Resources\EmpleadoResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreateEmpleado extends CreateRecord
{
    protected static string $resource = EmpleadoResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tipo_usuario'] = 'empleado';
        $data['empresa_id'] = auth()->id(); // ID del admin_empresa logueado
        
        return $data;
    }

    protected function afterCreate(): void
    {
        $record = $this->record;
        $roles = $this->data['roles'] ?? [];
        
        // Asignar roles de vendedor/digitador
        if (!empty($roles)) {
            $record->assignRole($roles);
        }
        
        Notification::make()
            ->title('Empleado creado exitosamente')
            ->success()
            ->send();
    }
}