<?php
// filepath: c:\laragon\www\posapp\app\Filament\Resources\ConfiguracionEmpresaResource\Pages\CreateConfiguracionEmpresa.php

namespace App\Filament\Resources\ConfiguracionEmpresaResource\Pages;

use App\Filament\Resources\ConfiguracionEmpresaResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

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

        // Guardia dura: sin importar la causa (navegador, algun paso del
        // wizard que se salte la validacion, etc.), NUNCA se debe crear
        // una configuracion sin tipo_negocio elegido de verdad -- si se
        // permitiera, el fallback de abajo (`?? 'tienda'`) lo dejaria
        // guardado en silencio, y como despues queda bloqueado para
        // siempre, la empresa terminaria con un tipo que nadie eligio.
        if (blank($data['tipo_negocio'] ?? null)) {
            throw ValidationException::withMessages([
                'data.tipo_negocio' => 'Debes seleccionar un tipo de negocio antes de continuar.',
            ]);
        }

        // Solo asignar empresa_id
        $data['empresa_id'] = $user->id;

        $data = array_merge($data, \App\Models\ConfiguracionEmpresa::flagsModuloParaTipo($data['tipo_negocio']));

        if (empty($data['slug']) && ! empty($data['nombre_empresa'])) {
            $data['slug'] = \Illuminate\Support\Str::slug($data['nombre_empresa']) . '-' . $user->id;
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Configuración de empresa creada exitosamente';
    }

    public static function canCreateAnother(): bool
    {
        return false;
    }

}