<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\MisSucursales;
use App\Services\Grupo\CambioDeEmpresa;
use Filament\Widgets\Widget;

// Acceso rapido a las sucursales desde el escritorio. Solo se muestra a la
// empresa dueña que tiene sucursales ligadas (mismas reglas que la pantalla
// "Mis sucursales"); para el resto de empresas no existe.
class MisSucursalesWidget extends Widget
{
    protected static string $view = 'filament.widgets.mis-sucursales';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = -10;

    public static function canView(): bool
    {
        return MisSucursales::canAccess();
    }

    protected function getViewData(): array
    {
        return ['sucursales' => app(CambioDeEmpresa::class)->filasParaDueno(auth()->user())];
    }
}
