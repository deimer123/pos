<?php

namespace App\Livewire;

use App\Models\Actor;
use App\Models\Caja;
use App\Models\Gasto;
use App\Models\HotelHabitacion;
use App\Models\HotelReserva;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Component;

class HotelPanel extends Component
{
    // ── Vista activa: 'habitaciones' | 'calendario'
    public string $vistaActiva = 'habitaciones';
    public string $busqueda    = '';
    public string $filtroZona  = '';
    public string $filtroEstado = 'libre'; // 'todas' | 'libre' | 'ocupada' | 'reservada'

    // ── Modal Reserva (nueva / editar)
    public bool   $modalReserva          = false;
    public ?int   $reservaId             = null;
    public ?int   $resHabitacionId       = null;
    public bool   $resHabitacionBloqueada = false; // true = la habitación ya viene definida (se abrió desde una habitación o celda específica)
    public bool   $resInmediato          = false;  // true = "check-in ahora" (huésped llega de una vez, sin abono); false = reserva a futuro
    public ?int   $resActorId            = null;
    public string $resHuespedNombre      = '';
    public string $resHuespedTelefono    = '';
    public string $resHuespedDocumento   = '';
    public string $resClimatizacion      = 'ninguno'; // 'ninguno' | 'aire' | 'ventilador'
    public string $resNumeroPersonas     = '1';
    public string $resFechaCheckin       = '';
    public string $resFechaCheckout      = '';
    public string $resObservaciones      = '';
    public string $resAbonoMonto         = '';
    public string $resAbonoMedioPago     = 'Efectivo';

    // ── Buscar / crear cliente (huésped) dentro del modal de reserva
    public bool   $modalBuscarClienteHotel = false;
    public string $buscarClienteHotelTexto = '';
    public bool   $modalCrearClienteHotel  = false;
    public array  $nuevoClienteHotel       = [];

    // ── Ver reserva (informativo, desde el calendario)
    public bool $modalVerReserva = false;
    public ?int $verReservaId    = null;

    // ── Calendario de reservas
    public string $calDesde = '';
    public string $calHasta = '';

    public function mount(): void
    {
        $this->calDesde = now()->toDateString();
        $this->calHasta = now()->addDays(6)->toDateString();
        $this->cancelarReservasNoPresentadas();
    }

    private function empresaId(): int
    {
        return auth()->user()->getEmpresaActualId();
    }

    // Si una reserva sigue en estado "reservada" (el huésped nunca llegó a
    // hacer la entrada) y ya pasaron más de 24 horas desde su fecha de
    // entrada, se cancela sola por huésped no presentado, para no dejarla
    // acumulada para siempre en la pestaña Reservadas. Se revisa cada vez
    // que se abre el panel de Hotel (no depende de un cron en el servidor).
    private function cancelarReservasNoPresentadas(): void
    {
        HotelReserva::where('empresa_id', $this->empresaId())
            ->where('estado', 'reservada')
            ->whereDate('fecha_checkin', '<', now()->subHours(24)->toDateString())
            ->get()
            ->each(function (HotelReserva $r) {
                $r->update([
                    'estado' => 'cancelada',
                    'observaciones' => trim(($r->observaciones ? $r->observaciones . ' · ' : '')
                        . '⚠️ Cancelada automáticamente: el huésped no se presentó, pasadas 24h de la fecha de entrada.'),
                ]);
            });
    }

    // ── Habitaciones ──────────────────────────────────────────────────────

