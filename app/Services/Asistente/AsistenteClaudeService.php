<?php

namespace App\Services\Asistente;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Asistente de IA para el admin_empresa (burbuja de chat flotante en el
 * panel, ver App\Livewire\AsistenteChat). Responde SOLO preguntas
 * informativas del negocio del usuario autenticado, usando "tool use" de
 * la API de Claude: el modelo no puede inventar cifras ni pedir datos de
 * otra empresa -- cada herramienta que puede llamar esta atada a un metodo
 * de AsistenteDatosEmpresaService al que SIEMPRE le inyectamos aqui el
 * empresa_id del usuario logueado, nunca un valor que venga del modelo.
 */
class AsistenteClaudeService
{
    private const MAX_TURNOS_HERRAMIENTAS = 4;

    public function __construct(
        private readonly AsistenteDatosEmpresaService $datos,
    ) {
    }

    public function preguntar(User $usuario, array $historial, string $pregunta): string
    {
        $apiKey = config('services.anthropic.api_key');

        if (blank($apiKey)) {
            return 'El asistente todavia no esta configurado (falta la API key de Anthropic). Pidele al administrador que la agregue en el servidor.';
        }

        $empresaId = $usuario->getEmpresaActualId();

        if (! $empresaId) {
            return 'No pude identificar tu empresa para consultar datos.';
        }

        $messages = $this->historialAMensajes($historial);
        $messages[] = ['role' => 'user', 'content' => $pregunta];

        for ($turno = 0; $turno < self::MAX_TURNOS_HERRAMIENTAS; $turno++) {
            $respuesta = $this->llamarClaude($apiKey, $messages);

            if (! $respuesta) {
                return 'No pude conectarme con el asistente en este momento. Intenta de nuevo en un rato.';
            }

            $bloques = $respuesta['content'] ?? [];
            $usosHerramienta = array_values(array_filter($bloques, fn ($b) => ($b['type'] ?? null) === 'tool_use'));

            if (($respuesta['stop_reason'] ?? null) !== 'tool_use' || empty($usosHerramienta)) {
                return $this->textoFinal($bloques);
            }

            $messages[] = ['role' => 'assistant', 'content' => $bloques];

            $resultados = [];
            foreach ($usosHerramienta as $uso) {
                $resultados[] = [
                    'type' => 'tool_result',
                    'tool_use_id' => $uso['id'],
                    'content' => json_encode(
                        $this->ejecutarHerramienta($empresaId, $uso['name'] ?? '', $uso['input'] ?? []),
                        JSON_UNESCAPED_UNICODE
                    ),
                ];
            }

            $messages[] = ['role' => 'user', 'content' => $resultados];
        }

        return 'Tu pregunta necesito varios pasos para resolverla y no logre terminar a tiempo. Intenta preguntar algo mas puntual.';
    }

