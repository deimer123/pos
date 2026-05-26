<?php

namespace App\Filament\Resources\EmpresaResource\Pages;

use App\Filament\Resources\EmpresaResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEmpresa extends EditRecord
{
    protected static string $resource = EmpresaResource::class;

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

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (! empty($data['plan_meses']) && ! empty($data['plan_started_at']) && empty($data['plan_ends_at'])) {
            $data['plan_ends_at'] = EmpresaResource::calculatePlanEndDate(
                $data['plan_started_at'],
                (int) $data['plan_meses'],
            );
        }

        return $data;
    }
}
