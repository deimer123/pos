<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\CiudadesImport;
use Filament\Notifications\Notification;

class ImportarCiudades extends Page
{

    public static function canAccess(): bool
{
    return auth()->user()->hasRole('super_admin');
}

    use WithFileUploads;

    public $archivo;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';
    protected static ?string $navigationGroup = 'Configuraciones';
    protected static string $view = 'filament.pages.importar-ciudades';
    protected static ?string $title = 'Importar Ciudades';

    public function importar()
    {
        $this->validate([
            'archivo' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        try {
            Excel::import(new CiudadesImport, $this->archivo);

            Notification::make()
                ->title('Ciudades importadas correctamente')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Error al importar ciudades')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
