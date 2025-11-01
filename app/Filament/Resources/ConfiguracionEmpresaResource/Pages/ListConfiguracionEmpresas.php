<?php
// filepath: c:\laragon\www\posapp\app\Filament\Resources\ConfiguracionEmpresaResource\Pages\ListConfiguracionEmpresas.php

namespace App\Filament\Resources\ConfiguracionEmpresaResource\Pages;

use App\Filament\Resources\ConfiguracionEmpresaResource;
use Filament\Resources\Pages\ListRecords;
use App\Models\ConfiguracionEmpresa;

class ListConfiguracionEmpresas extends ListRecords
{
    protected static string $resource = ConfiguracionEmpresaResource::class;

    public function mount(): void
    {
        $user = auth()->user();
        
        // Verificar si ya tiene configuración
        $configuracion = ConfiguracionEmpresa::where('empresa_id', $user->id)->first();
        
        if ($configuracion) {
            // ✅ Usar $this->redirect() en lugar de redirect()
            $this->redirect($this->getResource()::getUrl('edit', ['record' => $configuracion->id]));
            return;
        } else {
            // Si no tiene configuración, redirigir al create
            $this->redirect($this->getResource()::getUrl('create'));
            return;
        }
    }

    protected function getHeaderActions(): array
    {
        // No mostrar acciones porque siempre redirige
        return [];
    }
}