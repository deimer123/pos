<?php
// filepath: c:\laragon\www\posapp\app\Filament\Resources\ConfiguracionEmpresaResource\Pages\CreateConfiguracionEmpresa.php

namespace App\Filament\Resources\ConfiguracionEmpresaResource\Pages;

use App\Filament\Resources\ConfiguracionEmpresaResource;
use Filament\Resources\Pages\CreateRecord;

class CreateConfiguracionEmpresa extends CreateRecord
{
    protected static string $resource = ConfiguracionEmpresaResource::class;

    public function mount(): void
    {
        $user = auth()->user();
        
        // ✅ Verificar ANTES de cargar la página
        $configuracionExistente = \App\Models\ConfiguracionEmpresa::where('empresa_id', $user->id)->first();
        
        if ($configuracionExistente) {
            // ✅ Usar $this->redirect() en lugar de redirect()
            $this->redirect($this->getResource()::getUrl('edit', ['record' => $configuracionExistente->id]));
            return;
        }
        
        // Si no existe, continuar con el mount normal
        parent::mount();
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();
        
        // Solo asignar empresa_id
        $data['empresa_id'] = $user->id;
        
        return $data;
    }

    protected function getRedirectUrl(): string
    {
        // Verificar que el record existe
        if ($this->record && $this->record->id) {
            return $this->getResource()::getUrl('edit', ['record' => $this->record->id]);
        }
        
        // Fallback: buscar la configuración recién creada
        $user = auth()->user();
        $configuracion = \App\Models\ConfiguracionEmpresa::where('empresa_id', $user->id)->first();
        
        if ($configuracion) {
            return $this->getResource()::getUrl('edit', ['record' => $configuracion->id]);
        }
        
        // Último fallback
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Configuración de empresa creada exitosamente';
    }
}