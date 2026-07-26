<?php

namespace App\Filament\Resources\ConfiguracionEmpresaResource\Pages;

use App\Filament\Resources\ConfiguracionEmpresaResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditConfiguracionEmpresa extends EditRecord
{
    protected static string $resource = ConfiguracionEmpresaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('emparejarTurion')
                ->label('Emparejar equipo offline')
                ->icon('heroicon-o-computer-desktop')
                ->color('primary')
                ->url(fn () => \App\Filament\Pages\EmparejarTerminal::getUrl()),
            Action::make('descargarTurion')
                ->label('Descargar Sistema POS Offline')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->url(fn () => route('turion.descargar')),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // El campo ya viene deshabilitado en el formulario si el tipo de
        // negocio ya estaba definido, pero se refuerza aqui tambien: una
        // vez configurado no se puede cambiar por ningun medio.
        if (filled($this->record->tipo_negocio)) {
            $data['tipo_negocio'] = $this->record->tipo_negocio;
        } elseif (blank($data['tipo_negocio'] ?? null)) {
            // Guardia dura: un registro que quedo sin tipo_negocio (por el
            // bug ya corregido en CreateConfiguracionEmpresa) se puede
            // corregir desde aqui, pero no se puede volver a guardar en
            // blanco.
            throw ValidationException::withMessages([
                'data.tipo_negocio' => 'Debes seleccionar un tipo de negocio antes de continuar.',
            ]);
        }

        $data = array_merge($data, \App\Models\ConfiguracionEmpresa::flagsModuloParaTipo($data['tipo_negocio']));

        if (empty($data['slug']) && ! empty($data['nombre_empresa'])) {
            $data['slug'] = \Illuminate\Support\Str::slug($data['nombre_empresa']) . '-' . $this->record->empresa_id;
        }

        return $data;
    }
}
