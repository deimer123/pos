<?php
namespace App\Filament\Resources\EmpresaResource\Pages;

use App\Filament\Resources\EmpresaResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreateEmpresa extends CreateRecord
{
    protected static string $resource = EmpresaResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tipo_usuario'] = 'empresa';
        $data['empresa_id'] = null; // Las empresas no tienen empresa_id
        
        return $data;
    }

    protected function afterCreate(): void
    {
        $record = $this->record;
        
        // Asignar rol de admin_empresa
        $record->assignRole('admin_empresa');
        
        Notification::make()
            ->title('Empresa creada exitosamente')
            ->success()
            ->send();
    }
}
