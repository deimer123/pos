<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\ActorImport;
use Filament\Notifications\Notification;

class ImportarActores extends Page
{

    public function descargarPlantilla()
{
    return response()->download(storage_path('app/plantillas/plantilla_actores.xlsx'));
}


    use WithFileUploads;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';
    protected static string $view = 'filament.pages.importar-actores';
    protected static ?string $navigationGroup = 'Configuraciones';

    public $archivo;

    public function importar()
    {
        $this->validate([
            'archivo' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        try {
            Excel::import(new ActorImport, $this->archivo);

            Notification::make()
                ->title('Importación exitosa')
                ->body('Los datos han sido importados correctamente.')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Error al importar')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
