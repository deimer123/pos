<?php

namespace App\Filament\Resources\AjusteInventarioResource\Pages;

use App\Filament\Resources\AjusteInventarioResource;
use App\Models\AjusteInventario;
use App\Models\AjusteInventarioDetalle;
use App\Models\Product;
use Filament\Resources\Pages\CreateRecord;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;

class CreateAjusteInventario extends CreateRecord
{
    protected static string $resource = AjusteInventarioResource::class;

    protected ?AjusteInventario $draft = null;
    protected function cacheKey(): string
    {
        return 'ajuste_inventario_cache_u_' . Auth::id();
    }

    protected function mutateFormDataBeforeFill(array $data): array
{
    // 👇 SI VIENE DESDE BORRADOR, NO USAR CACHE
    if (request()->has('draft')) {
        return $data;
    }

    return cache()->get($this->cacheKey(), $data);
}

    protected function handleRecordCreation(array $data): AjusteInventario
{
    return DB::transaction(function () use ($data) {

        // 🔎 Buscar borrador (opcional)
        $ajuste = AjusteInventario::where('empresa_id', Auth::user()->getEmpresaActualId())
            ->where('usuario_id', Auth::id())
            ->where('estado', 'borrador')
            ->first();

        // ✅ SI HAY BORRADOR → CONFIRMARLO
        if ($ajuste) {
            $ajuste->update([
                'tipo'        => $data['tipo'],
                'observacion' => $data['observacion'],
                'estado'      => 'confirmado',
            ]);

            return $ajuste;
        }

        // ✅ SI NO HAY BORRADOR → CREAR NUEVO
        return AjusteInventario::create([
            'empresa_id'  => Auth::user()->getEmpresaActualId(),
            'usuario_id'  => Auth::id(),
            'tipo'        => $data['tipo'],
            'observacion' => $data['observacion'],
            'estado'      => 'confirmado',
        ]);
    });
}



    /* =========================
     | POST-CREATE
     ========================= */
 protected function afterCreate(): void
{
    app(\App\Services\Inventario\AplicarAjusteInventarioService::class)->aplicar($this->record);

    $this->reset('data');

    $this->dispatch('clear-inventario-cache');

    $this->redirect(
        $this->getResource()::getUrl('index')
    );
}

protected function mutateFormDataBeforeCreate(array $data): array
{
    $empresaId = auth()->user()->getEmpresaActualId();

    // 🔥 MUY IMPORTANTE: reindexar el repeater
    $detalles = array_values($data['detalles'] ?? []);

    foreach ($detalles as $i => $detalle) {

        if (empty($detalle['producto_id'])) {
            continue;
        }

        $producto = Product::where('empresa_id', $empresaId)
            ->where('id_producto', $detalle['producto_id'])
            ->first();

        if (! $producto) {
            throw new \Exception("Producto {$detalle['producto_id']} no encontrado");
        }

        $existenciasActuales = (int) $producto->existencias;
        $cantidadIngresada   = (int) ($detalle['cantidad_nueva'] ?? 0);

        if ($data['tipo'] === 'ajuste') {

            $cantidadAnterior = $existenciasActuales;
            $diferencia       = $cantidadIngresada - $existenciasActuales;

        } else {

            $cantidadAnterior = 0;
            $diferencia       = $cantidadIngresada;
        }

        $detalles[$i]['cantidad_anterior'] = $cantidadAnterior;
        $detalles[$i]['diferencia']        = $diferencia;
        $detalles[$i]['cantidad_nueva']    = $cantidadIngresada;
    }

    $data['detalles'] = $detalles;

    return $data;
}


protected function getCreatedNotification(): ?Notification
{
    return Notification::make()
        ->title('Inventario actualizado correctamente')
        ->body(
            $this->record->tipo === 'inventario_nuevo'
                ? 'Se reinició el inventario de todos los productos.'
                : 'El ajuste de inventario fue aplicado correctamente.'
        )
        ->success();
}



