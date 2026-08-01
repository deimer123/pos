<?php

namespace App\Filament\Pages;

use App\Models\ConfiguracionEmpresa;
use App\Models\HotelHabitacion;
use App\Models\HotelReserva;
use Carbon\Carbon;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

/**
 * Historial de productos agregados a la cuenta de cada habitación durante
 * sus estadías -- distinto del Kardex de productos (App\Filament\Pages\
 * Kardex), que mueve stock agregado de toda la empresa. Aquí se lee
 * directo HotelReservaConsumo (ver HotelReserva::consumos()), que ya
 * guarda linea por linea lo que se cobró en cada reserva.
 */
class KardexHabitacion extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationLabel = 'Kardex de Habitaciones';
    protected static ?string $navigationGroup = 'Inventario';
    protected static ?int $navigationSort = 3;
    protected static string $view = 'filament.pages.kardex-habitacion';

    public ?int $habitacionId = null;
    public string $desde;
    public string $hasta;

    public function mount(): void
    {
        $this->desde = now()->startOfMonth()->toDateString();
        $this->hasta = now()->toDateString();

        $this->habitacionId = HotelHabitacion::where('empresa_id', $this->empresaId())
            ->orderBy('numero')
            ->value('id');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user || ! ($user->hasRole('admin_empresa') || ($user->hasRole('digitador') && $user->puedeVerResource('kardex')))) {
            return false;
        }

        return (bool) ConfiguracionEmpresa::where('empresa_id', $user->getEmpresaActualId())->value('usa_hotel');
    }

    private function empresaId(): int
    {
        return auth()->user()->getEmpresaActualId();
    }

    public function habitaciones(): Collection
    {
        return HotelHabitacion::where('empresa_id', $this->empresaId())
            ->orderBy('numero')
            ->get();
    }

    /** Reservas de la habitación seleccionada que se solapan con el rango de fechas, con sus consumos. */
    public function reservas(): Collection
    {
        if (! $this->habitacionId) {
            return collect();
        }

        $desde = Carbon::parse($this->desde)->startOfDay();
        $hasta = Carbon::parse($this->hasta)->endOfDay();

        return HotelReserva::where('empresa_id', $this->empresaId())
            ->where('habitacion_id', $this->habitacionId)
            ->where('estado', '!=', 'cancelada')
            ->whereDate('fecha_checkin', '<=', $hasta)
            ->where(function ($q) use ($desde) {
                $q->whereNull('fecha_checkout')->orWhereDate('fecha_checkout', '>=', $desde);
            })
            ->with(['consumos' => fn ($q) => $q->orderBy('created_at'), 'factura'])
            ->orderByDesc('fecha_checkin')
            ->get();
    }
}
