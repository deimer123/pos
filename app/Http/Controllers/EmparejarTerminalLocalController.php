<?php

namespace App\Http\Controllers;

use App\Exceptions\LocalLicense\LicenciaInvalidaException;
use App\Models\ActivacionLocal;
use App\Services\LocalLicense\ValidadorLicencia;
use App\Support\MachineId;
use Illuminate\Http\Request;

/**
 * Suma un terminal adicional a un servidor de la edicion Local que YA
 * esta activado (ver ActivarLicenciaController para la activacion del
 * PROPIO servidor). A diferencia de esa pantalla, aca no se crea empresa
 * ni usuario nuevo -- ya existen, este terminal solo se registra y pasa
 * directo al login normal. Se llega aca desde un equipo "cliente" (sin
 * PHP ni base de datos propia, ver src-tauri-local/src/lib.rs) que abre
 * su webview directo contra esta URL en el servidor de la red local.
 */
class EmparejarTerminalLocalController extends Controller
{
    public function mostrar(Request $request)
    {
        $machineId = (string) $request->query('machine_id', '');

        if ($machineId !== '' && ActivacionLocal::where('machine_id', $machineId)->exists()) {
            return redirect('/login');
        }

        return view('emparejar-terminal-local', ['machineId' => $machineId]);
    }

    public function emparejar(Request $request, ValidadorLicencia $validador)
    {
        $data = $request->validate([
            'codigo' => 'required|string',
            'machine_id' => 'required|string',
        ]);

        $machineId = trim($data['machine_id']);

        if (ActivacionLocal::where('machine_id', $machineId)->exists()) {
            return redirect('/login');
        }

        try {
            $activacion = $validador->verificar(trim($data['codigo']), $machineId);
        } catch (LicenciaInvalidaException $e) {
            return back()->withInput()->withErrors(['codigo' => $e->getMessage()]);
        }

        if ($activacion->rol !== 'cliente') {
            return back()->withInput()->withErrors([
                'codigo' => 'Este código es de un equipo servidor, no de un terminal. Para activar un servidor nuevo, usá la pantalla de activación de esa instalación.',
            ]);
        }

        $servidor = ActivacionLocal::where('rol', 'servidor')->first();

        if (! $servidor || (int) $servidor->empresa_id !== $activacion->empresaId) {
            return back()->withInput()->withErrors([
                'codigo' => 'Este código fue emitido para otra empresa -- no corresponde a este servidor.',
            ]);
        }

        ActivacionLocal::create([
            'licencia_id' => $activacion->licenciaId,
            'empresa_id' => $activacion->empresaId,
            'empresa_nombre' => $activacion->empresaNombre,
            'machine_id' => $machineId,
            'rol' => 'cliente',
            'codigo_raw' => $data['codigo'],
            'activada_at' => now(),
        ]);

        return redirect('/login');
    }
}
