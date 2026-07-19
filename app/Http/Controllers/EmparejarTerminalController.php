<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

/**
 * Pantalla local (sin login) donde el cajero ingresa el codigo generado
 * en el panel del negocio para emparejar esta terminal de Turion la
 * primera vez. Reutiliza "php artisan pos:pair" (App\Console\Commands\PosPair).
 */
class EmparejarTerminalController extends Controller
{
    public function mostrar()
    {
        return view('emparejar-terminal');
    }

    public function emparejar(Request $request)
    {
        $data = $request->validate([
            'codigo' => 'required|string|max:20',
            'servidor' => 'required|url',
        ]);

        $salida = Artisan::call('pos:pair', [
            'codigo' => $data['codigo'],
            'servidor' => $data['servidor'],
        ]);

        if ($salida !== 0) {
            return back()
                ->withInput()
                ->withErrors(['codigo' => 'No se pudo emparejar: '.trim(Artisan::output())]);
        }

        return redirect('/pos')->with('status', 'Terminal emparejada correctamente.');
    }
}