    // Todas las habitaciones (según búsqueda/zona) con su estado calculado,
    // SIN aplicar el filtro de estado — sirve tanto para listar como para
    // contar cuántas hay en cada estado para los botones de filtro.
    private function habitacionesConEstado()
    {
        $empresaId = $this->empresaId();
        $hoy = now()->toDateString();

        $habitaciones = HotelHabitacion::where('empresa_id', $empresaId)
            ->where('activa', true)
            ->when($this->busqueda, fn ($q) => $q->where('numero', 'like', '%' . $this->busqueda . '%'))
            ->when($this->filtroZona, fn ($q) => $q->where('zona', $this->filtroZona))
            ->orderBy('numero')
            ->get();

        return $habitaciones->map(function (HotelHabitacion $h) use ($hoy) {
            // Un huésped con estado "checkin" sigue ocupando la habitación hasta
            // que se factura su salida (eso es lo único que cambia su estado a
            // "checkout") — sin importar si su fecha_checkout planeada ya pasó
            // o es hoy. Esa fecha solo importa para saber si una reserva
            // "reservada" (que aún no ha llegado) sigue vigente.
            $reservaActiva = HotelReserva::where('habitacion_id', $h->id)
                ->where(function ($q) use ($hoy) {
                    $q->where('estado', 'checkin')
                        ->orWhere(function ($qq) use ($hoy) {
                            $qq->where('estado', 'reservada')
                                ->whereDate('fecha_checkin', '<=', $hoy)
                                ->where(fn ($q3) => $q3->whereNull('fecha_checkout')->orWhereDate('fecha_checkout', '>', $hoy));
                        });
                })
                ->orderBy('fecha_checkin')
                ->first();

            $proximaReserva = HotelReserva::where('habitacion_id', $h->id)
                ->where('estado', 'reservada')
                ->whereDate('fecha_checkin', '>', $hoy)
                ->orderBy('fecha_checkin')
                ->first();

            $h->reserva_activa  = $reservaActiva;
            $h->proxima_reserva = $proximaReserva;
            $h->estado_actual   = $reservaActiva
                ? ($reservaActiva->estado === 'checkin' ? 'ocupada' : 'reservada')
                : 'libre';

            return $h;
        });
    }

    public function getHabitacionesProperty()
    {
        $habitaciones = $this->habitacionesConEstado();

        if ($this->filtroEstado !== 'todas') {
            $habitaciones = $habitaciones->where('estado_actual', $this->filtroEstado)->values();
        }

        return $habitaciones;
    }

    public function getConteoEstadosProperty(): array
    {
        $habitaciones = $this->habitacionesConEstado();

        return [
            'todas'     => $habitaciones->count(),
            'libre'     => $habitaciones->where('estado_actual', 'libre')->count(),
            'ocupada'   => $habitaciones->where('estado_actual', 'ocupada')->count(),
            'reservada' => $this->reservas->count(),
        ];
    }

    // TODAS las reservas con estado "reservada" (apartadas para cualquier
    // fecha, ya sea hoy/atrasadas o a futuro), no solo la que esté activa
    // hoy en la habitación — una habitación puede tener varias reservas
    // futuras en fila, así que esta pestaña lista reservas, no habitaciones.
    public function getReservasProperty()
    {
        $empresaId = $this->empresaId();

        return HotelReserva::where('empresa_id', $empresaId)
            ->where('estado', 'reservada')
            ->whereHas('habitacion', function ($q) use ($empresaId) {
                $q->where('empresa_id', $empresaId)
                    ->when($this->busqueda, fn ($qq) => $qq->where('numero', 'like', '%' . $this->busqueda . '%'))
                    ->when($this->filtroZona, fn ($qq) => $qq->where('zona', $this->filtroZona));
            })
            ->with('habitacion')
            ->orderBy('fecha_checkin')
            ->get();
    }

    public function getZonasDisponiblesProperty(): array
    {
        return HotelHabitacion::where('empresa_id', $this->empresaId())
            ->where('activa', true)
            ->whereNotNull('zona')
            ->where('zona', '!=', '')
            ->distinct()
            ->orderBy('zona')
            ->pluck('zona')
            ->toArray();
    }

    // Las habitaciones (camas, aire/ventilador, precio por número de
    // personas) se crean y editan en Administración → Hotel → Habitaciones,
    // no aquí — este panel es solo para operar las reservas del día a día.

