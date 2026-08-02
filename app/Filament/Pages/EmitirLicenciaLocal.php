<?php

namespace App\Filament\Pages;

use App\Exceptions\LocalLicense\LicenciaInvalidaException;
use App\Models\LicenciaLocal;
use App\Models\User;
use App\Services\LocalLicense\CodigoActivacion;
use App\Services\LocalLicense\FirmadorLicencia;
use DateTimeImmutable;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * Emite (firma) codigos de activacion offline para la edicion Local -- ver
 * app/Services/LocalLicense. A diferencia de EmparejarTerminal (self-
 * service para admin_empresa), esto es solo para super_admin: emitir una
 * licencia Local es una decision comercial/soporte, no algo que el
 * cliente final autogestione.
 */
class EmitirLicenciaLocal extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-key';
    protected static ?string $navigationLabel = 'Emitir licencia Local';
    protected static ?string $navigationGroup = 'Administración';
    protected static string $view = 'filament.pages.emitir-licencia-local';

    public ?array $data = [];
    public ?string $codigoGenerado = null;

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasRole('super_admin');
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('empresa_id')
                    ->label('Empresa')
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search) => User::where('tipo_usuario', 'empresa')
                        ->where('name', 'like', "%{$search}%")
                        ->limit(20)
                        ->pluck('name', 'id'))
                    ->getOptionLabelUsing(fn ($value) => User::find($value)?->name)
                    ->required(),

                TextInput::make('machine_id')
                    ->label('Machine ID')
                    ->helperText('El código que muestra la pantalla de activación de la instalación del cliente.')
                    ->required(),

                TextInput::make('machine_label')
                    ->label('Etiqueta de la máquina (opcional)')
                    ->placeholder('Ej: Caja principal - Tienda Norte'),

                Select::make('rol')
                    ->label('Rol de esta licencia')
                    ->options([
                        'servidor' => 'Servidor (arranca la instalación, corre PHP+base de datos)',
                        'cliente' => 'Terminal / cliente (se conecta a un servidor que ya existe)',
                    ])
                    ->default('servidor')
                    ->required()
                    ->helperText('Cada equipo adicional (servidor o terminal) necesita su propia licencia -- ver "Conectar Terminales" en el equipo servidor para emparejar un terminal.'),
            ])
            ->statePath('data');
    }

    public function emitirCodigo(FirmadorLicencia $firmador): void
    {
        $data = $this->form->getState();

        $empresa = User::where('tipo_usuario', 'empresa')->find($data['empresa_id'] ?? null);

        if (! $empresa) {
            Notification::make()->title('Empresa no encontrada')->danger()->send();

            return;
        }

        $machineId = trim((string) $data['machine_id']);

        if (LicenciaLocal::where('empresa_id', $empresa->id)->where('machine_id', $machineId)->exists()) {
            Notification::make()
                ->title('Ya existe una licencia emitida para esta empresa y esta máquina')
                ->body('Podés copiarla de nuevo desde el historial de abajo en vez de emitir una duplicada.')
                ->warning()
                ->send();

            return;
        }

        $licenciaId = (string) Str::uuid();

        $rol = $data['rol'] ?? 'servidor';

        $codigo = new CodigoActivacion(
            licenciaId: $licenciaId,
            empresaId: $empresa->id,
            empresaNombre: $empresa->name,
            machineId: $machineId,
            issuedAt: new DateTimeImmutable(),
            rol: $rol,
        );

        try {
            $codigoFirmado = $firmador->firmar($codigo);
        } catch (LicenciaInvalidaException $e) {
            Notification::make()->title('No se pudo emitir el código')->body($e->getMessage())->danger()->send();

            return;
        }

        LicenciaLocal::create([
            'licencia_id' => $licenciaId,
            'empresa_id' => $empresa->id,
            'machine_id' => $machineId,
            'machine_label' => $data['machine_label'] ?? null,
            'rol' => $rol,
            'creado_por' => auth()->id(),
            'codigo_emitido' => $codigoFirmado,
            'issued_at' => $codigo->issuedAt,
        ]);

        $this->codigoGenerado = $codigoFirmado;

        Notification::make()->title('Código de activación generado')->success()->send();
    }

    public function licenciasEmitidas(): Collection
    {
        return LicenciaLocal::with(['empresa', 'creador'])
            ->orderByDesc('issued_at')
            ->limit(20)
            ->get();
    }
}
