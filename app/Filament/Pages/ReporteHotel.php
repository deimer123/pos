<?php

namespace App\Filament\Pages;

use App\Models\ConfiguracionEmpresa;
use App\Models\HotelHabitacion;
use App\Models\HotelReserva;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class ReporteHotel extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';
    protected static ?string $navigationLabel = 'Reporte de Hotel';
    protected static ?string $navigationGroup = 'Reportes';
    protected static string $view = 'filament.pages.reporte-hotel';

    public string $desde;
    public string $hasta;

    public function mount(): void
    {
        $this->desde = now()->startOfMonth()->toDateString();
        $this->hasta = now()->toDateString();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canAccess(): bool
    {
        if (! auth()->check() || ! auth()->user()->hasRole('admin_empresa')) {
            return false;
        }

        $empresaId = auth()->user()->getEmpresaActualId();

        return (bool) ConfiguracionEmpresa::where('empresa_id', $empresaId)->value('usa_hotel');
    }

    private function empresaId(): int
    {
        return auth()->user()->getEmpresaActualId();
    }

    private function rango(): array
    {
        $desde = Carbon::parse($this->desde)->startOfDay();
        $hasta = Carbon::parse($this->hasta)->endOfDay();

        return [$desde, $hasta];
    }

    public function totalProductos(): float
    {
        [$desde, $hasta] = $this->rango();
        $empresaId = $this->empresaId();

        return (float) DB::table('factura_detalles as fd')
            ->join('facturas as f', 'f.id', '=', 'fd.factura_id')
            ->join('products as p', function ($join) {
                $join->on('p.id_producto', '=', 'fd.producto_id')
                    ->on('p.empresa_id', '=', 'f.empresa_id');
            })
            ->where('f.empresa_id', $empresaId)
            ->whereBetween('f.fecha', [$desde, $hasta])
            ->where('p.tipo_producto', '!=', 'servicio')
            ->where('fd.producto_id', '!=', '10001')
            ->sum(DB::raw('(fd.cantidad - COALESCE(fd.devuelto_cantidad, 0)) * fd.precio'));
    }

    public function utilidadProductos(): float
    {
        [$desde, $hasta] = $this->rango();
        $empresaId = $this->empresaId();

        return (float) DB::table('factura_detalles as fd')
            ->join('facturas as f', 'f.id', '=', 'fd.factura_id')
            ->join('products as p', function ($join) {
                $join->on('p.id_producto', '=', 'fd.producto_id')
                    ->on('p.empresa_id', '=', 'f.empresa_id');
            })
            ->where('f.empresa_id', $empresaId)
            ->whereBetween('f.fecha', [$desde, $hasta])
            ->where('p.tipo_producto', '!=', 'servicio')
            ->where('fd.producto_id', '!=', '10001')
            ->sum(DB::raw('
                ((fd.cantidad - COALESCE(fd.devuelto_cantidad, 0)) * fd.precio)
                -
                (
                    (fd.cantidad - COALESCE(fd.devuelto_cantidad, 0))
                    *
                    (
                        (COALESCE(p.precio_costo, 0) * (1 - (COALESCE(p.descuento_comercial, 0) / 100)))
                        *
                        (1 + (COALESCE(p.iva_venta, 0) / 100))
                    )
                )
            '));
    }

    private function lineasHospedaje()
    {
        [$desde, $hasta] = $this->rango();
        $empresaId = $this->empresaId();

        return DB::table('factura_detalles as fd')
            ->join('facturas as f', 'f.id', '=', 'fd.factura_id')
            ->where('f.empresa_id', $empresaId)
            ->whereBetween('f.fecha', [$desde, $hasta])
            ->where('fd.producto_id', 0)
            ->where('fd.descripcion_larga', 'like', 'Hospedaje habitación%')
            ->select('fd.factura_id', 'fd.subtotal')
            ->get();
    }

    public function totalHospedaje(): float
    {
        return (float) $this->lineasHospedaje()->sum('subtotal');
    }

    public function utilidadHospedaje(): float
    {
        // El hospedaje no tiene "costo de producto": se factura 100% como
        // utilidad porque los gastos del hotel se descuentan aparte (Caja/Gastos).
        return $this->totalHospedaje();
    }

    public function utilidadTotal(): float
    {
        return $this->utilidadProductos() + $this->utilidadHospedaje();
    }

    /** Habitación | Reservas | Noches | Ingresos */
    public function reportePorHabitacion(): array
    {
        $lineas = $this->lineasHospedaje();

        if ($lineas->isEmpty()) {
            return [];
        }

        $facturaIds = $lineas->pluck('factura_id')->unique();

        $reservas = HotelReserva::whereIn('factura_id', $facturaIds)
            ->with('habitacion')
            ->get()
            ->keyBy('factura_id');

        $ingresoPorFactura = $lineas->pluck('subtotal', 'factura_id');

        $filas = [];

        foreach ($reservas as $facturaId => $reserva) {
            $habitacionId = $reserva->habitacion_id;
            $nombreHabitacion = $reserva->habitacion?->numero ?? "#{$habitacionId}";

            if (! isset($filas[$habitacionId])) {
                $filas[$habitacionId] = [
                    'habitacion' => $nombreHabitacion,
                    'reservas' => 0,
                    'noches' => 0,
                    'ingresos' => 0.0,
                ];
            }

            $filas[$habitacionId]['reservas']++;
            $filas[$habitacionId]['noches'] += $reserva->numero_noches;
            $filas[$habitacionId]['ingresos'] += (float) ($ingresoPorFactura[$facturaId] ?? 0);
        }

        usort($filas, fn ($a, $b) => strnatcasecmp($a['habitacion'], $b['habitacion']));

        return $filas;
    }

    private function reservasVigentes()
    {
        [$desde, $hasta] = $this->rango();
        $empresaId = $this->empresaId();

        return HotelReserva::where('empresa_id', $empresaId)
            ->where('estado', '!=', 'cancelada')
            ->whereDate('fecha_checkin', '<=', $hasta)
            ->where(function ($q) use ($desde) {
                $q->whereNull('fecha_checkout')->orWhereDate('fecha_checkout', '>=', $desde);
            })
            ->get();
    }

    /** Devuelve ['habitaciones' => Collection, 'dias' => [Carbon...], 'ocupado' => [habitacion_id][Y-m-d] => 'reservada'|'checkin'|'checkout'|null, 'porcentaje' => float] */
    public function ocupacion(): array
    {
        [$desde, $hasta] = $this->rango();
        $empresaId = $this->empresaId();

        $habitaciones = HotelHabitacion::where('empresa_id', $empresaId)
            ->where('activa', true)
            ->orderBy('numero')
            ->get();

        $reservas = $this->reservasVigentes();
        $hoy = now()->toDateString();

        $dias = CarbonPeriod::create($desde->copy()->startOfDay(), $hasta->copy()->startOfDay());

        $ocupado = [];
        $celdasOcupadas = 0;

        foreach ($habitaciones as $habitacion) {
            $reservasHabitacion = $reservas->where('habitacion_id', $habitacion->id);

            foreach ($dias as $dia) {
                $diaStr = $dia->toDateString();
                $estado = null;

                foreach ($reservasHabitacion as $reserva) {
                    $fin = $reserva->fecha_checkout?->toDateString() ?? $hoy;

                    if ($diaStr >= $reserva->fecha_checkin->toDateString() && $diaStr <= $fin) {
                        // reservada = aún no llega; checkin = hospedado sin facturar
                        // todavía; checkout = ya se facturó su salida.
                        $estado = $reserva->estado;
                        break;
                    }
                }

                $ocupado[$habitacion->id][$diaStr] = $estado;

                if ($estado) {
                    $celdasOcupadas++;
                }
            }
        }

        $totalCeldas = $habitaciones->count() * iterator_count(CarbonPeriod::create($desde->copy()->startOfDay(), $hasta->copy()->startOfDay()));
        $porcentaje = $totalCeldas > 0 ? round(($celdasOcupadas / $totalCeldas) * 100, 2) : 0.0;

        return [
            'habitaciones' => $habitaciones,
            'dias' => CarbonPeriod::create($desde->copy()->startOfDay(), $hasta->copy()->startOfDay()),
            'ocupado' => $ocupado,
            'porcentaje' => $porcentaje,
        ];
    }
}