    // Avisos para el recepcionista: huéspedes que debían llegar hoy (o antes)
    // y aún no hicieron check-in, y huéspedes que ya están hospedados y
    // tienen salida programada para hoy.
    public function getAlertasProperty(): array
    {
        $empresaId = $this->empresaId();
        $hoy = now()->toDateString();

        $checkinsPendientes = HotelReserva::where('empresa_id', $empresaId)
            ->where('estado', 'reservada')
            ->whereDate('fecha_checkin', '<=', $hoy)
            ->with('habitacion')
            ->orderBy('fecha_checkin')
            ->get();

        $salidasHoy = HotelReserva::where('empresa_id', $empresaId)
            ->where('estado', 'checkin')
            ->whereDate('fecha_checkout', $hoy)
            ->with('habitacion')
            ->orderBy('fecha_checkout')
            ->get();

        return [
            'checkinsPendientes' => $checkinsPendientes,
            'salidasHoy' => $salidasHoy,
        ];
    }

    // ── Reservas ──────────────────────────────────────────────────────────

    public function abrirNuevaReserva(?int $habitacionId = null, bool $inmediato = false, ?string $fecha = null): void
    {
        $fechaCheckin = $fecha ?: now()->toDateString();

        $this->reservaId            = null;
        $this->resHabitacionId      = $habitacionId;
        $this->resHabitacionBloqueada = $habitacionId !== null;
        $this->resInmediato         = $inmediato;
        $this->resActorId           = null;
        $this->resHuespedNombre     = '';
        $this->resHuespedTelefono   = '';
        $this->resHuespedDocumento  = '';
        $this->resClimatizacion     = 'ninguno';
        $this->resNumeroPersonas    = '1';
        $this->resFechaCheckin      = $fechaCheckin;
        $this->resFechaCheckout     = $inmediato ? '' : Carbon::parse($fechaCheckin)->addDay()->toDateString();
        $this->resObservaciones     = '';
        $this->resAbonoMonto        = '';
        $this->resAbonoMedioPago    = 'Efectivo';
        $this->modalReserva         = true;
    }

    public function abrirEditarReserva(int $id): void
    {
        $r = HotelReserva::where('empresa_id', $this->empresaId())->findOrFail($id);

        $this->reservaId            = $r->id;
        $this->resHabitacionId      = $r->habitacion_id;
        $this->resHabitacionBloqueada = true;
        $this->resInmediato         = false;
        $this->resActorId           = $r->actor_id;
        $this->resHuespedNombre     = $r->huesped_nombre;
        $this->resHuespedTelefono   = $r->huesped_telefono ?? '';
        $this->resHuespedDocumento  = $r->huesped_documento ?? '';
        $this->resClimatizacion     = $r->climatizacion ?? 'ninguno';
        $this->resNumeroPersonas    = (string) $r->numero_personas;
        $this->resFechaCheckin      = $r->fecha_checkin->toDateString();
        $this->resFechaCheckout     = $r->fecha_checkout?->toDateString() ?? '';
        $this->resObservaciones     = $r->observaciones ?? '';
        $this->resAbonoMonto        = '';
        $this->resAbonoMedioPago    = 'Efectivo';
        $this->modalReserva         = true;
    }

    // Vista informativa de una reserva (desde el calendario): solo para
    // consultar los datos, no se puede editar desde aquí.
    public function verReserva(int $id): void
    {
        $this->verReservaId   = $id;
        $this->modalVerReserva = true;
    }

    public function getReservaVistaProperty(): ?HotelReserva
    {
        if (! $this->verReservaId) {
            return null;
        }

        return HotelReserva::where('empresa_id', $this->empresaId())
            ->with(['habitacion', 'consumos'])
            ->find($this->verReservaId);
    }

