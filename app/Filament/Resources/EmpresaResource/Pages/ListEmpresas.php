<?php

namespace App\Filament\Resources\EmpresaResource\Pages;

use App\Filament\Resources\EmpresaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEmpresas extends ListRecords
{
    protected static string $resource = EmpresaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // El "emisor" de las facturas de plan es la propia cuenta del
            // super_admin (con la que inicia sesion), no una empresa aparte
            // que hay que crear y marcar -- este boton lo lleva a configurar
            // sus propios datos (NIT, representante legal, Factus si va a
            // facturar electronicamente). Ver App\Services\Ventas\
            // FacturarPlanService.
            Actions\Action::make('configurarFacturacion')
                ->label('Configurar datos de facturación')
                ->icon('heroicon-o-cog-6-tooth')
                ->color('gray')
                ->url(fn () => \App\Filament\Resources\ConfiguracionEmpresaResource::getUrl('create')),

            Actions\CreateAction::make()
                ->label('Crear Empresa'),
        ];
    }
}
