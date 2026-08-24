<?php

namespace App\Services;

use App\Models\OrdenMesa;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class PedidoListoNotifier
{
    public function notificarOrdenLista(OrdenMesa $orden): void
    {
        if (! config('services.webpush.public_key') || ! config('services.webpush.private_key')) {
            return;
        }

        $orden->loadMissing('mesa', 'usuario');

        $usuarios = User::where('empresa_id', $orden->empresa_id)
            ->where(function ($q) use ($orden) {
                $q->role('cajero');

                if ($orden->usuario_id) {
                    $q->orWhere('id', $orden->usuario_id);
                }
            })
            ->pluck('id');

        if ($usuarios->isEmpty()) {
            return;
        }

        $suscripciones = PushSubscription::whereIn('user_id', $usuarios)->get();

        if ($suscripciones->isEmpty()) {
            return;
        }

        $titulo = $orden->mesa ? "Mesa {$orden->mesa->nombre} lista" : 'Pedido listo';
        $cuerpo = $orden->tipo_pedido === 'domicilio'
            ? 'El pedido a domicilio #' . $orden->numero_cocina_dia . ' está listo para despachar'
            : 'Todos los platos de la mesa están listos para servir';

        $payload = json_encode([
            'title' => $titulo,
            'body' => $cuerpo,
            'url' => '/pos?modo=mesas',
            'tag' => 'pedido-listo-' . $orden->id,
        ]);

        try {
            $webPush = new WebPush([
                'VAPID' => [
                    'subject' => config('services.webpush.subject'),
                    'publicKey' => config('services.webpush.public_key'),
                    'privateKey' => config('services.webpush.private_key'),
                ],
            ]);

            foreach ($suscripciones as $sub) {
                $webPush->queueNotification(
                    Subscription::create([
                        'endpoint' => $sub->endpoint,
                        'publicKey' => $sub->public_key,
                        'authToken' => $sub->auth_token,
                        'contentEncoding' => $sub->content_encoding,
                    ]),
                    $payload
                );
            }

            foreach ($webPush->flush() as $reporte) {
                if ($reporte->isSuccess()) {
                    continue;
                }

                $status = $reporte->getResponse()?->getStatusCode();

                if (in_array($status, [404, 410], true)) {
                    PushSubscription::where('endpoint', $reporte->getEndpoint())->delete();
                }
            }
        } catch (\Throwable $e) {
            Log::warning('No se pudo enviar la notificacion push de pedido listo', [
                'orden_id' => $orden->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