    // Desde la vista informativa, si de verdad se necesita editar, pasa al
    // modal de edición normal.
    public function editarDesdeVerReserva(): void
    {
        if (! $this->verReservaId) return;

        $id = $this->verReservaId;
        $this->modalVerReserva = false;
        $this->verReservaId    = null;
        $this->abrirEditarReserva($id);
    }

    // ── Buscar / crear cliente (huésped) ─────────────────────────────────

    public function abrirModalBuscarClienteHotel(): void
    {
        $this->buscarClienteHotelTexto = '';
        $this->modalBuscarClienteHotel = true;
    }

    public function getResultadosClienteHotelProperty()
    {
        $query = Actor::where('empresa_id', $this->empresaId())
            ->where(fn ($q) => $q->where('clasificacion', 'cliente')->orWhere('clasificacion', 'cliente_proveedor'));

        if (trim($this->buscarClienteHotelTexto) !== '') {
            $texto = '%' . $this->buscarClienteHotelTexto . '%';

            return $query->where(fn ($q) => $q->where('nombre', 'like', $texto)
                ->orWhere('identificacion', 'like', $texto)
                ->orWhere('telefono', 'like', $texto))
                ->orderBy('nombre')
                ->limit(20)
                ->get();
        }

        return $query->latest('id')->limit(10)->get();
    }

    public function seleccionarClienteHotel(int $actorId): void
    {
        $actor = Actor::where('empresa_id', $this->empresaId())->find($actorId);
        if (! $actor) {
            return;
        }

        $this->resActorId          = $actor->id;
        $this->resHuespedNombre    = (string) $actor->nombre;
        $this->resHuespedTelefono  = (string) ($actor->telefono ?? '');
        $this->resHuespedDocumento = (string) ($actor->identificacion ?? '');
        $this->modalBuscarClienteHotel = false;
    }

    public function abrirModalCrearClienteHotel(): void
    {
        $this->resetErrorBag();
        $this->nuevoClienteHotel = [
            'tipo_documento_id'  => 3,
            'identificacion'     => '',
            'nombre'             => '',
            'razon_social'       => '',
            'email'              => '',
            'telefono'           => '',
            'direccion'          => '',
            'departamento_id'    => '',
            'ciudad_id'          => '',
            'tipo_persona'       => '',
            'regimen_tributario' => '',
            'responsable_iva'    => '',
        ];
        $this->modalCrearClienteHotel = true;
    }

