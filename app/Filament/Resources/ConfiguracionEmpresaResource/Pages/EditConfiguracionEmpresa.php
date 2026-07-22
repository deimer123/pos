<?php

namespace App\Filament\Resources\ConfiguracionEmpresaResource\Pages;

use App\Filament\Resources\ConfiguracionEmpresaResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

class EditConfiguracionEmpresa extends EditRecord
{
    protected static string $resource = ConfiguracionEmpresaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('emparejarTurion')
                ->label('Emparejar Turión')
                ->icon('heroicon-o-computer-desktop')
                ->color('primary')
                ->url(fn () => \App\Filament\Pages\EmparejarTerminal::getUrl()),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // El campo ya viene deshabilitado en el formulario si el tipo de
        // negocio ya estaba definido, pero se refuerza aqui tambien: una
        // vez configurado no se puede cambiar por ningun medio.
        if (filled($this->record->tipo_negocio)) {
            $data['tipo_negocio'] = $this->record->tipo_negocio;
        }

        $data = array_merge($data, \App\Models\ConfiguracionEmpresa::flagsModuloParaTipo($data['tipo_negocio'] ?? 'tienda'));

        if (empty($data['slug']) && ! empty($data['nombre_empresa'])) {
            $data['slug'] = \Illuminate\Support\Str::slug($data['nombre_empresa']) . '-' . $this->record->empresa_id;
        }

        return $data;
    }
}
