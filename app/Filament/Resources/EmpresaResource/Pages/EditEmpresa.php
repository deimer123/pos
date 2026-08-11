<?php

namespace App\Filament\Resources\EmpresaResource\Pages;

use App\Filament\Resources\EmpresaResource;
use App\Services\Factus\FactusClient;
use App\Services\Ubl21\Ubl21Client;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditEmpresa extends EditRecord
{
    protected static string $resource = EmpresaResource::class;

    // Ver CreateEmpresa::$complementosSeleccionados -- mismo motivo.
    protected array $complementosSeleccionados = [];

    protected function getHeaderActions(): array
    {
        // "Generar factura del plan" se movio a la lista de empresas
        // (accion de tabla "Facturar" en EmpresaResource::table()) -- el
        // usuario pidio poder facturar directo desde ahi, sin entrar a
        // editar. Se dejan aca solo las acciones que si necesitan estar
        // dentro de la ficha (Factus).
        return [
            Actions\Action::make('probarFactus')
                ->label('Probar conexion Factus')
                ->icon('heroicon-o-signal')
                ->action(function (): void {
                    $this->save(false, false);
                    $this->record->refresh();

                    try {
                        FactusClient::forEmpresa($this->record->configuracion)->token(true);

                        Notification::make()
                            ->title('Conexion con Factus correcta')
                            ->success()
                            ->send();
                    } catch (\Throwable $exception) {
                        Notification::make()
                            ->title('No se pudo conectar con Factus')
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Actions\Action::make('sincronizarRangosFactus')
                ->label('Sincronizar rangos')
                ->icon('heroicon-o-arrow-path')
                ->requiresConfirmation()
                ->modalHeading('Sincronizar rangos desde Factus')
                ->modalDescription('Se consultaran los rangos activos de Factus y se guardara el primer rango vigente encontrado para esta empresa.')
                ->action(function (): void {
                    $this->save(false, false);
                    $this->record->refresh();

                    try {
                        $config = $this->record->configuracion;

                        if (! $config) {
                            throw new \RuntimeException('Primero guarda la configuracion Factus de esta empresa.');
                        }

                        $client = FactusClient::forEmpresa($config);
                        $response = $client->numberingRanges([
                            'is_active' => 1,
                        ]);
                        $ranges = $this->extractRanges($response);
                        $range = collect($ranges)
                            ->first(fn (array $range) => $this->activeInvoiceRange($range))
                            ?? collect($ranges)->first(fn (array $range) => $this->activeRange($range));
                        $creditNoteRange = collect($ranges)
                            ->first(fn (array $range) => $this->activeCreditNoteRange($range));

                        if (! $range) {
                            $response = $client->numberingRanges();
                            $ranges = $this->extractRanges($response);

                            $range = collect($ranges)
                                ->first(fn (array $range) => $this->activeInvoiceRange($range))
                                ?? collect($ranges)->first(fn (array $range) => $this->activeRange($range));
                            $creditNoteRange = collect($ranges)
                                ->first(fn (array $range) => $this->activeCreditNoteRange($range));
                        }

                        if (! $range) {
                            try {
                                $dianResponse = $client->dianNumberingRanges();
                                $dianRanges = $this->extractRanges($dianResponse);
                            } catch (\Throwable) {
                                $dianRanges = [];
                            }

                            if ($dianRanges !== []) {
                                throw new \RuntimeException('Factus devolvio rangos DIAN, pero no devolvio rangos validos para crear facturas por API v1. Pide a soporte Factus el numbering_range_id compatible con /v1/bills/validate.');
                            }
                        }

                        if (! $range) {
                            $count = count($ranges);

                            throw new \RuntimeException($count > 0
                                ? "Factus devolvio {$count} rango(s), pero ninguno activo y vigente. Revisa el estado del rango en Factus/DIAN o escribe manualmente el ID del rango."
                                : 'Factus no devolvio rangos para estas credenciales. Pide a soporte Factus el ID del rango de numeracion sandbox o verifica que la resolucion este asociada al software Factus.');
                        }

                        $update = [
                            'factus_numbering_range_id' => $range['id'] ?? null,
                            'prefijo' => $range['prefix'] ?? null,
                            'rango_desde' => $range['from'] ?? null,
                            'rango_hasta' => $range['to'] ?? null,
                            'rango_actual' => $range['current'] ?? null,
                            'numero_resolucion' => $range['resolution_number'] ?? null,
                            'fecha_inicio' => $range['start_date'] ?? null,
                            'fecha_fin' => $range['end_date'] ?? null,
                            'llave' => $range['technical_key'] ?? null,
                            'expirado' => (bool) ($range['is_expired'] ?? false),
                            'factus_synced_at' => now(),
                        ];

                        if ($creditNoteRange) {
                            $update['factus_credit_note_numbering_range_id'] = $creditNoteRange['id'] ?? null;
                        }

                        $config->update($update);

                        $this->fillForm();

                        Notification::make()
                            ->title('Rango Factus sincronizado')
                            ->body('Se guardo el rango '.($range['prefix'] ?? '').' '.($range['from'] ?? '').'-'.($range['to'] ?? '').($creditNoteRange ? ' y el rango de nota credito.' : '.'))
                            ->success()
                            ->send();
                    } catch (\Throwable $exception) {
                        Notification::make()
                            ->title('No se pudieron sincronizar los rangos')
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Actions\Action::make('probarUbl21')
                ->label('Probar conexion (proveedor alterno)')
                ->icon('heroicon-o-signal')
                ->action(function (): void {
                    $this->save(false, false);
                    $this->record->refresh();

                    try {
                        $config = $this->record->configuracion;

                        if (! $config) {
                            throw new \RuntimeException('Primero guarda la configuracion del proveedor alterno de esta empresa.');
                        }

                        Ubl21Client::forEmpresa($config)->consecutivoActual(1, $config->ubl21_prefix);

                        Notification::make()
                            ->title('Conexion correcta')
                            ->success()
                            ->send();
                    } catch (\Throwable $exception) {
                        Notification::make()
                            ->title('No se pudo conectar con el proveedor alterno')
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['factus'], $data['ubl21']);

        if (! empty($data['plan_meses']) && ! empty($data['plan_started_at']) && empty($data['plan_ends_at'])) {
            $data['plan_ends_at'] = EmpresaResource::calculatePlanEndDate(
                $data['plan_started_at'],
                (int) $data['plan_meses'],
            );
        }

        $this->complementosSeleccionados = $data['complementos_ids'] ?? [];
        unset($data['complementos_ids']);

        $data['valor_plan_total'] = EmpresaResource::calcularTotalPlan(
            $data['plan_id'] ?? null,
            $this->complementosSeleccionados,
            $data['paquete_usuarios_id'] ?? null,
        );

        $data = EmpresaResource::sinPlanParaLocal($data);

        if (($data['tipo_edicion'] ?? null) === 'local') {
            $this->complementosSeleccionados = [];
        }

        return $data;
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['plan_meses'] = (int) ($data['plan_meses'] ?? 3);
        $data['plan_started_at'] = $data['plan_started_at']
            ?? $this->record->plan_started_at?->toDateString()
            ?? $this->record->created_at?->toDateString()
            ?? today()->toDateString();
        $data['plan_ends_at'] = $data['plan_ends_at']
            ?? EmpresaResource::calculatePlanEndDate(
                $data['plan_started_at'],
                $data['plan_meses'],
            );
        $data['factus'] = EmpresaResource::factusFormState($this->record);
        $data['ubl21'] = EmpresaResource::ubl21FormState($this->record);

        return $data;
    }

    protected function afterSave(): void
    {
        EmpresaResource::saveFactusConfig($this->record, $this->form->getRawState()['factus'] ?? []);
        EmpresaResource::saveUbl21Config($this->record, $this->form->getRawState()['ubl21'] ?? []);
        EmpresaResource::sincronizarComplementos($this->record, $this->complementosSeleccionados);
    }

    private function extractRanges(array $response): array
    {
        $data = $response['data'] ?? $response;

        if (isset($data['data']) && is_array($data['data'])) {
            $data = $data['data'];
        }

        if (! is_array($data)) {
            return [];
        }

        return array_values(array_filter($data, 'is_array'));
    }

    private function truthy(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 'true', 'TRUE', 'si', 'SI'], true);
    }

    private function activeRange(array $range): bool
    {
        if (blank($range['id'] ?? null)) {
            return false;
        }

        if (blank($range['from'] ?? null) && blank($range['to'] ?? null) && blank($range['resolution_number'] ?? null)) {
            return false;
        }

        $active = $range['is_active'] ?? $range['active'] ?? true;
        $expired = $range['is_expired'] ?? $range['expired'] ?? false;

        return $this->truthy($active) && ! $this->truthy($expired);
    }

    private function activeInvoiceRange(array $range): bool
    {
        return $this->activeRange($range) && in_array($this->documentCode($range), ['01', '1'], true);
    }

    private function activeCreditNoteRange(array $range): bool
    {
        return $this->activeRange($range) && $this->documentCode($range) === '22';
    }

    private function documentCode(array $range): string
    {
        $document = $range['document'] ?? $range['document_code'] ?? null;

        if (is_array($document)) {
            $document = $document['code'] ?? $document['id'] ?? null;
        }

        return str_pad((string) $document, 2, '0', STR_PAD_LEFT);
    }
}