    // Mismos campos y reglas que el "Crear Cliente" del POS base, para que el
    // cliente quede completo (útil para facturación electrónica) sin
    // importar desde dónde se creó.
    public function guardarClienteHotel(): void
    {
        $this->resetErrorBag();
        $empresaId = $this->empresaId();

        $this->nuevoClienteHotel['responsable_iva'] = $this->nuevoClienteHotel['responsable_iva'] !== ''
            ? (int) $this->nuevoClienteHotel['responsable_iva']
            : null;

        if ($this->nuevoClienteHotel['regimen_tributario'] === 'simplificado' && $this->nuevoClienteHotel['responsable_iva'] == 1) {
            $this->addError('nuevoClienteHotel.responsable_iva', 'Un régimen simplificado no puede ser responsable de IVA.');
        }

        if ($this->nuevoClienteHotel['regimen_tributario'] === 'comun' && $this->nuevoClienteHotel['responsable_iva'] != 1) {
            $this->addError('nuevoClienteHotel.responsable_iva', 'Un régimen común debe ser responsable de IVA.');
        }

        if ($this->nuevoClienteHotel['tipo_persona'] === 'juridica' && (int) $this->nuevoClienteHotel['tipo_documento_id'] !== 6) {
            $this->addError('nuevoClienteHotel.tipo_documento_id', 'Una persona jurídica solo puede tener NIT como tipo de documento.');
        }

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        $this->validate([
            'nuevoClienteHotel.identificacion' => [
                'required',
                Rule::unique('actors', 'identificacion')->where(fn ($q) => $q->where('empresa_id', $empresaId)),
            ],
            'nuevoClienteHotel.nombre' => [
                'required',
                Rule::unique('actors', 'nombre')->where(fn ($q) => $q->where('empresa_id', $empresaId)),
            ],
            'nuevoClienteHotel.email' => [
                'required',
                'email',
                Rule::unique('actors', 'email')->where(fn ($q) => $q->where('empresa_id', $empresaId)),
            ],
            'nuevoClienteHotel.telefono'           => 'required|numeric',
            'nuevoClienteHotel.departamento_id'    => 'required|exists:departamentos,id',
            'nuevoClienteHotel.ciudad_id'          => 'required|exists:ciudades,id',
            'nuevoClienteHotel.direccion'          => 'required',
            'nuevoClienteHotel.responsable_iva'    => 'required|in:0,1',
            'nuevoClienteHotel.regimen_tributario' => 'required',
            'nuevoClienteHotel.tipo_persona'       => 'required',
        ], [
            'nuevoClienteHotel.identificacion.required' => 'Debe ingresar una identificación.',
            'nuevoClienteHotel.identificacion.unique'   => 'Este número de identificación ya está registrado.',
            'nuevoClienteHotel.nombre.required'         => 'Debe ingresar un nombre.',
            'nuevoClienteHotel.nombre.unique'           => 'El nombre ya existe.',
            'nuevoClienteHotel.email.required'          => 'Debe ingresar un correo electrónico.',
            'nuevoClienteHotel.email.email'             => 'Debe ingresar un correo válido.',
            'nuevoClienteHotel.email.unique'            => 'Este correo ya está registrado.',
            'nuevoClienteHotel.telefono.required'          => 'Debe ingresar un teléfono.',
            'nuevoClienteHotel.departamento_id.required'   => 'Seleccione un departamento.',
            'nuevoClienteHotel.ciudad_id.required'         => 'Seleccione una ciudad.',
            'nuevoClienteHotel.direccion.required'         => 'Ingrese una dirección.',
            'nuevoClienteHotel.responsable_iva.required'   => 'Indique si es responsable de IVA.',
            'nuevoClienteHotel.regimen_tributario.required' => 'Seleccione un régimen tributario.',
            'nuevoClienteHotel.tipo_persona.required'      => 'Seleccione un tipo de persona.',
        ]);

        $actor = Actor::create([
            'id_clip_pro'        => (int) Actor::max('id_clip_pro') + 1,
            'empresa_id'         => $empresaId,
            'tipo'               => 1,
            'tipo_documento_id'  => $this->nuevoClienteHotel['tipo_documento_id'],
            'identificacion'     => $this->nuevoClienteHotel['identificacion'],
            'nombre'             => $this->nuevoClienteHotel['nombre'],
            'razon_social'       => $this->nuevoClienteHotel['razon_social'] ?: null,
            'email'              => $this->nuevoClienteHotel['email'],
            'telefono'           => $this->nuevoClienteHotel['telefono'],
            'direccion'          => $this->nuevoClienteHotel['direccion'],
            'departamento_id'    => $this->nuevoClienteHotel['departamento_id'],
            'ciudad_id'          => $this->nuevoClienteHotel['ciudad_id'],
            'tipo_persona'       => $this->nuevoClienteHotel['tipo_persona'],
            'regimen_tributario' => $this->nuevoClienteHotel['regimen_tributario'],
            'responsable_iva'    => $this->nuevoClienteHotel['responsable_iva'],
            'clasificacion'      => 'cliente',
        ]);

        $this->resActorId          = $actor->id;
        $this->resHuespedNombre    = (string) $actor->nombre;
        $this->resHuespedTelefono  = (string) ($actor->telefono ?? '');
        $this->resHuespedDocumento = (string) ($actor->identificacion ?? '');
        $this->modalCrearClienteHotel = false;
        $this->dispatch('notify', type: 'success', message: 'Cliente creado.');
    }

