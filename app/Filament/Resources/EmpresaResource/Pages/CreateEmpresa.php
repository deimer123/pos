<?php
namespace App\Filament\Resources\EmpresaResource\Pages;

use App\Filament\Resources\EmpresaResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreateEmpresa extends CreateRecord
{
    protected static string $resource = EmpresaResource::class;

    // complementos_ids no es una columna real de "users" (es una relacion
    // muchos-a-muchos con precio_aplicado en la tabla pivote), asi que se
    // extrae en mutateFormDataBeforeCreate() y se guarda aqui para poder
    // sincronizar el pivote en afterCreate(), ya con el registro creado.
    protected array $complementosSeleccionados = [];

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        unset($data['factus']);

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

        $this->complementosSeleccionados = $data['complementos_ids'] ?? [];
        unset($data['complementos_ids']);

        $data['valor_plan_total'] = EmpresaResource::calcularTotalPlan(
            $data['plan_id'] ?? null,
            $this->complementosSeleccionados,
            $data['paquete_usuarios_id'] ?? null,
        );

        return $data;
    }

    protected function afterCreate(): void
    {
        $record = $this->record;

        // Asignar rol de admin_empresa
        $record->assignRole('admin_empresa');
        EmpresaResource::saveFactusConfig($record, $this->form->getRawState()['factus'] ?? []);
        EmpresaResource::sincronizarComplementos($record, $this->complementosSeleccionados);

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
