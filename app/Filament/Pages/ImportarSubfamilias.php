<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\SubfamiliaImport;
use Filament\Notifications\Notification;

class ImportarSubfamilias extends Page
{
    use WithFileUploads;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';
    protected static ?string $navigationGroup = 'Configuraciones';
    protected static string $view = 'filament.pages.importar-subfamilias';

    public $archivo;

    public function importar()
    {
        $this->validate([
            'archivo' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        try {
            Excel::import(new SubfamiliaImport, $this->archivo);

            Notification::make()
                ->title('Subfamilias importadas con éxito')
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
