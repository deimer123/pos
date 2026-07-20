<?php

namespace App\Filament\Pages;

use App\Models\PairingCode;
use Filament\Pages\Page;

class EmparejarTerminal extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-computer-desktop';
    protected static ?string $navigationLabel = 'Emparejar Turión';
    protected static string $view = 'filament.pages.emparejar-terminal';

    public ?PairingCode $codigoActivo = null;

    // Se entra desde el boton "Emparejar Turion" dentro de Configuracion de
    // Empresa (ver EditConfiguracionEmpresa), no como item propio del menu.
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasRole(['admin_empresa', 'super_admin']);
    }

    public function getTitle(): string
    {
        return 'Emparejar una terminal de Turión';
    }

    public function generarCodigo(): void
    {
        $empresaId = auth()->user()->getEmpresaActualId();

        $this->codigoActivo = PairingCode::generarPara($empresaId, auth()->id());
    }
}
