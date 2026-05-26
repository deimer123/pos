<?php

namespace App\Filament\Resources\EmpleadoResource\Pages;

use App\Filament\Resources\EmpleadoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditEmpleado extends EditRecord
{
    protected static string $resource = EmpleadoResource::class;

    protected function getHeaderActions(): array
    {
        return [
           // Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function afterSave(): void
    {
        $record = $this->record;
        $roles = $this->data['roles'] ?? [];
        $this->validateRoleLimits($roles);
        
        // Sincronizar roles usando Spatie
        $record->syncRoles($roles);
    }

    protected function validateRoleLimits(array $roles): void
    {
        $record = $this->record;
        $empresa = $record->empresaPrincipal();
        $limits = [
            'vendedor' => (int) $empresa->max_vendedores,
            'cajero' => (int) $empresa->max_cajeros,
            'digitador' => (int) $empresa->max_digitadores,
        ];
        $labels = [
            'vendedor' => 'vendedores',
            'cajero' => 'cajeros',
            'digitador' => 'digitadores',
        ];

        foreach ($limits as $role => $limit) {
            if (! in_array($role, $roles, true) || $record->hasRole($role)) {
                continue;
            }

            $actuales = \App\Models\User::query()
                ->where('empresa_id', $record->empresa_id)
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
