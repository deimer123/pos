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
        $data['activo'] = $data['activo'] ?? true;
        $data['plan_meses'] = (int) ($data['plan_meses'] ?? 3);
        $data['plan_started_at'] = $data['plan_started_at'] ?? today()->toDateString();
        $data['plan_ends_at'] = $data['plan_ends_at']
            ?? EmpresaResource::calculatePlanEndDate($data['plan_started_at'], $data['plan_meses']);
        $data['max_vendedores'] = (int) ($data['max_vendedores'] ?? 1);
        $data['max_cajeros'] = (int) ($data['max_cajeros'] ?? 1);
        $data['max_digitadores'] = (int) ($data['max_digitadores'] ?? 0);
        
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

    public static function canCreateAnother(): bool
    {
        return false;
    }

}
