<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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

    /**
     * Borra el emparejamiento local (sync_state) y cierra cualquier sesion
     * activa. Se llama desde el boton "Olvidar emparejamiento" del login
     * (no desde dentro de la app), justamente para poder reasignar la
     * terminal a otra empresa sin quedar atrapado con la sesion vieja
     * todavia autenticada -- eso era lo que dejaba "la ruta guardada" de
     * la empresa anterior y no dejaba entrar con el codigo de la nueva.
     */
    public function olvidar(Request $request)
    {
        if (DB::getDriverName() !== 'sqlite') {
            abort(404);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        DB::table('sync_state')->delete();

        return redirect()->route('emparejar')->with('status', 'Emparejamiento olvidado. Puedes ingresar un nuevo código.');
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
