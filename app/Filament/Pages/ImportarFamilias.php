<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\FamiliaImport;
use Filament\Notifications\Notification;

class ImportarFamilias extends Page
{
    use WithFileUploads;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';
    protected static ?string $navigationGroup = 'Configuraciones';
    protected static string $view = 'filament.pages.importar-familias';

    public $archivo;

    public function importar()
    {
        $this->validate([
            'archivo' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        try {
            Excel::import(new FamiliaImport, $this->archivo);

            Notification::make()
                ->title('Familias importadas con éxito')
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
