<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\DepartamentosImport;
use Filament\Notifications\Notification;

class ImportarDepartamentos extends Page
{

    public static function canAccess(): bool
{
    return auth()->user()->hasRole('super_admin');
}

    use WithFileUploads;

    public $archivo;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';
    protected static ?string $navigationGroup = 'Configuraciones';
    protected static string $view = 'filament.pages.importar-departamentos';
    protected static ?string $title = 'Importar Departamentos';

    public function importar()
    {
        $this->validate([
            'archivo' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        try {
            Excel::import(new DepartamentosImport, $this->archivo);

            Notification::make()
                ->title('Departamentos importados')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Error')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
