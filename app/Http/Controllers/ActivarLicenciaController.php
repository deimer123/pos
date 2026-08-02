<?php

namespace App\Http\Controllers;

use App\Exceptions\LocalLicense\LicenciaInvalidaException;
use App\Models\ActivacionLocal;
use App\Models\User;
use App\Services\LocalLicense\ValidadorLicencia;
use App\Support\MachineId;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * Pantalla local (sin login) donde se activa una instalacion de la
 * edicion Local con el codigo firmado que emite el droplet (ver
 * App\Filament\Pages\EmitirLicenciaLocal). La verificacion es 100%
 * offline (ver ValidadorLicencia) -- esta instalacion nunca llama al
 * droplet, ni siquiera aca. Mismo patron que EmparejarTerminalController
 * (Turion), adaptado a codigo firmado en vez de emparejamiento online.
 */
class ActivarLicenciaController extends Controller
{
    public function mostrar()
    {
        if (ActivacionLocal::activa()) {
            return redirect('/login');
        }

        return view('activar-licencia', ['machineId' => MachineId::actual()]);
    }

    public function activar(Request $request, ValidadorLicencia $validador)
    {
        if (ActivacionLocal::activa()) {
            return redirect('/login');
        }

        $data = $request->validate([
            'codigo' => 'required|string',
        ]);

        try {
            $activacion = $validador->verificar(trim($data['codigo']), MachineId::actual());
        } catch (LicenciaInvalidaException $e) {
            return back()->withInput()->withErrors(['codigo' => $e->getMessage()]);
        }

        // Esta pantalla arranca una instalacion NUEVA (crea la empresa y su
        // primer usuario) -- un codigo de rol 'cliente' es para sumar un
        // terminal a un servidor que YA existe (ver
        // EmparejarTerminalLocalController::mostrar()), no tiene sentido
        // aca.
        if ($activacion->rol !== 'servidor') {
            return back()->withInput()->withErrors([
                'codigo' => 'Este código es de un terminal (cliente), no de un equipo servidor. Para emparejar un terminal, hacelo desde la pantalla "Conectar Terminales" del equipo servidor.',
            ]);
        }

        // Paso 2 (wizard minimo): solo pide el usuario administrador. El
        // resto de la configuracion del negocio (tipo_negocio, NIT,
        // representante legal, toggles usa_*) lo completa el wizard que
        // YA existe para cualquier empresa nueva (RequireConfiguracionEmpresa
        // -> ConfiguracionEmpresaResource), no se duplica ese formulario aca.
        return view('activar-licencia-empresa', [
            'licenciaId' => $activacion->licenciaId,
            'empresaId' => $activacion->empresaId,
            'empresaNombre' => $activacion->empresaNombre,
            'machineId' => $activacion->machineId,
            'codigoRaw' => $data['codigo'],
        ]);
    }

    public function guardarEmpresa(Request $request, ValidadorLicencia $validador)
    {
        if (ActivacionLocal::activa()) {
            return redirect('/login');
        }

        $data = $request->validate([
            'licencia_id' => 'required|uuid',
            'empresa_id' => 'required|integer',
            'empresa_nombre' => 'required|string',
            'machine_id' => 'required|string',
            'codigo_raw' => 'required|string',
            'admin_nombre' => 'required|string|max:200',
            'admin_email' => 'required|email|max:200|unique:users,email',
            'admin_password' => 'required|string|min:8|confirmed',
        ]);

        // Re-verifica el codigo en vez de confiar en los campos ocultos
        // del formulario: alguien podria mandar un POST directo a esta
        // ruta con datos manipulados sin haber pasado por activar().
        try {
            $activacion = $validador->verificar($data['codigo_raw'], MachineId::actual());
        } catch (LicenciaInvalidaException $e) {
            return redirect()->route('activar')->withErrors(['codigo' => $e->getMessage()]);
        }

        if ($activacion->rol !== 'servidor') {
            return redirect()->route('activar')->withErrors([
                'codigo' => 'Este código es de un terminal (cliente), no de un equipo servidor.',
            ]);
        }

        $empresa = User::create([
            'name' => $data['admin_nombre'],
            'email' => $data['admin_email'],
            'password' => Hash::make($data['admin_password']),
            'tipo_usuario' => 'empresa',
            'empresa_id' => null,
            'activo' => true,
        ]);

        $empresa->assignRole('admin_empresa');

        ActivacionLocal::create([
            'licencia_id' => $activacion->licenciaId,
            'empresa_id' => $activacion->empresaId,
            'empresa_nombre' => $activacion->empresaNombre,
            'machine_id' => $activacion->machineId,
            'rol' => $activacion->rol,
            'codigo_raw' => $data['codigo_raw'],
            'activada_at' => now(),
        ]);

        Auth::login($empresa);

        return redirect('/admin');
    }
}
