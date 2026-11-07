<?php

namespace App\Filament\Resources\SucursalResource\Pages;

use App\Filament\Resources\SucursalResource;
use App\Models\ConfiguracionEmpresa;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateSucursal extends CreateRecord
{
    protected static string $resource = SucursalResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $empresaId = auth()->user()->getEmpresaActualId();

        // Mientras la pantalla de mesas no filtre por sucursal, una segunda
        // sucursal mezclaria las mesas y pedidos de cocina de todas.
        $config = ConfiguracionEmpresa::where('empresa_id', $empresaId)->first();

        if ($config && ($config->usa_mesas || $config->usa_cocina)) {
            Notification::make()
                ->title('Todavía no se puede crear otra sucursal')
                ->body('Las empresas con mesas o cocina podrán tener varias sucursales en una próxima actualización.')
                ->danger()
                ->send();

            $this->halt();
        }

        $data['empresa_id'] = $empresaId;
        $data['es_principal'] = false;

        return $data;
    }
}
