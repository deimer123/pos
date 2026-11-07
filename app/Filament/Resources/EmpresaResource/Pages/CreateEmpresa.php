<?php
namespace App\Filament\Resources\EmpresaResource\Pages;

use App\Filament\Resources\EmpresaResource;
use App\Mail\EmpresaActivacionMail;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CreateEmpresa extends CreateRecord
{
    protected static string $resource = EmpresaResource::class;

    // complementos_ids no es una columna real de "users" (es una relacion
    // muchos-a-muchos con precio_aplicado en la tabla pivote), asi que se
    // extrae en mutateFormDataBeforeCreate() y se guarda aqui para poder
    // sincronizar el pivote en afterCreate(), ya con el registro creado.
    protected array $complementosSeleccionados = [];

    // El campo "password" del formulario se dehydrata a su hash antes de
    // llegar a mutateFormDataBeforeCreate()/afterCreate(), asi que la
    // contraseña en texto plano (necesaria para el correo de activacion)
    // se guarda aparte tomandola del estado crudo del formulario.
    protected ?string $passwordTextoPlano = null;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->passwordTextoPlano = $this->form->getRawState()['password'] ?? null;

        unset($data['factus'], $data['ubl21']);

        $data['tipo_usuario'] = 'empresa';
        $data['empresa_id'] = null; // Las empresas no tienen empresa_id
        $data['activo'] = $data['activo'] ?? true;
        $data['plan_meses'] = (int) ($data['plan_meses'] ?? 3);
        $data['plan_started_at'] = $data['plan_started_at'] ?? today()->toDateString();
        $data['plan_ends_at'] = $data['plan_ends_at']
            ?? EmpresaResource::calculatePlanEndDate($data['plan_started_at'], $data['plan_meses']);
        $data['max_vendedores'] = (int) ($data['max_vendedores'] ?? 1);
        $data['max_cajeros'] = (int) ($data['max_cajeros'] ?? 1);
        $data['max_digitadores'] = (int) ($data['max_digitadores'] ?? 0);

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

    protected function afterCreate(): void
    {
        $record = $this->record;

        // Asignar rol de admin_empresa
        $record->assignRole('admin_empresa');
        EmpresaResource::saveFactusConfig($record, $this->form->getRawState()['factus'] ?? []);
        EmpresaResource::saveUbl21Config($record, $this->form->getRawState()['ubl21'] ?? []);
        EmpresaResource::sincronizarComplementos($record, $this->complementosSeleccionados);

        $this->enviarCorreoActivacion($record);

        Notification::make()
            ->title('Empresa creada exitosamente')
            ->success()
            ->send();
    }

    // La edicion Local no inicia sesion en este panel (su unica interfaz es
    // la app de escritorio activada por codigo), asi que no tiene sentido
    // mandarle un correo con login/plan -- solo se envia para Online/Hibrida.
    protected function enviarCorreoActivacion($record): void
    {
        if ($record->tipo_edicion === 'local' || blank($this->passwordTextoPlano) || blank($record->email)) {
            return;
        }

        try {
            Mail::to($record->email)->send(new EmpresaActivacionMail(
                $record,
                $this->passwordTextoPlano,
                route('filament.admin.auth.login'),
            ));
        } catch (\Throwable $exception) {
            Log::error('No se pudo enviar el correo de activacion de la empresa', [
                'empresa_id' => $record->id,
                'error' => $exception->getMessage(),
            ]);

            Notification::make()
                ->title('Empresa creada, pero no se pudo enviar el correo de activación')
                ->body($exception->getMessage())
                ->warning()
                ->send();
        }
    }

    public static function canCreateAnother(): bool
    {
        return false;
    }

}