    /* =========================
     | BOTONES
     ========================= */
    protected function getFormActions(): array
    {
        return [

            // 💾 GUARDAR BORRADOR
            Action::make('guardar_borrador')
                ->label('Guardar borrador')
                ->color('gray')
                ->action(fn () => $this->saveDraft()),

            // ❌ CANCELAR
            Action::make('cancelar')
                ->label('Cancelar')
                ->color('danger')
                ->requiresConfirmation()
                ->action(fn () => $this->cancelDraft()),

            // ✅ CREAR
            $this->getCreateFormAction()
             ->label('ACTUALIZAR')
                ->requiresConfirmation()
                ->modalHeading('Confirmar inventario')
                ->modalDescription(fn () =>
                    ($this->data['tipo'] ?? null) === 'inventario_nuevo'
                        ? '⚠️ Esto borrará el inventario actual de TODOS los productos.'
                        : '¿Desea guardar este ajuste de inventario?'
                )
                ->modalSubmitActionLabel('Aceptar')
                ->modalCancelActionLabel('Cancelar'),
        ];
    }

    public static function canCreateAnother(): bool
    {
        return false;
    }

    /* =========================
     | GUARDAR BORRADOR (BD)
     ========================= */
public function saveDraft(): void
{
    DB::transaction(function () {

        $data = $this->form->getState();

        // 🔎 BUSCAR BORRADOR EXISTENTE
        $ajuste = AjusteInventario::firstOrCreate(
            [
                'empresa_id' => Auth::user()->getEmpresaActualId(),
                'usuario_id' => Auth::id(),
                'estado'     => 'borrador',
            ],
            [
                'tipo'        => $data['tipo'],
                'observacion' => $data['observacion'],
            ]
        );

        // 🔥 LIMPIAR DETALLES ANTERIORES
        $ajuste->detalles()->delete();

        foreach ($data['detalles'] ?? [] as $item) {

            if (empty($item['producto_id'])) {
                continue;
            }

            AjusteInventarioDetalle::create([
                'ajuste_inventario_id' => $ajuste->id,
                'producto_id'          => $item['producto_id'], // SIEMPRE ID REAL
                'producto_variante_id' => $item['producto_variante_id'] ?? null,
                'cantidad_anterior'    => $item['cantidad_anterior'] ?? 0,
                'cantidad_nueva'       => $item['cantidad_nueva'],
                'diferencia'           => $item['diferencia'] ?? 0,
            ]);
        }
    });

    $this->dispatch('clear-inventario-cache');

    $this->form->fill([]); // limpia UI

    $this->redirect(
        $this->getResource()::getUrl('index')
    );

    Notification::make()
        ->title('Borrador guardado correctamente')
        ->success()
        ->send();
}



    /* =========================
     | CANCELAR
     ========================= */
   public function cancelDraft(): void
{
    $this->dispatch('clear-inventario-cache');

    $this->form->fill([]); // limpia UI

    $this->redirect(
        $this->getResource()::getUrl('index')
    );
}

public function mount(): void
{
    parent::mount();

    $draftId = request()->query('draft');
    if (! $draftId) {
        return;
    }

    $draft = AjusteInventario::with('detalles.producto')
        ->where('id', $draftId)
        ->where('estado', 'borrador')
        ->where('empresa_id', auth()->user()->getEmpresaActualId())
        ->first();

    if (! $draft) {
        return;
    }

    $this->form->fill([
        'tipo'        => $draft->tipo,
        'observacion' => $draft->observacion,
        'detalles'    => $draft->detalles->map(function ($d) {

            // 👇 YA VIENE BIEN POR LA RELACIÓN
            $producto = $d->producto;

            if (! $producto) {
                return null;
            }

            return [
                // visible
                'codigo_ingresado'      => $producto->id_producto, // 10003
                'nombre_producto'       => $producto->descripcion_larga,
                'existencias_actuales'  => $producto->existencias,

                // 🔑 CLAVE: ID REAL
                 'producto_id'           => $producto->id_producto,
                 'producto_variante_id'  => $d->producto_variante_id,

                // cantidades
                'cantidad_nueva'        => $d->cantidad_nueva,
                'cantidad_anterior'     => $d->cantidad_anterior,
                'diferencia'            => $d->diferencia,
            ];
        })->filter()->values()->toArray(),
    ]);
}

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

}
