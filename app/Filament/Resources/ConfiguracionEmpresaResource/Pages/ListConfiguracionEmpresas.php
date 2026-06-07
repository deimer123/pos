<?php

namespace App\Filament\Resources\ConfiguracionEmpresaResource\Pages;

use App\Filament\Resources\ConfiguracionEmpresaResource;
use App\Models\ConfiguracionEmpresa;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListConfiguracionEmpresas extends ListRecords
{
    protected static string $resource = ConfiguracionEmpresaResource::class;

    public function mount(): void
    {
        $user = auth()->user();

        if ($user && $user->hasRole('super_admin')) {
            parent::mount();
            return;
        }

        $configuracion = ConfiguracionEmpresa::where('empresa_id', $user->id)->first();

        if ($configuracion) {
            $this->redirect($this->getResource()::getUrl('edit', ['record' => $configuracion->id]));
            return;
        }

        $this->redirect($this->getResource()::getUrl('create'));
    }

    protected function getHeaderActions(): array
    {
        if (auth()->user()?->hasRole('super_admin')) {
            return [
                Actions\CreateAction::make(),
            ];
        }

        return [];
    }
}
