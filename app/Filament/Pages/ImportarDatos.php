<?php

namespace App\Filament\Pages;

use App\Imports\ActorImport;
use App\Imports\ProductImport;
use App\Imports\AlternateCodesImport;
use App\Imports\FamiliaImport;
use App\Imports\SubfamiliaImport;
use Filament\Pages\Page;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;
use Filament\Notifications\Notification;

class ImportarDatos extends Page
{
    use WithFileUploads;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';
    protected static string $view = 'filament.pages.importar-datos';
    protected static ?string $navigationGroup = 'Configuraciones';

    public $archivo_productos;
    public $archivo_actores;
    public $archivo_codigos_alternos;
    public $archivo_familias;
    public $archivo_subfamilias;
    public $empresa_id;

    public static function canAccess(): bool
    {
        return auth()->user()->hasRole('super_admin');
    }

    public function importar()
    {
        $this->validate([
            'empresa_id'               => 'required|exists:users,id',
            'archivo_familias'        => 'nullable|file|mimes:xlsx,xls,csv',
        'archivo_subfamilias'     => 'nullable|file|mimes:xlsx,xls,csv',
        'archivo_actores'         => 'nullable|file|mimes:xlsx,xls,csv',
        'archivo_productos'       => 'nullable|file|mimes:xlsx,xls,csv',
        'archivo_codigos_alternos'=> 'nullable|file|mimes:xlsx,xls,csv',
        ]);

        try {
        if ($this->archivo_familias) {
            Excel::import(new FamiliaImport($this->empresa_id), $this->archivo_familias);
        }

        if ($this->archivo_subfamilias) {
            Excel::import(new SubfamiliaImport($this->empresa_id), $this->archivo_subfamilias);
        }

        if ($this->archivo_actores) {
            Excel::import(new ActorImport($this->empresa_id), $this->archivo_actores);
        }

        if ($this->archivo_productos) {
            Excel::import(new ProductImport($this->empresa_id), $this->archivo_productos);
        }

        if ($this->archivo_codigos_alternos) {
            Excel::import(new AlternateCodesImport($this->empresa_id), $this->archivo_codigos_alternos);
        }

            Notification::make()
                ->title('Importación completa')
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
