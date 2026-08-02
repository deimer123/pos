<?php

namespace App\Filament\Pages;

use App\Models\ConfiguracionEmpresa;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Datos de facturacion del super_admin como "empresa emisora" de las
 * facturas de plan/servicios que le cobra a sus clientes (droplet/Local).
 * A diferencia de ConfiguracionEmpresaResource, esto NO es el wizard de
 * onboarding de un negocio real: el super_admin no vende productos ni
 * opera un tipo de negocio, solo necesita identificarse (nombre, NIT,
 * representante legal) para que FacturarPlanService pueda emitir la
 * factura -- por eso no pide tipo_negocio ni nada relacionado a ventas.
 */
class ConfigurarFacturacionEmisor extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string $view = 'filament.pages.configurar-facturacion-emisor';

    public ?array $data = [];

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasRole('super_admin');
    }

    public function mount(): void
    {
        $config = ConfiguracionEmpresa::where('empresa_id', auth()->id())->first();

        $this->form->fill($config?->only([
            'nombre_empresa', 'representante_legal', 'nit', 'telefono', 'direccion', 'logo',
        ]) ?? []);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make(2)
                    ->schema([
                        TextInput::make('nombre_empresa')
                            ->label('Nombre de la Empresa')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('representante_legal')
                            ->label('Representante Legal')
                            ->required()
                            ->maxLength(255),
                    ]),

                Grid::make(2)
                    ->schema([
                        TextInput::make('nit')
                            ->label('NIT')
                            ->required()
                            ->maxLength(20),

                        TextInput::make('telefono')
                            ->label('Teléfono')
                            ->tel()
                            ->maxLength(20),
                    ]),

                Grid::make(2)
                    ->schema([
                        Textarea::make('direccion')
                            ->label('Dirección')
                            ->maxLength(500),

                        FileUpload::make('logo')
                            ->label('Logo')
                            ->image()
                            ->directory('logos')
                            ->disk('public')
                            ->visibility('public')
                            ->preserveFilenames()
                            ->maxSize(2048),
                    ]),
            ])
            ->statePath('data');
    }

    public function guardar(): void
    {
        $data = $this->form->getState();

        ConfiguracionEmpresa::updateOrCreate(
            ['empresa_id' => auth()->id()],
            $data,
        );

        Notification::make()->title('Datos de facturación guardados')->success()->send();
    }
}
