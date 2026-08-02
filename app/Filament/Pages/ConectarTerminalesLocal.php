<?php

namespace App\Filament\Pages;

use App\Models\ActivacionLocal;
use App\Support\PosEdition;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection;

/**
 * Muestra la direccion de este servidor en la red local para que otros
 * equipos (terminales/cajas) se puedan conectar, y lista los terminales
 * ya emparejados -- ver App\Http\Controllers\EmparejarTerminalLocalController
 * (donde se conecta cada terminal) y App\Filament\Pages\EmitirLicenciaLocal
 * (donde el super_admin emite el codigo de cada terminal nuevo).
 */
class ConectarTerminalesLocal extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-computer-desktop';
    protected static ?string $navigationLabel = 'Conectar Terminales';
    protected static ?string $navigationGroup = 'Administración';
    protected static string $view = 'filament.pages.conectar-terminales-local';

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canAccess(): bool
    {
        return PosEdition::esLocal();
    }

    public function urlParaTerminales(): ?string
    {
        $direccion = config('pos.lan_address');

        if (! $direccion) {
            return null;
        }

        return "http://{$direccion}/emparejar-terminal";
    }

    public function terminales(): Collection
    {
        return ActivacionLocal::where('rol', 'cliente')->orderByDesc('activada_at')->get();
    }

    public function revocarTerminal(int $id): void
    {
        $fila = ActivacionLocal::where('rol', 'cliente')->where('id', $id)->first();

        if (! $fila) {
            return;
        }

        $fila->delete();

        Notification::make()
            ->title('Terminal desconectado')
            ->body('Si vuelve a conectarse, va a pedir el código de activación de nuevo.')
            ->success()
            ->send();
    }
}
