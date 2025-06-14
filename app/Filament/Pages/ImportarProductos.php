<?php

namespace App\Filament\Pages;

use App\Imports\ProductImport;
use Filament\Pages\Page;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;
use Filament\Notifications\Notification;

class ImportarProductos extends Page
{


public function descargarPlantilla()
{
    return response()->download(storage_path('app/plantillas/plantilla_productos.xlsx'));
}

    use WithFileUploads;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';
    protected static string $view = 'filament.pages.importar-productos';
    protected static ?string $navigationGroup = 'Configuraciones';

    public $archivo;

    public function importar()
    {
        $this->validate([
            'archivo' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        try {
            Excel::import(new ProductImport, $this->archivo);

            Notification::make()
                ->title('Importación completada')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Error durante la importación')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