    public function getTodasHabitacionesProperty()
    {
        return HotelHabitacion::where('empresa_id', $this->empresaId())
            ->where('activa', true)
            ->orderBy('numero')
            ->get();
    }

    public function getHabitacionSeleccionadaProperty(): ?HotelHabitacion
    {
        if (! $this->resHabitacionId) {
            return null;
        }

        return HotelHabitacion::where('empresa_id', $this->empresaId())->find($this->resHabitacionId);
    }

    // Si la habitación que se está por ocupar (entrada inmediata) ya tiene
    // una reserva futura, hay que avisarle al recepcionista de una vez para
    // que le informe al huésped hasta cuándo puede quedarse.
    public function getProximaReservaSeleccionadaProperty(): ?HotelReserva
    {
        if (! $this->resHabitacionId) {
            return null;
        }

        return HotelReserva::where('habitacion_id', $this->resHabitacionId)
            ->when($this->reservaId, fn ($q) => $q->where('id', '!=', $this->reservaId))
            ->where('estado', 'reservada')
            ->whereDate('fecha_checkin', '>', now()->toDateString())
            ->orderBy('fecha_checkin')
            ->first();
    }

    public function getPrecioNocheReservaProperty(): float
    {
        $habitacion = $this->habitacionSeleccionada;
        if (! $habitacion) {
            return 0;
        }

        $personas = max(1, (int) $this->resNumeroPersonas);
        $clima    = $this->resClimatizacion === 'ninguno' ? null : $this->resClimatizacion;

        return $habitacion->precioNochePara($personas, $clima);
    }

    public function getTotalEstimadoReservaProperty(): float
    {
        if (! $this->habitacionSeleccionada || ! $this->resFechaCheckin || ! $this->resFechaCheckout) {
            return 0;
        }

        $noches = max(1, Carbon::parse($this->resFechaCheckin)->diffInDays(Carbon::parse($this->resFechaCheckout)));

        return round($this->precioNocheReserva * $noches, 2);
    }