    private function llamarClaude(string $apiKey, array $messages): ?array
    {
        try {
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
            ])
                ->timeout(30)
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => config('services.anthropic.model'),
                    'max_tokens' => 800,
                    'system' => $this->systemPrompt(),
                    'tools' => $this->definicionHerramientas(),
                    'messages' => $messages,
                ]);

            if ($response->failed()) {
                Log::warning('Asistente IA: la API de Anthropic rechazo la peticion', [
                    'status' => $response->status(),
                    'body' => $response->json() ?? $response->body(),
                ]);

                return null;
            }

            return $response->json();
        } catch (\Throwable $e) {
            Log::warning('Asistente IA: fallo la conexion con Anthropic', ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function textoFinal(array $bloques): string
    {
        $texto = collect($bloques)
            ->where('type', 'text')
            ->pluck('text')
            ->implode("\n");

        return trim($texto) !== '' ? trim($texto) : 'No tengo una respuesta para eso.';
    }

    /**
     * Historial breve para darle contexto de la conversacion -- solo texto
     * plano, nunca se reenvian bloques de tool_use/tool_result de vueltas
     * anteriores (no hace falta y complica el formato de mensajes validos).
     */
    private function historialAMensajes(array $historial): array
    {
        return collect($historial)
            ->take(-8)
            ->map(fn ($m) => ['role' => $m['role'], 'content' => $m['content']])
            ->values()
            ->all();
    }

    private function ejecutarHerramienta(int $empresaId, string $nombre, array $input): array
    {
        try {
            return match ($nombre) {
                'info_empresa' => $this->datos->infoEmpresa($empresaId),
                'resumen_ventas' => $this->datos->resumenVentas($empresaId, $input['periodo'] ?? 'hoy'),
                'ventas_en_rango' => $this->datos->ventasEnRango($empresaId, $input['desde'], $input['hasta']),
                'producto_mas_vendido' => $this->datos->productoMasVendido($empresaId, $input['periodo'] ?? 'mes', $input['limite'] ?? 5),
                'productos_stock_bajo' => $this->datos->productosStockBajo($empresaId, (float) ($input['limite'] ?? 5)),
                'facturas_pendientes_pago' => $this->datos->facturasPendientesPago($empresaId, $input['limite'] ?? 20),
                'mejores_clientes' => $this->datos->mejoresClientes($empresaId, $input['periodo'] ?? 'mes', $input['limite'] ?? 5),
                'gastos_periodo' => $this->datos->gastosPeriodo($empresaId, $input['periodo'] ?? 'mes'),
                'resumen_servicios' => $this->datos->resumenServicios($empresaId, $input['periodo'] ?? 'mes'),
                'comisiones_por_colaborador' => $this->datos->comisionesPorColaborador($empresaId, $input['periodo'] ?? 'mes', $input['rol'] ?? null, $input['limite'] ?? 15),
                'liquidaciones_pendientes' => $this->datos->liquidacionesPendientes($empresaId, $input['limite'] ?? 15),
                'resumen_ordenes_taller' => $this->datos->resumenOrdenesTaller($empresaId),
                'resumen_ordenes_servicio_tecnico' => $this->datos->resumenOrdenesServicioTecnico($empresaId),
                'resumen_hotel' => $this->datos->resumenHotel($empresaId),
                'resumen_compras' => $this->datos->resumenCompras($empresaId, $input['periodo'] ?? 'mes'),
                default => ['error' => 'Herramienta desconocida.'],
            };
        } catch (\Throwable $e) {
            Log::warning('Asistente IA: fallo ejecutando herramienta', ['herramienta' => $nombre, 'error' => $e->getMessage()]);

            return ['error' => 'No se pudo consultar ese dato en este momento.'];
        }
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
        Eres el asistente interno de un sistema de punto de venta (POS) colombiano.
        Le respondes SOLO al dueño/administrador de UNA empresa especifica sobre datos
        de SU propio negocio: ventas, productos, inventario, clientes, cartera, gastos,
        servicios, comisiones de mecanicos/tecnicos/vendedores, ordenes de taller,
        ordenes de servicio tecnico de celulares, hotel (reservas/habitaciones) y
        compras a proveedores. No todas las empresas usan todos estos modulos -- si una
        herramienta no aplica al tipo de negocio del usuario, dilo con naturalidad en
        vez de forzar una respuesta.

        Reglas estrictas:
        - Solo puedes responder preguntas informativas sobre el negocio del usuario,
          usando las herramientas disponibles para consultar datos reales. Nunca
          inventes cifras: si necesitas un dato, llama a la herramienta correspondiente.
        - Si la pregunta no tiene que ver con el negocio del usuario (temas generales,
          otras empresas, programacion, noticias, opiniones personales, etc.), responde
          amablemente que solo puedes ayudar con informacion de su negocio y no sigas.
        - No des consejos legales, contables o fiscales definitivos -- si preguntan algo
          asi, aclara que consulten a su contador.
        - Responde en español, corto y directo, con las cifras en pesos colombianos
          formateadas (ej: $1.250.000). No repitas la pregunta del usuario.
        PROMPT;
    }

    private function definicionHerramientas(): array
    {
        $periodoEnum = ['hoy', 'ayer', 'semana', 'mes', 'mes_pasado'];

        return [
            [
                'name' => 'info_empresa',
                'description' => 'Datos basicos de la empresa (nombre, NIT, tipo de negocio, telefono, direccion).',
                'input_schema' => ['type' => 'object', 'properties' => (object) []],
            ],
            [
                'name' => 'resumen_ventas',
                'description' => 'Total vendido (contado y credito) en un periodo predefinido.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'periodo' => ['type' => 'string', 'enum' => $periodoEnum],
                    ],
                    'required' => ['periodo'],
                ],
            ],
            [
                'name' => 'ventas_en_rango',
                'description' => 'Total vendido entre dos fechas especificas (formato YYYY-MM-DD).',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'desde' => ['type' => 'string', 'description' => 'Fecha inicial YYYY-MM-DD'],
                        'hasta' => ['type' => 'string', 'description' => 'Fecha final YYYY-MM-DD'],
                    ],
                    'required' => ['desde', 'hasta'],
                ],
            ],
            [
                'name' => 'producto_mas_vendido',
                'description' => 'Ranking de productos mas vendidos (por cantidad) en un periodo.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'periodo' => ['type' => 'string', 'enum' => $periodoEnum],
                        'limite' => ['type' => 'integer', 'description' => 'Cuantos productos mostrar, por defecto 5'],
                    ],
                ],
            ],
            [
                'name' => 'productos_stock_bajo',
                'description' => 'Productos con pocas existencias o agotados (existencias <= limite, por defecto 5).',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'limite' => ['type' => 'number', 'description' => 'Umbral de existencias, por defecto 5. Usa 0 para agotados.'],
                    ],
                ],
            ],
            [
                'name' => 'facturas_pendientes_pago',
                'description' => 'Facturas a credito pendientes, parciales o vencidas (cartera por cobrar).',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'limite' => ['type' => 'integer', 'description' => 'Cuantas facturas listar, por defecto 20'],
                    ],
                ],
            ],
            [
                'name' => 'mejores_clientes',
                'description' => 'Clientes que mas han comprado en un periodo, ordenados por total comprado.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'periodo' => ['type' => 'string', 'enum' => $periodoEnum],
                        'limite' => ['type' => 'integer', 'description' => 'Cuantos clientes mostrar, por defecto 5'],
                    ],
                ],
            ],
            [
                'name' => 'gastos_periodo',
                'description' => 'Total de gastos/salidas de caja registrados en un periodo.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'periodo' => ['type' => 'string', 'enum' => $periodoEnum],
                    ],
                ],
            ],
            [
                'name' => 'resumen_servicios',
                'description' => 'Total vendido en servicios (propios y de terceros) en un periodo, y la ganancia de la empresa por los servicios propios.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'periodo' => ['type' => 'string', 'enum' => $periodoEnum],
                    ],
                ],
            ],
            [
                'name' => 'comisiones_por_colaborador',
                'description' => 'Cuanto ha generado cada mecanico, tecnico, vendedor o mesero en un periodo (servicios/ventas asignadas a su nombre).',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'periodo' => ['type' => 'string', 'enum' => $periodoEnum],
                        'rol' => ['type' => 'string', 'enum' => ['mecanico', 'tecnico', 'vendedor', 'mesero'], 'description' => 'Filtra por tipo de colaborador. Si no se da, incluye todos.'],
                        'limite' => ['type' => 'integer', 'description' => 'Por defecto 15'],
                    ],
                ],
            ],
            [
                'name' => 'liquidaciones_pendientes',
                'description' => 'Liquidaciones de comisiones a mecanicos/tecnicos que todavia no se han pagado.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'limite' => ['type' => 'integer', 'description' => 'Por defecto 15'],
                    ],
                ],
            ],
            [
                'name' => 'resumen_ordenes_taller',
                'description' => 'Cuantas ordenes de taller (mecanica) hay por cada estado (pendiente, en_proceso, listo, entregado, cancelado).',
                'input_schema' => ['type' => 'object', 'properties' => (object) []],
            ],
            [
                'name' => 'resumen_ordenes_servicio_tecnico',
                'description' => 'Cuantas ordenes de servicio tecnico de celulares hay por cada estado (pendiente, en_proceso, listo, entregado, cancelado).',
                'input_schema' => ['type' => 'object', 'properties' => (object) []],
            ],
            [
                'name' => 'resumen_hotel',
                'description' => 'Reservas de hotel por estado (reservada, checkin, checkout, cancelada) y cuantas habitaciones activas hay.',
                'input_schema' => ['type' => 'object', 'properties' => (object) []],
            ],
            [
                'name' => 'resumen_compras',
                'description' => 'Total comprado a proveedores y saldo pendiente por pagar en un periodo.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'periodo' => ['type' => 'string', 'enum' => $periodoEnum],
                    ],
                ],
            ],
        ];
    }
}
