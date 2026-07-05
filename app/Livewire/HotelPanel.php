<?php

namespace App\Livewire;

use App\Models\Caja;
use App\Models\Gasto;
use App\Models\HotelHabitacion;
use App\Models\HotelReserva;
use Illuminate\Support\Carbon;
use Livewire\Component;

class HotelPanel extends Component
{
    // ── Vista activa: 'habitaciones' | 'calendario'
    public string $vistaActiva = 'habitaciones';
    public string $busqueda    = '';
    public string $filtroZona  = '';

    // ── Modal Reserva (nueva / editar)
    public bool   $modalReserva          = false;
    public ?int   $reservaId             = null;
    public ?int   $resHabitacionId       = null;
    public string $resHuespedNombre      = '';
    public string $resHuespedTelefono    = '';
    public string $resHuespedDocumento   = '';
    public string $resNumeroPersonas     = '1';
    public string $resFechaCheckin       = '';
    public string $resFechaCheckout      = '';
    public string $resObservaciones      = '';
    public string $resAbonoMonto         = '';
    public string $resAbonoMedioPago     = 'Efectivo';

    // ── Calendario de reservas
    public string $calDesde = '';
    public string $calHasta = '';

    public function mount(): void
    {
        $this->calDesde = now()->toDateString();
        $this->calHasta = now()->addDays(6)->toDateString();
    }

    private function empresaId(): int
    {
        return auth()->user()->getEmpresaActualId();
    }

    // ── Habitaciones ──────────────────────────────────────────────────────

    public function getHabitacionesProperty()
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
            $reservaActiva = HotelReserva::where('habitacion_id', $h->id)
                ->whereIn('estado', ['reservada', 'checkin'])
                ->whereDate('fecha_checkin', '<=', $hoy)
                ->where(fn ($q) => $q->whereNull('fecha_checkout')->orWhereDate('fecha_checkout', '>', $hoy))
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

    // ── Reservas ──────────────────────────────────────────────────────────

    public function abrirNuevaReserva(?int $habitacionId = null): void
    {
        $this->reservaId           = null;
        $this->resHabitacionId     = $habitacionId;
        $this->resHuespedNombre    = '';
        $this->resHuespedTelefono  = '';
        $this->resHuespedDocumento = '';
        $this->resNumeroPersonas   = '1';
        $this->resFechaCheckin     = now()->toDateString();
        $this->resFechaCheckout    = now()->addDay()->toDateString();
        $this->resObservaciones    = '';
        $this->resAbonoMonto       = '';
        $this->resAbonoMedioPago   = 'Efectivo';
        $this->modalReserva        = true;
    }

    public function abrirEditarReserva(int $id): void
    {
        $r = HotelReserva::where('empresa_id', $this->empresaId())->findOrFail($id);

        $this->reservaId           = $r->id;
        $this->resHabitacionId     = $r->habitacion_id;
        $this->resHuespedNombre    = $r->huesped_nombre;
        $this->resHuespedTelefono  = $r->huesped_telefono ?? '';
        $this->resHuespedDocumento = $r->huesped_documento ?? '';
        $this->resNumeroPersonas   = (string) $r->numero_personas;
        $this->resFechaCheckin     = $r->fecha_checkin->toDateString();
        $this->resFechaCheckout    = $r->fecha_checkout?->toDateString() ?? '';
        $this->resObservaciones    = $r->observaciones ?? '';
        $this->resAbonoMonto       = '';
        $this->resAbonoMedioPago   = 'Efectivo';
        $this->modalReserva        = true;
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

    public function getPrecioNocheReservaProperty(): float
    {
        $habitacion = $this->habitacionSeleccionada;
        if (! $habitacion) {
            return 0;
        }

        $personas = max(1, (int) $this->resNumeroPersonas);

        return $habitacion->precioNochePara($personas);
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

        $precioNoche = $habitacion->precioNochePara((int) $this->resNumeroPersonas);
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
            'huesped_nombre'    => trim($this->resHuespedNombre),
            'huesped_telefono'  => trim($this->resHuespedTelefono) ?: null,
            'huesped_documento' => trim($this->resHuespedDocumento) ?: null,
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
            $data['estado']     = 'reservada';
            $reserva = HotelReserva::create($data);

            $abono = (float) $this->resAbonoMonto;
            if ($abono > 0) {
                $this->registrarAbonoReserva($reserva, $abono, $this->resAbonoMedioPago);
            }
        }

        $this->modalReserva = false;
        $this->dispatch('notify', type: 'success', message: $this->reservaId ? 'Reserva actualizada.' : 'Reserva creada.');
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
            'calendario'        => $this->vistaActiva === 'calendario' ? $this->calendario : [],
            'diasCalendario'    => $this->vistaActiva === 'calendario' ? $this->diasCalendario : [],
        ]);
    }
}