    public function guardarReserva(): void
    {
        $this->validate([
            'resHabitacionId'    => 'required|integer',
            'resHuespedNombre'   => 'required|string|max:200',
            'resNumeroPersonas'  => 'required|integer|min:1',
            'resFechaCheckin'    => 'required|date',
            'resFechaCheckout'   => 'nullable|date|after:resFechaCheckin',
        ], [
            'resHabitacionId.required'   => 'Selecciona una habitación.',
            'resHuespedNombre.required'  => 'El nombre del huésped es obligatorio.',
            'resFechaCheckout.after'     => 'La fecha de salida debe ser posterior a la de entrada.',
        ]);

        $empresaId = $this->empresaId();

        $habitacion = HotelHabitacion::where('empresa_id', $empresaId)->find($this->resHabitacionId);
        if (! $habitacion) {
            $this->addError('resHabitacionId', 'Habitación no válida.');
            return;
        }

        if ((int) $this->resNumeroPersonas > $habitacion->capacidad_maxima) {
            $this->addError('resNumeroPersonas', 'Supera la capacidad máxima de la habitación (' . $habitacion->capacidad_maxima . ' personas).');
            return;
        }

        if ($this->resClimatizacion === 'aire' && ! $habitacion->tiene_aire) {
            $this->addError('resClimatizacion', 'Esta habitación no tiene aire acondicionado.');
            return;
        }

        if ($this->resClimatizacion === 'ventilador' && ! $habitacion->tiene_ventilador) {
            $this->addError('resClimatizacion', 'Esta habitación no tiene ventilador.');
            return;
        }

        $climatizacion = $this->resClimatizacion === 'ninguno' ? null : $this->resClimatizacion;
        $precioNoche   = $habitacion->precioNochePara((int) $this->resNumeroPersonas, $climatizacion);
        if ($precioNoche <= 0) {
            $this->addError('resNumeroPersonas', 'Esta habitación no tiene un precio configurado para esa cantidad de personas. Configúralo en Administración → Hotel → Habitaciones.');
            return;
        }

        // Si la nueva reserva no tiene fecha de salida definida (el huésped
        // no sabe cuándo se va), bloquea la habitación indefinidamente desde
        // el check-in, así que choca con cualquier reserva activa desde ese
        // punto en adelante. Si sí tiene fecha de salida, solo choca con
        // reservas que se crucen en ese rango.
        $conflictoQuery = HotelReserva::where('habitacion_id', $this->resHabitacionId)
            ->whereIn('estado', ['reservada', 'checkin'])
            ->when($this->reservaId, fn ($q) => $q->where('id', '!=', $this->reservaId))
            ->where(fn ($q) => $q->whereNull('fecha_checkout')->orWhere('fecha_checkout', '>', $this->resFechaCheckin));

        if ($this->resFechaCheckout) {
            $conflictoQuery->where('fecha_checkin', '<', $this->resFechaCheckout);
        }

        if ($conflictoQuery->exists()) {
            $this->dispatch('notify', type: 'error', message: 'La habitación ya tiene una reserva en ese rango de fechas.');
            return;
        }

        $data = [
            'empresa_id'        => $empresaId,
            'habitacion_id'     => $this->resHabitacionId,
            'actor_id'          => $this->resActorId,
            'huesped_nombre'    => trim($this->resHuespedNombre),
            'huesped_telefono'  => trim($this->resHuespedTelefono) ?: null,
            'huesped_documento' => trim($this->resHuespedDocumento) ?: null,
            'climatizacion'     => $climatizacion,
            'numero_personas'   => (int) $this->resNumeroPersonas,
            'fecha_checkin'     => $this->resFechaCheckin,
            'fecha_checkout'    => $this->resFechaCheckout ?: null,
            'precio_noche'      => $precioNoche,
            'observaciones'     => trim($this->resObservaciones) ?: null,
        ];

        if ($this->reservaId) {
            HotelReserva::where('empresa_id', $empresaId)->where('id', $this->reservaId)->update($data);
        } else {
            $data['creado_por'] = auth()->id();

            if ($this->resInmediato) {
                // El huésped llega para hospedarse de una vez: entra directo
                // a "checkin", sin pasar por "reservada" ni pedir abono.
                $data['estado']          = 'checkin';
                $data['checkin_real_at'] = now();
            } else {
                $data['estado'] = 'reservada';
            }

            $reserva = HotelReserva::create($data);

            $abono = (float) $this->resAbonoMonto;
            if ($abono > 0) {
                $this->registrarAbonoReserva($reserva, $abono, $this->resAbonoMedioPago);
            }
        }

        $this->modalReserva = false;
        $this->dispatch('notify', type: 'success', message: $this->reservaId ? 'Reserva actualizada.' : ($this->resInmediato ? 'Entrada registrada.' : 'Reserva creada.'));
    }

    private function registrarAbonoReserva(HotelReserva $reserva, float $monto, string $medioPago): void
    {
        $empresaId = $this->empresaId();

        $reserva->update([
            'abono_monto'      => $monto,
            'abono_medio_pago' => $medioPago,
        ]);

        $cajaActiva = Caja::where('empresa_id', $empresaId)
            ->where('estado', 'abierta')
            ->latest('opened_at')
            ->first();

        $ultimo = Gasto::where('empresa_id', $empresaId)->max('id_gasto');

        Gasto::create([
            'id_gasto'    => $ultimo ? $ultimo + 1 : 1,
            'empresa_id'  => $empresaId,
            'tipo'        => 'entrada',
            'categoria'   => 'Abono hotel',
            'descripcion' => 'Abono reserva #' . str_pad((string) $reserva->numero_reserva, 4, '0', STR_PAD_LEFT) . ' - ' . $reserva->huesped_nombre,
            'monto'       => $monto,
            'fecha'       => today()->toDateString(),
            'metodo_pago' => $medioPago,
            'created_by'  => auth()->id(),
            'caja_id'     => $cajaActiva?->id,
        ]);
    }

