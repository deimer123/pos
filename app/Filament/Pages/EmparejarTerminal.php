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
