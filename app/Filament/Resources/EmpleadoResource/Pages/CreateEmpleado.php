<?php

namespace App\Filament\Resources\EmpleadoResource\Pages;

use App\Filament\Resources\EmpleadoResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;

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
        $data['empresa_id'] = auth()->user()->getEmpresaActualId();
        $this->validateRoleLimits($this->data['roles'] ?? [], $data['empresa_id']);
        
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

    public static function canCreateAnother(): bool
    {
        return false;
    }

    protected function validateRoleLimits(array $roles, int $empresaId): void
    {
        $empresa = auth()->user()->empresaPrincipal();
        $labels = [
            'vendedor' => 'vendedores',
            'cajero' => 'cajeros',
            'digitador' => 'digitadores',
        ];
        $limits = [
            'vendedor' => (int) $empresa->max_vendedores,
            'cajero' => (int) $empresa->max_cajeros,
            'digitador' => (int) $empresa->max_digitadores,
        ];

        foreach ($limits as $role => $limit) {
            if (! in_array($role, $roles, true)) {
                continue;
            }

            $actuales = \App\Models\User::query()
                ->where('empresa_id', $empresaId)
                ->role($role)
                ->count();

            if ($actuales >= $limit) {
                throw ValidationException::withMessages([
                    'data.roles' => "La empresa ya alcanzo el limite de {$labels[$role]} permitido por el super admin.",
                ]);
            }
        }
    }

}
