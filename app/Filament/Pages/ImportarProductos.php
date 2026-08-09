<?php

namespace App\Filament\Pages;

use App\Exports\ProductBulkTemplateExport;
use App\Imports\ProductBulkImport;
use App\Imports\ProductoLoteBulkImport;
use App\Imports\ProductoRopaBulkImport;
use App\Models\ConfiguracionEmpresa;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;

class ImportarProductos extends Page
{
    use WithFileUploads;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';
    protected static string $view = 'filament.pages.importar-productos';

    public $archivo;
    public array $resultado = [];

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function canAccess(): bool
    {
        return auth()->check()
            && auth()->user()->hasRole('admin_empresa')
            && auth()->user()->puedeVerResource('productos');
    }

    public function getTitle(): string
    {
        return 'Carga masiva de productos';
    }

    private function empresaVendePorVariantes(int $empresaId): bool
    {
        return ConfiguracionEmpresa::where('empresa_id', $empresaId)->value('tipo_negocio') === 'ropa_calzado';
    }

    // Un producto no maneja variantes Y lotes a la vez en la practica, pero
    // por si acaso, variantes gana prioridad si ambas cosas estuvieran
    // activas (mismo orden que ya se usa en CompraResource).
    private function empresaUsaLotes(int $empresaId): bool
    {
        return (bool) ConfiguracionEmpresa::where('empresa_id', $empresaId)->value('usa_lotes');
    }

    public function descargarPlantilla()
    {
        $empresaId = auth()->user()->getEmpresaActualId();
        $conVariantes = $this->empresaVendePorVariantes($empresaId);
        $conLotes = ! $conVariantes && $this->empresaUsaLotes($empresaId);

        return Excel::download(new ProductBulkTemplateExport($empresaId, $conVariantes, $conLotes), 'plantilla-productos.xlsx');
    }

    public function importar()
    {
        $this->validate([
            'archivo' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        @set_time_limit(0);
        @ini_set('max_execution_time', '0');

        $empresaId = auth()->user()->getEmpresaActualId();
        $conVariantes = $this->empresaVendePorVariantes($empresaId);
        $conLotes = ! $conVariantes && $this->empresaUsaLotes($empresaId);

        $import = match (true) {
            $conVariantes => new ProductoRopaBulkImport($empresaId),
            $conLotes => new ProductoLoteBulkImport($empresaId),
            default => new ProductBulkImport($empresaId),
        };

        try {
            Excel::import($import, $this->archivo);
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Error durante la importación')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        $this->resultado = $import->resumen();
        $this->archivo = null;

        $creados = $this->resultado['creados'];
        $errores = count($this->resultado['errores']);

        $notificacion = Notification::make()
            ->title($creados . ' producto' . ($creados === 1 ? '' : 's') . ' importado' . ($creados === 1 ? '' : 's') . ($errores ? ", {$errores} fila(s) con error" : ''));

        $errores > 0 ? $notificacion->warning() : $notificacion->success();

        $notificacion->send();
    }
}
