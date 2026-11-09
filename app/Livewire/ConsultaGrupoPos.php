<?php

namespace App\Livewire;

use App\Services\Grupo\ConsultaInventarioGrupo;
use Livewire\Component;

// Consulta de SOLO LECTURA, dentro del POS, de cantidad y precio de los
// productos en las otras empresas del grupo (sucursales). No tiene ninguna
// accion que modifique datos ni que agregue al carrito; las empresas se
// calculan en el servidor a partir del usuario, nunca desde el navegador.
class ConsultaGrupoPos extends Component
{
    public bool $abierto = false;

    public string $buscar = '';

    // Maximo de filas en el modal; para mas se afina la busqueda.
    private const LIMITE = 30;

    public function abrir(): void
    {
        abort_unless(app(ConsultaInventarioGrupo::class)->disponible(auth()->user()), 403);

        $this->abierto = true;
    }

    public function cerrar(): void
    {
        $this->abierto = false;
        $this->buscar = '';
    }

    public function render()
    {
        $datos = ['empresas' => [], 'propia' => null, 'filas' => collect(), 'verStock' => false];

        if ($this->abierto) {
            $servicio = app(ConsultaInventarioGrupo::class);
            $usuario = auth()->user();

            abort_unless($servicio->disponible($usuario), 403);

            $empresas = $servicio->empresas($usuario);
            $datos = [
                'empresas' => $empresas,
                'propia' => $usuario->getEmpresaActualId(),
                'filas' => $servicio->query($empresas, $this->buscar)->limit(self::LIMITE)->get(),
                'verStock' => $servicio->puedeVerStock($usuario),
            ];
        }

        return view('livewire.consulta-grupo-pos', $datos);
    }
}