    public function confirmarCheckin(int $reservaId): void
    {
        $r = HotelReserva::where('empresa_id', $this->empresaId())->findOrFail($reservaId);
        $r->update(['estado' => 'checkin', 'checkin_real_at' => now()]);

        $this->dispatch('notify', type: 'success', message: 'Check-in registrado para ' . $r->huesped_nombre . '.');
    }

    public function irAFacturar(int $reservaId): void
    {
        $this->redirect(route('pos') . '?hotel=' . $reservaId);
    }

    public function cancelarReserva(int $reservaId): void
    {
        if (! auth()->user()->hasRole('admin_empresa')) {
            $this->dispatch('notify', type: 'error', message: 'Solo el administrador de la empresa puede cancelar reservas.');
            return;
        }

        HotelReserva::where('empresa_id', $this->empresaId())->where('id', $reservaId)->update(['estado' => 'cancelada']);
        $this->dispatch('notify', type: 'success', message: 'Reserva cancelada.');
    }

    // ── Calendario ────────────────────────────────────────────────────────

    public function getDiasCalendarioProperty(): array
    {
        $desde = Carbon::parse($this->calDesde ?: now());
        $hasta = Carbon::parse($this->calHasta ?: now()->addDays(6));

        if ($hasta->lt($desde)) {
            $hasta = $desde->copy()->addDays(6);
        }

        $dias = [];
        for ($d = $desde->copy(); $d->lte($hasta); $d->addDay()) {
            $dias[] = $d->copy();
        }

        return $dias;
    }

    public function getCalendarioProperty(): array
    {
        $empresaId = $this->empresaId();
        $dias = $this->diasCalendario;

        if (empty($dias)) {
            return [];
        }

        $desde = $dias[0]->toDateString();
        $hasta = end($dias)->toDateString();

        $habitaciones = HotelHabitacion::where('empresa_id', $empresaId)->where('activa', true)->orderBy('numero')->get();

        $reservas = HotelReserva::where('empresa_id', $empresaId)
            ->whereIn('estado', ['reservada', 'checkin'])
            ->where('fecha_checkin', '<', $hasta)
            ->where(fn ($q) => $q->whereNull('fecha_checkout')->orWhere('fecha_checkout', '>=', $desde))
            ->get();

        return $habitaciones->map(function (HotelHabitacion $h) use ($dias, $reservas) {
            $celdas = [];
            foreach ($dias as $dia) {
                $reserva = $reservas->first(function ($r) use ($h, $dia) {
                    return (int) $r->habitacion_id === (int) $h->id
                        && $dia->gte($r->fecha_checkin)
                        && ($r->fecha_checkout === null || $dia->lt($r->fecha_checkout));
                });

                $celdas[] = [
                    'fecha'   => $dia->toDateString(),
                    'reserva' => $reserva,
                ];
            }

            return [
                'habitacion' => $h,
                'celdas'     => $celdas,
            ];
        })->toArray();
    }

    public function render()
    {
        return view('livewire.hotel-panel', [
            'habitaciones'      => $this->habitaciones,
            'todasHabitaciones' => $this->todasHabitaciones,
            'zonasDisponibles'  => $this->zonasDisponibles,
            'conteoEstados'     => $this->conteoEstados,
            'reservas'          => $this->filtroEstado === 'reservada' && $this->vistaActiva === 'habitaciones' ? $this->reservas : collect(),
            'calendario'        => $this->vistaActiva === 'calendario' ? $this->calendario : [],
            'diasCalendario'    => $this->vistaActiva === 'calendario' ? $this->diasCalendario : [],
            'alertas'           => $this->alertas,
        ]);
    }
}
