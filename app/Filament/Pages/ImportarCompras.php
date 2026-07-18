<?php

namespace App\Filament\Pages;

use App\Exports\CompraBulkTemplateExport;
use App\Imports\CompraBulkImport;
use App\Models\Actor;
use App\Models\Compra;
use App\Models\Product;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;

class ImportarCompras extends Page
{
    use WithFileUploads;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';
    protected static string $view = 'filament.pages.importar-compras';

    public $proveedor_id;
    public $numero_factura;
    public $tipo_pago = 'contado';
    public $fecha;
    public $fecha_vencimiento;
    public $archivo;
    public array $resultado = [];

    public function mount(): void
    {
        $this->fecha = now()->format('Y-m-d');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function canAccess(): bool
    {
        return auth()->check()
            && auth()->user()->hasRole('admin_empresa')
            && auth()->user()->puedeVerResource('compras');
    }

    public function getTitle(): string
    {
        return 'Carga masiva de una factura de compra';
    }

    public function getProveedores(): array
    {
        $empresaId = auth()->user()->getEmpresaActualId();

        return Actor::query()
            ->where('empresa_id', $empresaId)
            ->whereIn('clasificacion', ['proveedor', 'cliente_proveedor'])
            ->orderBy('nombre')
            ->pluck('nombre', 'id')
            ->toArray();
    }

    public function descargarPlantilla()
    {
        if (! $this->proveedor_id) {
            Notification::make()
                ->title('Selecciona un proveedor primero')
                ->body('La plantilla depende del proveedor: sin elegirlo no se puede armar la lista de productos existentes ni el desplegable.')
                ->warning()
                ->send();

            return;
        }

        $empresaId = auth()->user()->getEmpresaActualId();

        $proveedor = Actor::query()
            ->where('empresa_id', $empresaId)
            ->whereIn('clasificacion', ['proveedor', 'cliente_proveedor'])
            ->find($this->proveedor_id);

        if (! $proveedor) {
            Notification::make()->title('Proveedor no válido.')->danger()->send();

            return;
        }

        if ($this->numero_factura && $this->facturaDuplicada($empresaId, (int) $proveedor->id)) {
            Notification::make()
                ->title('Factura duplicada')
                ->body('Ya existe una compra con este número para este proveedor.')
                ->danger()
                ->send();

            return;
        }

        $productos = Product::query()
            ->where('empresa_id', $empresaId)
            ->where('id_proveedor', (int) $proveedor->id_clip_pro)
            ->with(['familia1', 'subfamilia'])
            ->orderBy('descripcion_larga')
            ->get();

        return Excel::download(new CompraBulkTemplateExport($empresaId, $productos), 'plantilla-compras.xlsx');
    }

    private function facturaDuplicada(int $empresaId, int $proveedorId): bool
    {
        return Compra::where('proveedor_id', $proveedorId)
            ->where('numero_factura', $this->numero_factura)
            ->where('empresa_id', $empresaId)
            ->where('estado', '!=', 'anulada')
            ->exists();
    }

    public function importar()
    {
        $this->validate([
            'proveedor_id' => 'required',
            'numero_factura' => 'required|string|max:100',
            'tipo_pago' => 'required|in:contado,credito',
            'fecha' => 'required|date',
            'fecha_vencimiento' => $this->tipo_pago === 'credito' ? 'required|date|after:fecha' : 'nullable|date',
            'archivo' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        $empresaId = auth()->user()->getEmpresaActualId();

        $proveedor = Actor::query()
            ->where('empresa_id', $empresaId)
            ->whereIn('clasificacion', ['proveedor', 'cliente_proveedor'])
            ->find($this->proveedor_id);

        if (! $proveedor) {
            Notification::make()->title('Proveedor no válido.')->danger()->send();

            return;
        }

        if ($this->facturaDuplicada($empresaId, (int) $proveedor->id)) {
            Notification::make()
                ->title('Factura duplicada')
                ->body('Ya existe una compra con este número para este proveedor.')
                ->danger()
                ->send();

            return;
        }

        @set_time_limit(0);
        @ini_set('max_execution_time', '0');

        $fechaVencimiento = $this->tipo_pago === 'credito' ? $this->fecha_vencimiento : $this->fecha;

        $import = new CompraBulkImport(
            empresaId: $empresaId,
            userId: auth()->id(),
            proveedorActorId: (int) $proveedor->id,
            proveedorClip: (int) $proveedor->id_clip_pro,
            numeroFactura: $this->numero_factura,
            tipoPago: $this->tipo_pago,
            fecha: $this->fecha,
            fechaVencimiento: $fechaVencimiento,
        );

        try {
            Excel::import($import, $this->archivo);
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Error durante la importación')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        $this->resultado = $import->resumen();
        $this->archivo = null;

        if (! $this->resultado['compra']) {
            $errores = count($this->resultado['errores']);

            Notification::make()
                ->title($errores > 0
                    ? "No se creó la compra: {$errores} fila(s) con error. Corrígelas y vuelve a subir el mismo archivo."
                    : 'No se creó la compra: el excel no tenía ningún producto.')
                ->body($errores > 0 ? 'Revisa el detalle abajo.' : null)
                ->danger()
                ->send();

            return;
        }

        $items = $this->resultado['items'];
        $errores = count($this->resultado['errores']);

        $notificacion = Notification::make()
            ->title('Compra creada con ' . $items . ' ítem' . ($items === 1 ? '' : 's') . ($errores ? ", {$errores} fila(s) con error" : ''));

        $errores > 0 ? $notificacion->warning() : $notificacion->success();
        $notificacion->send();

        $this->numero_factura = null;
    }
}
