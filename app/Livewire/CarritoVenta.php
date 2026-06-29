<?php
// filepath: c:\laragon\www\posapp\app\Livewire\CarritoVenta.php

namespace App\Livewire;

use App\Models\Actor;
use App\Models\Gasto;
use App\Models\Prefactura;
use App\Models\Product;
use App\Models\ProductCombo;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;
use Illuminate\Validation\Rule;
use App\Models\Factura;
use App\Models\FacturaDetalle;
use App\Models\FacturaPago;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\Caja;
use App\Models\ConfiguracionEmpresa;
class CarritoVenta extends Component
{
    public ?int $mesaId = null;
    public string $ordenEstadoActual = 'abierta'; // 'abierta' | 'en_preparacion'
    public string $ordenTipoPedido   = 'mesa';    // 'mesa' | 'domicilio' | 'para_llevar'
    public float  $ordenCostoEmpaque = 0;
    public ?string $ordenDomNombre        = null;
    public ?string $ordenDomTelefono      = null;
    public ?string $ordenDomDireccion     = null;
    public float   $ordenDomCostoDomicilio  = 0;
    public float   $ordenDomCostoDesechables = 0;

    public $preciosBase               = [];
    public $carrito                   = [];
    public $mostrarModal              = false;
    public $clienteId                 = null;
    public $observaciones             = '';
    public $mostrarModalPrefacturas   = false;
    public $prefacturasDisponibles    = [];
    public $prefacturaSeleccionada    = null;
    public ?int $prefacturaCargadaId   = null;
    public $detalleSeleccionado       = [];
    public $observacionesPrefactura   = '';
    public $search                    = '';
    public $clientes                  = [];
    public $mostrarModalClientes      = false;
    public $clienteSeleccionadoNombre = null;
    public ?string $clienteDireccion  = null;
    public ?string $clienteTelefono   = null;
    public string  $ordenDomObservaciones = '';
    public $buscarCliente             = '';
    public $mostrarModalCrearCliente  = false;
    public $nuevoCliente              = [
        'tipo_documento_id'  => '',
        'identificacion'     => '',
        'nombre'             => '',
        'razon_social'       => '',
        'telefono'           => '',
        'email'              => '',
        'direccion'          => '',
        'departamento_id'    => '',
        'ciudad_id'          => '',
        'tipo_persona'       => '',
        'regimen_tributario' => '',
        'responsable_iva'    => '',
        'tipo'               => 1,
        'clasificacion'      => 'cliente',
    ];
    public $codigoCliente;
    public $prefacturaAEliminarId = null;
    public $mostrarModalRenombrar = false;
    public $uuidProductoEditando;
    public $nuevoNombreTemporal;
    public $observacionKey = 0;
    public bool $cargandoPrefactura = false;
    public $totalGeneral = 0;
    public array $noAcumulables = [10001];
    public string $tab = 'prefacturas'; // 'prefacturas' | 'facturas'
    public ?string $fDesde = null;
    public ?string $fHasta = null;
    public string $minFechaFacturas;
    public string $maxFechaFacturas;
    public $facturas = []; // colecciÃ³n de facturas
    public ?Factura $facturaSeleccionada = null;
    public $detalleFacturaSeleccionada = []; // array para tabla detalle
    public bool $mostrarModalDevolucion = false;
    public string $tipoDevolucion = 'completa'; // completa | parcial
    public array $carritoDevolucion = []; // [detId => { seleccion, producto_id, desc, pendiente, cantidad, precio, subtotal }]
    public float $totalDevolucion = 0;
    public bool $mostrarModalCartera = false;
    public string $carteraBuscar = '';
    public array $carteraClientes = [];     // [{id, nombre, saldo, facturas, max_venc}]
    public ?int $carteraClienteId = null;
    public array $carteraFacturas = [];     // [{id, fecha, vence, total, saldo, estado}]
    public float $carteraTotalCliente = 0.0;
    public bool $mostrarModalFactura = false;
    public ?int $verFacturaId = null;
    public $cargandoFacturas = false;
    public bool $mostrarModalAbono = false;
    public bool $mostrarModalMovimientoCaja = false;
    public ?int $abonoFacturaId = null;
    public string $abonoMedio = 'efectivo';
    public $verFacturaSaldo = 0;
    public float $abonoSaldo = 0.0;
    public float $abonoMonto = 0.0;
    public string $abonoTransferObs = '';              // observaciÃ³n transferencia
    public string $abonoClienteNombre = '';
    public int $carteraRefreshKey = 0;
    public bool $mostrarModalHistorial = false;
    public ?string $histDesde = null;
    public ?string $histHasta = null;
    public string $histBuscar = '';
    public bool $histSoloCliente = false;
    public array $historialFacturas = [];
    public int $historialCount = 0;
    public float $historialTotal = 0.0;
    public int $historialRefreshKey = 0;
    public ?Caja $cajaActual = null;
    public string $cajaEstado = 'cerrada'; // 'abierta' | 'cerrada'
    public $mostrarModalAbrirCaja = false;
    public $mostrarModalCerrarCaja = false;
    public $montoApertura = 0;
    public $montoCierre = 0;
    public $diferenciaCaja = 0;
    public $resumenCaja = [
    'efectivo'      => 0,
    'transferencia' => 0,
    'credito'       => 0,
    'total_contado' => 0,
    'total'         => 0,
];
    public int $uiCajaKey = 0;
    public float $abonoVence = 0;
    public array $movimientoCaja = [
        'tipo' => 'salida',
        'categoria' => 'Otros',
        'descripcion' => '',
        'monto' => 0,
        'metodo_pago' => 'Efectivo',
        'observacion' => '',
    ];

    protected function costoConIvaProducto(Product $producto): float
    {
        $costoIva = (float) ($producto->costo_iva ?? 0);

        if ($costoIva > 0) {
            return round($costoIva, 2);
        }

        $costo = (float) ($producto->precio_costo ?? 0);
        $iva = (float) ($producto->iva_venta ?? 0);

        return round($costo + ($costo * $iva / 100), 2);
    }

    protected function utilidadSobreVenta(float $precioVenta, float $costoConIva): float
    {
        if ($precioVenta <= 0) {
            return 0;
        }

        return round((($precioVenta - $costoConIva) / $precioVenta) * 100, 2);
    }

    
    
    


    
    



   
    protected $listeners = [
        'productoAgregado'             => 'agregarProducto',
        'agregarManual'                => 'agregarProductoManual',
        'borrar-prefactura-confirmada' => 'borrarPrefacturaConfirmada',
        'reiniciar-prefactura'         => 'reiniciarPrefactura',
        'mesa-enviar-cocina'           => 'mesaEnviarACocina',
        'mesa-facturar'                => 'mesaPreFacturar',
        'mesa-liberar'                 => 'mesaLiberar',
        'mesa-en-espera'               => 'mesaEnEspera',
    ];

   

    
    

    

    

  

   
    private function getEmpresaId()
{
    $user = auth()->user();

    // Si tiene empresa asignada, usarla siempre.
    if (!empty($user->empresa_id)) {
        return $user->empresa_id;
    }

    // Para empresas con rol admin_empresa, la empresa es el mismo usuario.
    if ($user->hasRole('admin_empresa')) {
        return $user->id;
    }

    return $user->id;
}

private function textoUtf8($valor): string
{
    $texto = (string) ($valor ?? '');

    if ($texto === '') {
        return '';
    }

    if (function_exists('mb_check_encoding') && mb_check_encoding($texto, 'UTF-8')) {
        return $texto;
    }

    if (function_exists('mb_convert_encoding')) {
        return mb_convert_encoding($texto, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
    }

    return iconv('Windows-1252', 'UTF-8//IGNORE', $texto) ?: '';
}

private function limpiarUtf8Array(array $datos): array
{
    foreach ($datos as $clave => $valor) {
        if (is_array($valor)) {
            $datos[$clave] = $this->limpiarUtf8Array($valor);
        } elseif (is_string($valor)) {
            $datos[$clave] = $this->textoUtf8($valor);
        }
    }

    return $datos;
}


    public function rules()
    {
        $empresaId = $this->getEmpresaId();
        
        return [
        'nuevoCliente.identificacion' => [
            'required',
            Rule::unique('actors', 'identificacion')
                ->where(function ($query) use ($empresaId) {
                    return $query->where('empresa_id', $empresaId);
                })
        ],
        'nuevoCliente.nombre' => [
            'required',
            Rule::unique('actors', 'nombre')
                ->where(function ($query) use ($empresaId) {
                    return $query->where('empresa_id', $empresaId);
                })
        ],
        'nuevoCliente.email' => [
            'required',
            'email',
            Rule::unique('actors', 'email')
                ->where(function ($query) use ($empresaId) {
                    return $query->where('empresa_id', $empresaId);
                })
        ],
            'nuevoCliente.telefono'           => 'required|numeric',
            'nuevoCliente.departamento_id'    => 'required|exists:departamentos,id',
            'nuevoCliente.ciudad_id'          => 'required|exists:ciudades,id',
            'nuevoCliente.direccion'          => 'required',
            'nuevoCliente.responsable_iva'    => 'required|in:0,1',
            'nuevoCliente.regimen_tributario' => 'required',
            'nuevoCliente.tipo_persona'       => 'required',
        ];
    }

    public function messages()
    {
        return [
            'nuevoCliente.identificacion.required' => 'Debe ingresar una identificacion.',
            'nuevoCliente.identificacion.unique'   => 'Este numero de identificacion ya esta registrado.',
            'nuevoCliente.nombre.required'         => 'Debe ingresar un nombre.',
            'nuevoCliente.nombre.unique'           => 'El nombre ya existe.',
            'nuevoCliente.email.required'          => 'Debe ingresar un correo electronico.',
            'nuevoCliente.email.email'             => 'Debe ingresar un correo valido.',
            'nuevoCliente.email.unique'            => 'Este correo ya esta registrado.',
            'nuevoCliente.telefono.required'       => 'Debe ingresar un telefono.',
            'nuevoCliente.departamento_id.required' => 'Seleccione un departamento.',
            'nuevoCliente.ciudad_id.required'      => 'Seleccione una ciudad.',
            'nuevoCliente.direccion.required'      => 'Ingrese una direccion.',
            'nuevoCliente.responsable_iva.required' => 'Indique si es responsable de IVA.',
            'nuevoCliente.regimen_tributario.required' => 'Seleccione un regimen tributario.',
            'nuevoCliente.tipo_persona.required'   => 'Seleccione un tipo de persona.',
        ];
    }




    protected function carritoCacheKey(?int $empresaId = null): string
    {
        $empresaId = $empresaId ?: $this->getEmpresaId();
        $sufijo = $this->mesaId ? '_mesa_' . $this->mesaId : '';
        return 'pos_carrito_' . $empresaId . '_' . auth()->id() . $sufijo;
    }

    protected function observacionesCacheKey(?int $empresaId = null): string
    {
        $empresaId = $empresaId ?: $this->getEmpresaId();
        $sufijo = $this->mesaId ? '_mesa_' . $this->mesaId : '';
        return 'pos_observaciones_' . $empresaId . '_' . auth()->id() . $sufijo;
    }
    protected function permiteCantidadDecimal(array $item): bool
    {
        if (array_key_exists('permite_decimal', $item)) {
            return (bool) $item['permite_decimal'];
        }

        $vendePor = strtolower(trim((string) ($item['vende_por'] ?? '')));
        $permiteFraccion = (bool) ($item['permite_fraccion'] ?? false);

        if (in_array($vendePor, ['peso', 'porcion', 'litro', 'metro', 'hora'], true)) {
            return true;
        }

        if ($permiteFraccion) {
            return true;
        }

        return (int) ($item['id_unidad_de_medida'] ?? 1) !== 1;
    }

    protected function normalizarCantidad($cantidad, bool $permiteDecimal = true): float
    {
        $valor = (float) str_replace(',', '.', (string) ($cantidad ?? 1));

        if ($valor <= 0) {
            return 1.0;
        }

        if (! $permiteDecimal) {
            return (float) max(1, (int) floor($valor));
        }

        return round($valor, 2);
    }

    protected function guardarCarritoPersistente(): void
    {
        if (! auth()->check()) {
            return;
        }

        $empresaId = $this->getEmpresaId();

        if (! empty($this->carrito)) {
            Cache::put($this->carritoCacheKey($empresaId), $this->carrito, now()->addDays(7));
        }

        if (trim((string) $this->observacionesPrefactura) !== '') {
            Cache::put($this->observacionesCacheKey($empresaId), $this->observacionesPrefactura, now()->addDays(7));
        }
    }

    protected function olvidarCarritoPersistente(): void
    {
        if (! auth()->check()) {
            return;
        }

        $empresaId = $this->getEmpresaId();
        Cache::forget($this->carritoCacheKey($empresaId));
        Cache::forget($this->observacionesCacheKey($empresaId));
    }

     public function mount()
{
    $empresaId = $this->getEmpresaId();

    // Modo mesa: cargar ítems desde la OrdenMesa activa (ignora caché general)
    if ($this->mesaId) {
        $this->cargarCarritoDesdeOrdenMesa($empresaId);
        $this->observacionesPrefactura = Cache::get($this->observacionesCacheKey($empresaId), '');
        // Cargar estado y tipo de pedido de la orden activa
        $ordenActiva = \App\Models\OrdenMesa::where('mesa_id', $this->mesaId)
            ->whereIn('estado', ['abierta', 'en_preparacion'])
            ->latest()->first();
        if ($ordenActiva) {
            $this->ordenEstadoActual        = $ordenActiva->estado ?? 'abierta';
            $this->ordenTipoPedido          = $ordenActiva->tipo_pedido ?? 'mesa';
            $this->ordenCostoEmpaque        = (float)($ordenActiva->costo_empaque ?? 0);
            $this->ordenDomNombre           = $ordenActiva->dom_nombre ?? null;
            $this->ordenDomTelefono         = $ordenActiva->dom_telefono ?? null;
            $this->ordenDomDireccion        = $ordenActiva->dom_direccion ?? null;
            $this->ordenDomObservaciones    = $ordenActiva->dom_observaciones ?? '';
            $this->ordenDomCostoDomicilio   = (float)($ordenActiva->dom_costo_domicilio ?? 0);
            $this->ordenDomCostoDesechables = (float)($ordenActiva->dom_costo_desechables ?? 0);

            // Fallback: si las nuevas columnas son 0 pero hay costo_empaque, poner todo en domicilio
            if ($this->ordenDomCostoDomicilio === 0.0 && $this->ordenDomCostoDesechables === 0.0 && $this->ordenCostoEmpaque > 0) {
                $this->ordenDomCostoDomicilio = $this->ordenCostoEmpaque;
            }

            // Restaurar cliente guardado en la orden (siempre, para sobreescribir el default)
            if ($ordenActiva->cliente_id) {
                $cliente = \App\Models\Actor::find($ordenActiva->cliente_id);
                if ($cliente) {
                    $this->clienteId                = $cliente->id;
                    $this->clienteSeleccionadoNombre = $this->textoUtf8($cliente->nombre);
                    $this->clienteDireccion         = $cliente->direccion ?? null;
                    $this->clienteTelefono          = $cliente->telefono ?? null;
                }
            }
        }
        goto fin_carga_carrito;
    }

   $carritoGuardado = [];

   if (request()->hasSession()) {
       $carritoGuardado = session('carrito_guardado', []);
   }

   if (empty($carritoGuardado) && auth()->check()) {
       $carritoGuardado = Cache::get($this->carritoCacheKey($empresaId), []);

       if (! empty($carritoGuardado) && request()->hasSession()) {
           session()->put('carrito_guardado', $carritoGuardado);
       }
   }

   if (! empty($carritoGuardado)) {
    
    foreach ($carritoGuardado as $clave => $item) {
        $codigo = $item['id_producto'] ?? $clave;

        $producto = Product::where('id_producto', $codigo)
            ->where('empresa_id', $empresaId)
            ->first();
        
        // Si no existe en BD (o es temporal), restaurar con lo que venga
        if (!$producto) {
            $this->carrito[$clave] = $item;
            continue;
        }

        $precioVenta = floatval($item['nuevo_precio'] ?? $item['precio'] ?? $producto->precio_venta1);
        $cantidad    = $this->normalizarCantidad($item['cantidad'] ?? 1, $producto->permiteCantidadDecimal());
        $precioCosto = floatval($producto->precio_costo ?? 0);
        $costoConIva = $this->costoConIvaProducto($producto);
        $descuento   = floatval($item['descuento'] ?? 0);
        $utilidad    = $this->utilidadSobreVenta($precioVenta, $costoConIva);
                
        $this->carrito[$clave] = [
            'uuid'          => (string)($item['uuid'] ?? $clave),
            'id_producto'   => $producto->id_producto,
            'nombre'        => $item['nombre'] ?? $producto->descripcion_larga, // âœ… conserva nombre guardado
            'cantidad'      => $cantidad,
            'precio'        => $precioVenta,
            'nuevo_precio'  => $precioVenta,
            'descuento'     => $descuento,
            'iva_venta'     => floatval($producto->iva_venta ?? 0),
            'utilidad1'     => $utilidad,
            'costo'         => $precioCosto,
            'costo_iva'     => $costoConIva,
            'utilidad_nueva'=> $utilidad,
            'total'         => round($precioVenta * $cantidad, 2),
            'existencias'   => (float) ($producto->existencias ?? 0),
            'porciones_receta' => (function() use ($producto, $empresaId) {
                $r = \App\Models\Receta::where('empresa_id', $empresaId)
                    ->where('product_id', $producto->id)
                    ->where('activo', true)
                    ->with('items.ingrediente')
                    ->first();
                return $r ? ['porciones' => $r->porciones_disponibles, 'unidad' => $r->unidad_rendimiento] : null;
            })(),
            'id_unidad_de_medida' => (int) ($producto->id_unidad_de_medida ?? 1),
            'vende_por'     => (string) ($producto->vende_por ?? 'unidad'),
            'permite_fraccion' => (bool) ($producto->permite_fraccion ?? false),
            'permite_decimal' => $producto->permiteCantidadDecimal(),
        ];
    }
}

if (request()->hasSession() && session()->has('observaciones_guardadas')) {
    $this->observacionesPrefactura = session('observaciones_guardadas');
} elseif (auth()->check()) {
    $this->observacionesPrefactura = Cache::get($this->observacionesCacheKey($empresaId), '');
}


    fin_carga_carrito:

    // Cargar clientes
    $this->clientes = Actor::where('tipo', 1)
        ->where('empresa_id', $empresaId)
        ->orderBy('nombre')
        ->get(['id', 'id_clip_pro', 'nombre', 'identificacion']);

    if ($this->clienteId) {
        $cliente = $this->clientes->firstWhere('id_clip_pro', $this->clienteId);
        if ($cliente) {
            $this->clienteSeleccionadoNombre = $this->textoUtf8($cliente->nombre);
        }
    }
    $this->asignarConsumidorFinalPorDefecto();
    $this->codigoCliente = Actor::max('id_clip_pro') + 1;
    $this->dispatch('limpiar-input-busqueda')->to('pos-productos');

    // Calcular total inicial después de cargar el carrito
    $this->actualizarTotales();
    $this->maxFechaFacturas = now()->toDateString();
    $this->minFechaFacturas = now()->subMonths(3)->toDateString();
    $this->fDesde = $this->maxFechaFacturas;
    $this->fHasta = $this->maxFechaFacturas;
    $this->cargarCajaActual();

    // Verificar si hay caja abierta de dÃ­as anteriores
    if ($this->cajaActual && $this->cajaActual->opened_at->format('Y-m-d') !== now()->format('Y-m-d')) {
        // Cierra automÃ¡ticamente la caja anterior
        $this->cajaActual->update([
            'monto_cierre' => $this->cajaActual->monto_apertura,
            'closed_at'    => now()->startOfDay(),
            'estado'       => 'cerrada',
        ]);
        $this->cargarCajaActual();
    }

    // Si no hay caja abierta hoy, pedir abrir caja
    $user = auth()->user();

        if (
            !$this->cajaActual &&
            (
                $user->hasRole('cajero') ||
                $user->hasRole('admin_empresa')
            )
        ) {
            $this->mostrarModalAbrirCaja = true;
        }


}

public function asignarConsumidorFinalPorDefecto()
{
    // Solo asignar si no hay cliente ya seleccionado
    if (!$this->clienteId) {
        $empresaId = $this->getEmpresaId();

        $consumidor = Actor::where('empresa_id', $empresaId)
            ->where('nombre', 'CONSUMIDOR FINAL')
            ->first();

        if ($consumidor) {
            $this->clienteId = $consumidor->id;
            $this->clienteSeleccionadoNombre = $this->textoUtf8($consumidor->nombre);
        }
    }
}

    // âœ… CORREGIR: Solo un mÃ©todo abrirModalClientes
    public function abrirModalBuscarCliente()
    {
        $empresaId = $this->getEmpresaId();
        
        $this->clientes = Actor::whereIn('tipo', [1, 2])
            ->where('empresa_id', $empresaId)
            ->orderBy('nombre')
            ->get(['id', 'id_clip_pro', 'nombre', 'identificacion']);

        $this->buscarCliente = '';
        $this->mostrarModalClientes = true;
    }

    public function abrirModalCrearCliente()
    {
          \Log::info('abrirModalCrearCliente ejecutado');
        $this->resetErrorBag();
        $this->resetValidation();

        $this->nuevoCliente = [
            'tipo_documento_id'  => 3,
            'identificacion'     => '',
            'nombre'             => '',
            'razon_social'       => '',
            'telefono'           => '',
            'email'              => '',
            'direccion'          => '',
            'departamento_id'    => '',
            'ciudad_id'          => '',
            'tipo_persona'       => '',
            'regimen_tributario' => '',
            'responsable_iva'    => '',
            'tipo'               => 1,
            'clasificacion'      => 'cliente',
        ];

        $this->mostrarModalCrearCliente = true;
    }

    public function guardarCliente()
{
    $this->resetErrorBag();
    $this->nuevoCliente['responsable_iva'] = intval($this->nuevoCliente['responsable_iva']);

    // Validaciones personalizadas
    if ($this->nuevoCliente['regimen_tributario'] === 'simplificado' && $this->nuevoCliente['responsable_iva'] == 1) {
        $this->addError('nuevoCliente.responsable_iva', 'Un regimen simplificado no puede ser responsable de IVA.');
    }

    if ($this->nuevoCliente['regimen_tributario'] === 'comun' && $this->nuevoCliente['responsable_iva'] != 1) {
        $this->addError('nuevoCliente.responsable_iva', 'Un regimen comun debe ser responsable de IVA.');
    }

    if ($this->nuevoCliente['tipo_persona'] === 'juridica' && $this->nuevoCliente['tipo_documento_id'] != 6) {
        $this->addError('nuevoCliente.tipo_documento_id', 'Una persona juridica solo puede tener NIT como tipo de documento.');
    }

    if ($this->getErrorBag()->isNotEmpty()) {
        return;
    }

    $this->validate();

    $empresaId = $this->getEmpresaId(); // Esta funciÃ³n debe estar definida en este mismo componente

    

    $cliente = Actor::create([
        'id_clip_pro'        => Actor::max('id_clip_pro') + 1,
        'empresa_id'         => $empresaId,
        'tipo_documento_id'  => $this->nuevoCliente['tipo_documento_id'],
        'identificacion'     => $this->nuevoCliente['identificacion'],
        'nombre'             => $this->nuevoCliente['nombre'],
        'razon_social'       => $this->nuevoCliente['razon_social'],
        'telefono'           => $this->nuevoCliente['telefono'],
        'email'              => $this->nuevoCliente['email'],
        'departamento_id'    => $this->nuevoCliente['departamento_id'],
        'ciudad_id'          => $this->nuevoCliente['ciudad_id'],
        'direccion'          => $this->nuevoCliente['direccion'],
        'tipo_persona'       => $this->nuevoCliente['tipo_persona'],
        'regimen_tributario' => $this->nuevoCliente['regimen_tributario'],
        'responsable_iva'    => $this->nuevoCliente['responsable_iva'],
        'clasificacion'      => 'cliente',
        'tipo'               => 1,
    ]);

    $this->clienteId                 = $cliente->id;
    $this->clienteSeleccionadoNombre = $this->textoUtf8($cliente->nombre);
    $this->clienteDireccion          = $cliente->direccion ?? null;
    $this->clienteTelefono           = $cliente->telefono ?? null;
    $this->mostrarModalCrearCliente  = false;

    // Persist client to active mesa orden immediately
    if ($this->mesaId) {
        \App\Models\OrdenMesa::where('mesa_id', $this->mesaId)
            ->whereIn('estado', ['abierta', 'en_preparacion'])
            ->update(['cliente_id' => $cliente->id]);
    }
}


    public function confirmarGuardarPrefactura()
{
    // âœ… VALIDAR CARRITO VACÃO
    if (empty($this->carrito)) {
        $this->dispatch('mostrar-carrito-vacio');
        return;
    }
    
    // âœ… VALIDAR CLIENTE SELECCIONADO  
    if (!$this->clienteId) {
        $this->dispatch('mostrar-cliente-requerido');
        return;
    }
    
    // âœ… SI TODO ESTÃ BIEN, MOSTRAR CONFIRMACIÃ“N
    $this->dispatch('confirmar-guardar-prefactura');
}

    public function seleccionarCliente($idClipPro)
{
    $empresaId = $this->getEmpresaId();

    $cliente = \App\Models\Actor::where('empresa_id', $empresaId)
        ->where('id_clip_pro', $idClipPro)
        ->first();

    if (! $cliente) {
        $this->dispatch('error', 'Cliente no encontrado');
        return;
    }

    $this->clienteId = $cliente->id; // âœ… ID real
    $this->clienteSeleccionadoNombre = $this->textoUtf8($cliente->nombre);
    $this->clienteDireccion          = $cliente->direccion ?? null;
    $this->clienteTelefono           = $cliente->telefono ?? null;

    // Persist client to active mesa orden immediately
    if ($this->mesaId) {
        \App\Models\OrdenMesa::where('mesa_id', $this->mesaId)
            ->whereIn('estado', ['abierta', 'en_preparacion'])
            ->update(['cliente_id' => $cliente->id]);
    }

    $this->mostrarModalClientes = false;

}


  public function agregarProducto($idProducto)
{
    $empresaId = $this->getEmpresaId();
    $producto = Product::where('id_producto', $idProducto)
        ->where('empresa_id', $empresaId)
        ->first();
        
    if (!$producto) {
        return;
    }

    $esNoAcumulable = in_array((int)$producto->id_producto, $this->noAcumulables, true);
    $key = $esNoAcumulable ? (string) Str::uuid() : (string) $producto->id_producto;

    if (!$esNoAcumulable && isset($this->carrito[$key])) {
        $this->carrito[$key]['cantidad'] += 1;
    } else {
        // âœ… CONVERTIR EXPLÃCITAMENTE A NÃšMEROS
        $precioVenta = floatval($producto->precio_venta1);
        $precioCosto = floatval($producto->precio_costo ?? 0);
        $costoConIva = $this->costoConIvaProducto($producto);
        $ivaVenta    = floatval($producto->iva_venta ?? 0);
        $utilidad1   = $this->utilidadSobreVenta($precioVenta, $costoConIva);
        $existencias = (float) ($producto->existencias ?? 0);
        
        $this->carrito[$key] = [
            'uuid'          => $key, // clave propia para diferenciar instancias
            'id_producto'   => $producto->id_producto,
            'nombre'        => $this->textoUtf8($producto->descripcion_larga),
            'cantidad'      => 1,
            'precio'        => $precioVenta,
            'nuevo_precio'  => $precioVenta,
            'descuento'     => 0,
            'iva_venta'     => $ivaVenta,
            'utilidad1'     => $utilidad1,
            'costo'         => $precioCosto,
            'costo_iva'     => $costoConIva,
            'utilidad_nueva'=> $utilidad1,
            'total'         => $precioVenta,
            'existencias'   => $existencias,
            'porciones_receta' => (function() use ($producto, $empresaId) {
                $r = \App\Models\Receta::where('empresa_id', $empresaId)
                    ->where('product_id', $producto->id)
                    ->where('activo', true)
                    ->with('items.ingrediente')
                    ->first();
                return $r ? ['porciones' => $r->porciones_disponibles, 'unidad' => $r->unidad_rendimiento] : null;
            })(),
            'id_unidad_de_medida' => (int) ($producto->id_unidad_de_medida ?? 1),
            'vende_por'     => (string) ($producto->vende_por ?? 'unidad'),
            'permite_fraccion' => (bool) ($producto->permite_fraccion ?? false),
            'permite_decimal' => $producto->permiteCantidadDecimal(),
            'combo_activo'  => null,
            'precio_editado_manual' => false,
        ];
    }
    $this->actualizarTotales();

    // En modo mesa: guardar inmediatamente en OrdenMesa
    if ($this->mesaId) {
        $this->guardarProductoEnOrdenMesa($producto, $this->carrito[$key]['cantidad']);
    }
}
    public function agregarProductoAlCarrito($idProducto)
    {
        $this->agregarProducto($idProducto);
        $this->dispatch('limpiar-input-busqueda')->to('pos-productos');
    }

    private function guardarProductoEnOrdenMesa(\App\Models\Product $producto, float $cantidad): void
    {
        $empresaId = $this->getEmpresaId();
        $orden = $this->obtenerOrdenMesaActiva($empresaId);

        $existente = \App\Models\OrdenMesaItem::where('orden_mesa_id', $orden->id)
            ->where('product_id', $producto->id)
            ->whereIn('estado_cocina', ['pendiente', 'enviado', 'preparando'])
            ->orderByDesc('id')
            ->first();

        if ($existente && $existente->estado_cocina === 'pendiente') {
            // Sumar al ítem pendiente existente
            $existente->cantidad  = $cantidad;
            $existente->subtotal  = round($existente->precio_unitario * $cantidad, 2);
            $existente->save();
        } else {
            // Crear nuevo ítem pendiente
            \App\Models\OrdenMesaItem::create([
                'empresa_id'      => $empresaId,
                'orden_mesa_id'   => $orden->id,
                'product_id'      => $producto->id,
                'cantidad'        => $cantidad,
                'precio_unitario' => (float) $producto->precio_venta1,
                'subtotal'        => round((float) $producto->precio_venta1 * $cantidad, 2),
                'estado_cocina'   => 'pendiente',
                'requiere_cocina' => true,
            ]);
        }

        \App\Models\Mesa::where('id', $this->mesaId)->update(['estado' => 'ocupada']);
        $this->recalcularTotalOrden($orden->id);
    }

    // âœ… MÃ‰TODO PARA AGREGAR PRODUCTO MANUAL
    public function agregarProductoManual($data)
{
    if (!is_array($data)) {
     
        return;
    }

    $uuid = (string) Str::uuid();
    
    // âœ… CONVERTIR EXPLÃCITAMENTE A NÃšMEROS
    $precio = floatval($data['precio']);
    
    $this->carrito[$uuid] = [
        'uuid'         => $uuid,
        'id_producto'  => $data['codigo'],
        'nombre'       => $this->textoUtf8($data['nombre'] ?? ''),
        'precio'       => $precio,
        'cantidad'     => 1,
        'total'        => $precio, // âœ… CONVERSIÃ“N SEGURA
        'costo'        => 0,
        'nuevo_precio' => $precio,
        'descuento'    => 0,
        'existencias'  => 0,
        'id_unidad_de_medida' => 1,
        'vende_por'    => 'unidad',
        'permite_fraccion' => false,
        'permite_decimal' => false,
        'temporal'     => true,
    ];

    $this->actualizarTotales();
}

    public function eliminarProductoDelCarrito($data)
    {
        $uuid = $data['uuid'] ?? $data;
        
        foreach ($this->carrito as $index => $item) {
            if (($item['uuid'] ?? null) === $uuid || $item['id_producto'] == $uuid) {
                unset($this->carrito[$index]);
                break;
            }
        }
        
        $this->actualizarTotales();
    }

    public function eliminarDelCarrito($uuid)
{
    foreach ($this->carrito as $index => $item) {
        $itemUuid = (string) ($item['uuid'] ?? $item['id_producto'] ?? $index);
        $itemIdProducto = (string) ($item['id_producto'] ?? '');
        $indexStr = (string) $index;

        if ($itemUuid === (string)$uuid || $itemIdProducto === (string)$uuid || $indexStr === (string)$uuid) {
            // En modo mesa: si el ítem estaba pendiente en BD, eliminarlo
            if ($this->mesaId && ! empty($item['enviado_cocina'])) {
                // Ya enviado a cocina, no se puede eliminar desde aquí
            } elseif ($this->mesaId) {
                $empresaId = $this->getEmpresaId();
                $orden = \App\Models\OrdenMesa::where('empresa_id', $empresaId)
                    ->where('mesa_id', $this->mesaId)
                    ->whereIn('estado', ['abierta', 'en_preparacion'])
                    ->latest()->first();
                if ($orden) {
                    $producto = \App\Models\Product::where('id_producto', $item['id_producto'])
                        ->where('empresa_id', $empresaId)->first();
                    if ($producto) {
                        \App\Models\OrdenMesaItem::where('orden_mesa_id', $orden->id)
                            ->where('product_id', $producto->id)
                            ->where('estado_cocina', 'pendiente')
                            ->delete();
                        $this->recalcularTotalOrden($orden->id);
                    }
                }
            }
            unset($this->carrito[$index]);
            break;
        }
    }

    $this->actualizarTotales();
}
public function updatedCarrito($value, $key)
{
    // Detectar si cambiÃ³ la cantidad de algÃºn producto
    if (strpos($key, '.cantidad') !== false) {
        // Obtener el ID del producto desde el key
        $productId = str_replace('.cantidad', '', $key);
        
        if (isset($this->carrito[$productId])) {
            $cantidad = $this->normalizarCantidad($value, $this->permiteCantidadDecimal($this->carrito[$productId]));
            $nuevoPrecio = isset($this->carrito[$productId]['nuevo_precio']) 
                ? floatval($this->carrito[$productId]['nuevo_precio']) 
                : floatval($this->carrito[$productId]['precio']);
            
            // âœ… RECALCULAR SUBTOTAL CON EL PRECIO CORRECTO
            $this->carrito[$productId]['cantidad'] = $cantidad;
            $this->carrito[$productId]['total'] = round($nuevoPrecio * $cantidad, 2);
            
           
        }
        
        $this->calcularTotalGeneral();
    }
}

    

    public function confirmarLimpiarCarrito()
{
    $this->dispatch('confirmar-limpiar-carrito');
}

public function limpiarCarrito()
{
    $this->eliminarPrefacturaCargada();

    $this->carrito = [];
    $this->totalGeneral = 0;
    $this->vendedorPrefacturaId = null;
    $this->prefacturaCargadaId = null;

    $this->reset([
        'observacionesPrefactura',
        'prefacturaSeleccionada',
        'detalleSeleccionado',
        'clienteId', // âœ… LIMPIAR CLIENTE
        'clienteSeleccionadoNombre', // âœ… LIMPIAR NOMBRE
        'clienteDireccion',
        'clienteTelefono',
        'ordenDomObservaciones',
    ]);

    $this->observacionKey++; // âœ… Forzar re-render del textarea
    session()->forget('carrito_guardado');
    session()->forget('observaciones_guardadas');
    $this->olvidarCarritoPersistente();

    $this->dispatch('limpiar-carrito-en-cache');

    // âœ… Volver a asignar consumidor final
    $this->asignarConsumidorFinalPorDefecto();
}
    public function abrirModalEditar()
{
    $empresaId = $this->getEmpresaId();
    
    foreach ($this->carrito as $idClave => $item) {
        $esTemporal = ($item['temporal'] ?? false)
            || in_array((int)($item['id_producto'] ?? 0), $this->noAcumulables, true)
            || !is_numeric($idClave);

        if (!$esTemporal) {
            $producto = Product::where('id_producto', $idClave)
                ->where('empresa_id', $empresaId)
                ->first();
            $this->preciosBase[$idClave] = $producto?->precio_venta1 ?? ($item['precio'] ?? 0);
        } else {
            $this->preciosBase[$idClave] = $item['precio'] ?? 0;
        }
    }

    $this->mostrarModal = true;
}

   public function actualizarTotales()
{
    $empresaId = $this->getEmpresaId();

    foreach ($this->carrito as $id => $item) {
        $cantidad = $this->normalizarCantidad($item['cantidad'] ?? 1, $this->permiteCantidadDecimal($item));

        // Aplicar combo si corresponde (solo si el usuario no editó el precio manualmente)
        $precioBase = floatval($item['precio']);
        $combo = $this->obtenerComboActivo($item['id_producto'] ?? null, $cantidad, $empresaId);

        if ($combo && ! ($item['precio_editado_manual'] ?? false)) {
            $nuevoPrecio = $combo['precio_unitario_combo'];
            $this->carrito[$id]['nuevo_precio'] = $nuevoPrecio;
            $this->carrito[$id]['combo_activo'] = $combo['nombre'] ?? ('x' . (int)$combo['cantidad_minima']);
        } else {
            // Sin combo: restaurar precio base si venía de combo anterior
            if (isset($this->carrito[$id]['combo_activo']) && ! ($item['precio_editado_manual'] ?? false)) {
                $this->carrito[$id]['nuevo_precio'] = $precioBase;
                $this->carrito[$id]['combo_activo'] = null;
            }
            $nuevoPrecio = isset($item['nuevo_precio']) ? floatval($item['nuevo_precio']) : $precioBase;
        }

        $costo = floatval($item['costo']);
        $costoConIva = isset($item['costo_iva'])
            ? floatval($item['costo_iva'])
            : round($costo + ($costo * floatval($item['iva_venta'] ?? 0) / 100), 2);

        $subtotal = round($nuevoPrecio * $cantidad, 2);
        $utilidad_nueva = $this->utilidadSobreVenta($nuevoPrecio, $costoConIva);

        $this->carrito[$id]['utilidad_nueva'] = $utilidad_nueva;
        $this->carrito[$id]['total'] = $subtotal;

        if (!isset($this->carrito[$id]['nuevo_precio'])) {
            $this->carrito[$id]['nuevo_precio'] = $nuevoPrecio;
        }
    }

    $this->calcularTotalGeneral();
    $this->dispatch('guardar-carrito-en-cache', $this->carrito);
}

protected function obtenerComboActivo(?string $idProducto, float $cantidad, int $empresaId): ?array
{
    if (! $idProducto) return null;

    $producto = Product::where('id_producto', $idProducto)
        ->where('empresa_id', $empresaId)
        ->first();

    if (! $producto) return null;

    // El combo que aplica es el de mayor cantidad_minima que no supere la cantidad actual
    $combo = ProductCombo::where('product_id', $producto->id)
        ->where('empresa_id', $empresaId)
        ->where('activo', true)
        ->where('cantidad_minima', '<=', $cantidad)
        ->orderBy('cantidad_minima', 'desc')
        ->first();

    return $combo ? $combo->toArray() : null;
}
public function recalcularPorDescuento($id, $descuento)
{
    if (isset($this->carrito[$id])) {
        $precioBase = floatval($this->carrito[$id]['precio']);
        $nuevoPrecio = $precioBase * (1 + floatval($descuento) / 100);
        
        $this->carrito[$id]['nuevo_precio'] = round($nuevoPrecio, 2);
        $this->carrito[$id]['descuento'] = $descuento;
        
        $this->actualizarTotales();
    }
}

// âœ… MÃ‰TODO SEPARADO PARA ACTUALIZAR CUANDO SE MODIFICA PRECIO DIRECTAMENTE
public function recalcularPorPrecio($id, $nuevoPrecio)
{
    if (isset($this->carrito[$id])) {
        $precioBase = floatval($this->carrito[$id]['precio']);
        $descuento = $precioBase > 0 ? round((($nuevoPrecio / $precioBase) - 1) * 100, 2) : 0;
        
        $this->carrito[$id]['nuevo_precio'] = round((float) $nuevoPrecio, 2);
        $this->carrito[$id]['descuento'] = $descuento;
        $this->carrito[$id]['precio_editado_manual'] = true;
        $this->carrito[$id]['combo_activo'] = null;
        
        $this->actualizarTotales();
    }
}

public function imprimirPrefactura()
{
    if (! $this->prefacturaSeleccionada) {
        $this->dispatch('error', 'No hay prefactura seleccionada.');
        return;
    }

    return redirect()->route('prefactura.imprimir', ['id' => $this->prefacturaSeleccionada->id]);
}
public function aplicarCambiosModal($cambios)
{
  
    
    foreach ($cambios as $id => $datos) {
        if (isset($this->carrito[$id])) {
            // âœ… APLICAR DIRECTAMENTE LOS VALORES DEL MODAL
            $this->carrito[$id]['nuevo_precio'] = round((float) $datos['nuevo_precio'], 2);
            $this->carrito[$id]['descuento'] = floatval($datos['descuento']);
            
            // âœ… RECALCULAR SUBTOTAL INMEDIATAMENTE
            $cantidad = $this->normalizarCantidad($this->carrito[$id]['cantidad'] ?? 1, $this->permiteCantidadDecimal($this->carrito[$id]));
            $nuevoPrecio = round((float) $datos['nuevo_precio'], 2);
            $costo = floatval($this->carrito[$id]['costo']);
            $costoConIva = isset($this->carrito[$id]['costo_iva'])
                ? floatval($this->carrito[$id]['costo_iva'])
                : round($costo + ($costo * floatval($this->carrito[$id]['iva_venta'] ?? 0) / 100), 2);
            
            // Calcular nueva utilidad
            $utilidad_nueva = $this->utilidadSobreVenta($nuevoPrecio, $costoConIva);
            
            // âœ… ACTUALIZAR VALORES EN EL CARRITO
            $this->carrito[$id]['utilidad_nueva'] = $utilidad_nueva;
            $this->carrito[$id]['total'] = round($nuevoPrecio * $cantidad, 2);
            
           
        }
    }
    
    // âœ… RECALCULAR TOTAL GENERAL
    $this->calcularTotalGeneral();
    
    // Cerrar modal
    $this->mostrarModal = false;
    
    // Guardar en cache
    $this->dispatch('guardar-carrito-en-cache', $this->carrito);
    
   
}
public function calcularTotalGeneral()
{
    $this->totalGeneral = 0;
    
    foreach ($this->carrito as $item) {
        $this->totalGeneral += floatval($item['total'] ?? 0);
    }
    
    return $this->totalGeneral;
    $this->dispatch('guardar-carrito-en-cache', $this->carrito);
session()->put('observaciones_guardadas', $this->observacionesPrefactura);
    $this->guardarCarritoPersistente();

    // âœ… AGREGAR ESTA LÃNEA
}



    #[On('guardar-prefactura-confirmada')]
public function guardarPrefacturaConfirmada()
{
    $empresaId = $this->getEmpresaId();

   

    if (! $this->clienteId) {
        $this->dispatch('error', 'Debe seleccionar un cliente antes de guardar la prefactura.');
        return;
    }

    if (collect($this->carrito)->filter(fn($item) => $item['cantidad'] > 0)->isEmpty()) {
        $this->dispatch('error', 'El carrito estÃ¡ vacÃ­o. Agregue productos antes de guardar.');
        return;
    }

    $cliente = Actor::where('empresa_id', $empresaId)
        ->where('id', $this->clienteId)
        ->first();

    if (! $cliente) {
        $this->dispatch('error', 'Cliente no encontrado en esta empresa.');
        return;
    }

    try {
        if ($this->prefacturaCargadaId) {
            $prefactura = Prefactura::where('empresa_id', $empresaId)
                ->where('id', $this->prefacturaCargadaId)
                ->first();

            if (! $prefactura) {
                $this->prefacturaCargadaId = null;
                $this->dispatch('error', 'La prefactura cargada ya no existe. Intente guardarla nuevamente.');
                return;
            }

            DB::transaction(function () use ($prefactura, $empresaId) {
                $prefactura->update([
                    'cliente_id'    => $this->clienteId,
                    'cajero_id'     => null,
                    'observaciones' => $this->observacionesPrefactura ?? '',
                    'estado'        => 'borrador',
                ]);

                $prefactura->productos()->delete();

                foreach ($this->carrito as $item) {
                    $precioUnitario = $item['nuevo_precio'] ?? $item['precio'];
                    $cantidad = $this->normalizarCantidad($item['cantidad'] ?? 1, $this->permiteCantidadDecimal($item));
                    $subtotal = round($precioUnitario * $cantidad, 2);

                    $prefactura->productos()->create([
                        'producto_id'       => $item['id_producto'],
                        'cantidad'          => $cantidad,
                        'precio_unitario'   => $precioUnitario,
                        'subtotal'          => $subtotal,
                        'descripcion_larga' => $item['nombre'],
                        'descuento'         => $item['descuento'] ?? 0,
                        'empresa_id'        => $empresaId,
                    ]);
                }
            });

            $this->reset(['carrito', 'observacionesPrefactura', 'clienteId', 'clienteSeleccionadoNombre']);
            $this->totalGeneral = 0;
            $this->prefacturaCargadaId = null;
            $this->vendedorPrefacturaId = null;
            $this->observacionKey++;
            session()->forget('carrito_guardado');
            session()->forget('observaciones_guardadas');
            $this->olvidarCarritoPersistente();
            $this->asignarConsumidorFinalPorDefecto();

            session()->flash('success', 'Prefactura actualizada correctamente');
            $this->dispatch('prefactura-guardada-limpia-campo');
            return;
        }

        $prefactura = Prefactura::create([
            'empresa_id'    => $empresaId,
            'cliente_id'    => $this->clienteId,
             // ðŸ'¨â€ðŸ'¼ vendedor que creÃ³ prefactura
            'vendedor_id'   => auth()->id(),

            // ðŸ'° aÃºn no se factura
            'cajero_id'     => null,
            'observaciones' => $this->observacionesPrefactura ?? '',
            'estado'        => 'borrador',
        ]);

        foreach ($this->carrito as $item) {
            $precioUnitario = $item['nuevo_precio'] ?? $item['precio'];
            $cantidad = $this->normalizarCantidad($item['cantidad'] ?? 1, $this->permiteCantidadDecimal($item));
            $subtotal = round($precioUnitario * $cantidad, 2);

            $prefactura->productos()->create([
                'producto_id'       => $item['id_producto'],
                'cantidad'          => $cantidad,
                'precio_unitario'   => $precioUnitario,
                'subtotal'          => $subtotal,
                'descripcion_larga' => $item['nombre'],
                'descuento'         => $item['descuento'] ?? 0,
                'empresa_id'        => $empresaId,
            ]);
        }

        // âœ… Limpieza completa antes de asignar nuevo cliente
        $this->reset(['carrito', 'observacionesPrefactura', 'clienteId', 'clienteSeleccionadoNombre']);
        $this->totalGeneral = 0; 
        $this->observacionKey++; 
        session()->forget('carrito_guardado');
        session()->forget('observaciones_guardadas');
        $this->olvidarCarritoPersistente();

        // âœ… Ahora sÃ­: asignar "CONSUMIDOR FINAL"
        $this->asignarConsumidorFinalPorDefecto();

        session()->flash('success', 'âœ… Prefactura guardada correctamente');
        $this->dispatch('prefactura-guardada-limpia-campo');
    } catch (\Exception $e) {
        $this->dispatch('error', 'Error al guardar la prefactura: ' . $e->getMessage());
    }
}

    public function verPrefacturas()
    {
        $empresaId = $this->getEmpresaId();
        
        $user = auth()->user();

        $query = Prefactura::with(['cliente', 'productos'])
            ->where('empresa_id', $this->getEmpresaId())
            ->where('estado', 'borrador');


        // ðŸ'¨â€ðŸ'¼ SOLO vendedor
        if (
            $user->hasRole('vendedor') &&
            ! $user->hasAnyRole(['cajero', 'admin_empresa'])
        ) {
            $query->where('vendedor_id', $user->id);
        }

        $this->prefacturasDisponibles = $query
            ->latest()
            ->get();

        $this->mostrarModalPrefacturas = true;
        if ($this->mesaId) {
            $this->tab = 'facturas';
        }
    }

    public function seleccionarPrefactura($id)
    {
        if ($this->cargandoPrefactura) {
            return;
        }

        $this->cargandoPrefactura = true;
        $empresaId = $this->getEmpresaId();

        $user = auth()->user();

        if ($this->prefacturaSeleccionada && $this->prefacturaSeleccionada->id === $id) {
            $this->prefacturaSeleccionada  = null;
            $this->detalleSeleccionado     = [];
            $this->observacionesPrefactura = '';
            $this->cargandoPrefactura      = false;
            return;
        }

        $query = Prefactura::with('productos.producto', 'cliente')
            ->where('id', $id)
            ->where('empresa_id', $empresaId);


        // ðŸ'¨â€ðŸ'¼ SOLO vendedor
        if (
            $user->hasRole('vendedor') &&
            ! $user->hasAnyRole(['cajero', 'admin_empresa'])
        ) {
            $query->where('vendedor_id', $user->id);
        }

$prefactura = $query->first();

        if (!$prefactura) {
            $this->prefacturaSeleccionada  = null;
            $this->detalleSeleccionado     = [];
            $this->observacionesPrefactura = '';
            $this->cargandoPrefactura      = false;
            return;
        }

        $this->prefacturaSeleccionada = $prefactura;

        $this->detalleSeleccionado = $prefactura->productos->map(function ($item) {
            return [
                'producto'          => $item->producto,
                'producto_id'       => $item->producto_id,
                'descripcion_larga' => $item->descripcion_larga ?? optional($item->producto)->descripcion_larga ?? '(eliminado)',
                'cantidad'          => $item->cantidad,
                'precio_unitario'   => $item->precio_unitario,
                'subtotal'          => $item->subtotal,
            ];
        })->toArray();

        $this->observacionesPrefactura = $prefactura->observaciones ?? '';
        $this->observacionKey++;
        $this->cargandoPrefactura = false;
    }

    public function cargarPrefacturaAlCarrito($id)
{
    if ($this->cargandoPrefactura) return;

    $this->cargandoPrefactura = true;
    $empresaId = $this->getEmpresaId();

    $prefactura = Prefactura::with(['productos', 'cliente'])
        ->where('id', $id)
        ->where('empresa_id', $empresaId)
        ->first();

       

    if (! $prefactura) {
        $this->cargandoPrefactura = false;
        return;
    }

    $this->carrito = [];
    $this->prefacturaCargadaId = $prefactura->id;
    $this->vendedorPrefacturaId = $prefactura->vendedor_id;
    $prefactura->update(['estado' => 'en_edicion']);

    foreach ($prefactura->productos as $item) {
    $producto = Product::where('id_producto', $item->producto_id)
        ->where('empresa_id', $empresaId)
        ->first();

    if (! $producto) continue;

    $esNoAcumulable = in_array((int)$item->producto_id, $this->noAcumulables, true);
    $key = $esNoAcumulable ? (string) Str::uuid() : (string) $item->producto_id;
    $precioVenta = (float) $item->precio_unitario;
    $costoConIva = $this->costoConIvaProducto($producto);
    $utilidad = $this->utilidadSobreVenta($precioVenta, $costoConIva);

    $this->carrito[$key] = [
        'uuid'          => $key,
        'id_producto'   => $item->producto_id,
        'nombre'        => $item->descripcion_larga, // âœ… conserva nombre de la instancia
        'cantidad'      => $item->cantidad,
        'precio'        => $precioVenta,    // âœ… conserva precio de la instancia
        'nuevo_precio'  => $precioVenta,
        'descuento'     => $item->descuento ?? 0,
        'iva_venta'     => $producto->iva_venta ?? 0,
        'utilidad1'     => $utilidad,
        'costo'         => $producto->precio_costo ?? 0,
        'costo_iva'     => $costoConIva,
        'utilidad_nueva'=> $utilidad,
        'total'         => $item->subtotal,
        'existencias'   => $producto->existencias ?? 0,
        'id_unidad_de_medida' => (int) ($producto->id_unidad_de_medida ?? 1),
        'vende_por'     => (string) ($producto->vende_por ?? 'unidad'),
        'permite_fraccion' => (bool) ($producto->permite_fraccion ?? false),
        'permite_decimal' => $producto->permiteCantidadDecimal(),
    ];
}

    if ($prefactura->cliente) {
        $this->clienteId = $prefactura->cliente->id; // âœ… ID real
        $this->clienteSeleccionadoNombre = $prefactura->cliente->nombre;
    } else {
        $this->clienteId = null;
        $this->clienteSeleccionadoNombre = 'Sin cliente';
    }

    $this->observacionesPrefactura = $prefactura->observaciones;
    $this->observacionKey++;

    $this->prefacturaSeleccionada = null;
    $this->detalleSeleccionado = [];

    $this->prefacturasDisponibles = Prefactura::with(['cliente', 'productos'])
        ->where('empresa_id', $empresaId)
        ->where('estado', 'borrador')
        ->latest()
        ->get();

    $this->cargandoPrefactura = false;
    $this->mostrarModalPrefacturas = false;

$this->calcularTotalGeneral(); // âœ… RECALCULAR TOTAL DESPUÃ‰S DE CARGAR
$this->dispatch('guardar-carrito-en-cache', $this->carrito); // âœ… GUARDAR EN CACHE
    
}

    public function render()
{
    $empresaId = $this->getEmpresaId();
    $busqueda = '%' . $this->buscarCliente . '%';

    $this->clientes = Actor::query()
        ->whereIn('tipo', [1, 2])
        ->where('empresa_id', $empresaId)
        ->when($this->buscarCliente, function ($query) use ($busqueda) {
            $query->where(function ($q) use ($busqueda) {
                $q->where('nombre', 'like', $busqueda)
                    ->orWhere('identificacion', 'like', $busqueda);
            });
        })
        ->orderBy('nombre')
        ->get(['id', 'id_clip_pro', 'nombre', 'identificacion']);

    $carrito = $this->limpiarUtf8Array($this->carrito);
    $observacionesPrefactura = $this->textoUtf8($this->observacionesPrefactura);

    foreach ($carrito as $id => &$item) {
        $item['cantidad'] = $this->normalizarCantidad($item['cantidad'] ?? 1, $this->permiteCantidadDecimal($item));

        if (!isset($item['total']) || !isset($item['nuevo_precio'])) {
            $precio = isset($item['nuevo_precio']) ? floatval($item['nuevo_precio']) : floatval($item['precio']);
            $cantidad = $this->normalizarCantidad($item['cantidad'] ?? 1, $this->permiteCantidadDecimal($item));
            $item['total'] = round($precio * $cantidad, 2);
        }
    }
    unset($item);

    session()->put('carrito_guardado', $carrito);
    session()->put('observaciones_guardadas', $observacionesPrefactura);
    $this->guardarCarritoPersistente();

    $totalGeneral = 0;
    $itemsActivos = 0;
    foreach ($carrito as $item) {
        $totalGeneral += floatval($item['total'] ?? 0);
    }

    return view('livewire.carrito-venta', [
        'carrito'                 => $carrito,
        'totalGeneral'            => $totalGeneral,
        'itemsActivos'            => $itemsActivos,
        'creditoInfo'             => $this->clienteCreditoInfo,
        'mostrarModal'            => $this->mostrarModal,
        'mostrarModalPrefacturas' => $this->mostrarModalPrefacturas,
        'prefacturas'             => $this->prefacturasDisponibles,
        'prefacturaSeleccionada'  => $this->prefacturaSeleccionada,
        'detallesPrefactura'      => $this->detalleSeleccionado,
        'observacionesPrefactura' => $observacionesPrefactura,
        'preciosBase'             => $this->preciosBase,
        'cajaEstado'              => $this->cajaEstado,
        'cajaActual'              => $this->cajaActual,
    ]);
}

    // âœ… MÃ‰TODOS ADICIONALES NECESARIOS
    public function abrirModalRenombrar($uuid)
    {
        $this->uuidProductoEditando  = $uuid;
        $this->nuevoNombreTemporal   = $this->textoUtf8($this->carrito[$uuid]['nombre'] ?? '');
        $this->mostrarModalRenombrar = true;
    }

    public function guardarNuevoNombre()
    {
        if ($this->uuidProductoEditando && isset($this->carrito[$this->uuidProductoEditando])) {
            $this->carrito[$this->uuidProductoEditando]['nombre'] = $this->textoUtf8($this->nuevoNombreTemporal);
        }

        $this->cerrarModalRenombrar();
    }

    public function cerrarModalRenombrar()
    {
        $this->mostrarModalRenombrar = false;
        $this->uuidProductoEditando  = null;
        $this->nuevoNombreTemporal   = '';
    }

    public function cerrarModalCrearCliente()
    {
        $this->mostrarModalCrearCliente = false;
        $this->resetErrorBag();
        $this->resetValidation();
    }

    #[On('reiniciar-prefactura')]
    public function reiniciarPrefactura()
    {
        $this->prefacturaSeleccionada  = null;
        $this->detalleSeleccionado     = [];
        $this->observacionesPrefactura = '';
        $this->prefacturaCargadaId     = null;
        $this->vendedorPrefacturaId    = null;
    }

    private function eliminarPrefacturaCargada(): void
    {
        if (! $this->prefacturaCargadaId) {
            return;
        }

        $prefactura = Prefactura::with('productos')->find($this->prefacturaCargadaId);
        if ($prefactura) {
            $prefactura->productos()->delete();
            $prefactura->delete();
        }

        $this->prefacturaCargadaId = null;
    }

   #[On('borrar-prefactura-confirmada')]
public function borrarPrefacturaConfirmada()
{
    if (!$this->prefacturaSeleccionada?->id) return;

    $empresaId = $this->getEmpresaId();

    $prefactura = Prefactura::where('id', $this->prefacturaSeleccionada->id)
        ->where('empresa_id', $empresaId)
        ->first();

    if ($prefactura) {
        $prefactura->productos()->delete();
        $prefactura->delete();
    }

    // âœ… LIMPIAR TODO DESPUÃ‰S DE BORRAR
    $this->reset([
        'prefacturaSeleccionada',
        'detalleSeleccionado', 
        'observacionesPrefactura', // âœ… LIMPIAR OBSERVACIONES
        'prefacturaAEliminarId'
    ]);
    
    $this->observacionKey++; // âœ… FORZAR RE-RENDER DEL TEXTAREA
    $this->calcularTotalGeneral(); // âœ… RECALCULAR TOTAL

    $this->prefacturasDisponibles = Prefactura::with(['cliente', 'productos'])
        ->where('empresa_id', $empresaId)
        ->where('estado', 'borrador')
        ->latest()
        ->get();

    $this->mostrarModalPrefacturas = false;

    $this->dispatch('prefactura-borrada');
    
}

    public function confirmarBorrarPrefactura($id)
    {
        $this->prefacturaAEliminarId = $id;
        $this->dispatch('confirmar-borrar-prefactura');
    }

public function confirmarFacturar()
    {
        if (
    !auth()->user()->hasRole('cajero') &&
    !auth()->user()->hasRole('admin_empresa')
) {
    abort(403);
}

        // validar caja primero
        if (! $this->verificarCajaAbierta()) return;

        if (empty($this->carrito)) { $this->dispatch('error','Debe agregar productos al carrito antes de facturar.'); return; }
        if (!$this->clienteId) {
            $this->clienteId = $this->getConsumidorFinalId($this->getEmpresaId());
        }
        if (!$this->clienteId) { $this->dispatch('error','Falta CONSUMIDOR FINAL.'); return; }
        $totalActual = $this->calcularTotalGeneral();
        $nombreCliente = $this->clienteSeleccionadoNombre ?: 'CONSUMIDOR FINAL';

        // Si hay mesa, usar los datos ya cargados en las propiedades reactivas
        $mesaId       = $this->mesaId ?? null;
        $tipoPedidoOrden = $mesaId ? $this->ordenTipoPedido : 'local';
        // facturas.tipo_pedido ENUM: local | domicilio | para_llevar ('mesa' no es válido)
        $tipoPedidoFactura = $tipoPedidoOrden === 'mesa' ? 'local' : $tipoPedidoOrden;
        $esDomicilio  = $tipoPedidoOrden === 'domicilio';
        $costoEmpaque = $mesaId ? $this->ordenCostoEmpaque : 0;
        // El total que ve el cajero debe incluir los extras
        $totalConExtras = $totalActual + $costoEmpaque;

        $this->dispatch(
            'confirmar-facturar',
            clienteNombre: $this->textoUtf8($nombreCliente),
            creditoInfo: $this->clienteCreditoInfo,
            totalVenta: $totalConExtras,
            totalProductos: $totalActual,
            factusHabilitado: $this->facturacionElectronicaDisponible($this->getEmpresaId()),
            mesa_id: $mesaId,
            tipo_pedido: $tipoPedidoFactura,
            costo_empaque: $costoEmpaque,
            cobro_domicilio: $esDomicilio ? 'anticipado' : null,
            dom_observaciones: $esDomicilio ? $this->ordenDomObservaciones : null,
            dom_nombre: ($mesaId && $esDomicilio) ? $this->ordenDomNombre : null,
            dom_telefono: ($mesaId && $esDomicilio) ? $this->ordenDomTelefono : null,
            dom_direccion: ($mesaId && $esDomicilio) ? $this->ordenDomDireccion : null,
        );
    }


#[On('facturar-confirmada')]
public function facturarConfirmada(array $data = [])
{
    $empresaId = $this->getEmpresaId();

     if (! $this->verificarCajaAbierta()) {
        Log::warning('POS facturarConfirmada detenido por caja cerrada', [
            'empresa_id' => $empresaId,
            'user_id' => auth()->id(),
            'carrito_count' => count($this->carrito ?? []),
        ]);
        return;
     }


    try {
        DB::beginTransaction();

        Log::info('POS facturarConfirmada inicio', [
            'empresa_id' => $empresaId,
            'user_id' => auth()->id(),
            'carrito_count' => count($this->carrito ?? []),
            'cliente_id' => $this->clienteId,
            'data' => $data,
        ]);

        // âœ… CLIENTE: usa el seleccionado o fuerza CF
        $clienteId = $this->clienteId ?: $this->getConsumidorFinalId($empresaId);
        if (!$clienteId) {
            throw new \Exception('Falta CONSUMIDOR FINAL en esta empresa.');
        }

        // âœ… Permitir stock negativo: solo valida que exista y cantidad > 0
        foreach ($this->carrito as $item) {
            $prod = Product::where('empresa_id', $empresaId)
                ->where('id_producto', $item['id_producto'])
                ->lockForUpdate()
                ->first();

            if (!$prod) {
                throw new \Exception("Producto {$item['id_producto']} no existe.");
            }

            $cant = $this->normalizarCantidad($item['cantidad'] ?? 1, $this->permiteCantidadDecimal($item));
            if ($cant <= 0) {
                throw new \Exception("Cantidad invÃ¡lida en {$item['id_producto']}.");
            }
        }

        // ===== Datos de la peticiÃ³n =====
        $tipoFactura  = $data['tipo_factura'] ?? 'salida';
        $tipoPago     = $data['tipo_pago']    ?? 'contado';
        $medioPago    = $tipoPago === 'contado' ? ($data['medio_pago'] ?? 'efectivo') : null;
        $vencRaw      = $data['fecha_vencimiento'] ?? null;
        $tipoPedido     = $data['tipo_pedido'] ?? 'local';
        $costoEmpaque   = (float)($data['costo_empaque'] ?? 0);
        $cobroDomicilio = $data['cobro_domicilio'] ?? null;

        if ($tipoFactura === 'electronica' && ! $this->facturacionElectronicaDisponible($empresaId)) {
            throw new \Exception('La facturacion electronica no esta activa o no tiene rango Factus configurado para esta empresa.');
        }

        // NUEVO: observaciÃ³n exclusiva para transferencia
        $transferObs = ($tipoPago === 'contado' && $medioPago === 'transferencia')
            ? trim((string)($data['transferencia_obs'] ?? ''))
            : null;

        $obs = trim((string)($this->observacionesPrefactura ?? ''));

        $vendedorId = auth()->id();
        if ($this->prefacturaCargadaId) {
            $prefactura = Prefactura::find($this->prefacturaCargadaId);
            $vendedorId = $prefactura?->vendedor_id ?? $vendedorId;
        }

        // ===== Crear factura =====
        $factura = Factura::create([
            'empresa_id'         => $empresaId,
            'cliente_id'         => $clienteId,
            'user_id'            => auth()->id(),
            'vendedor_id'        => $vendedorId,
            'cajero_id'          => auth()->id(),
            'tipo_factura'       => $tipoFactura,
            'factus_reference_code' => null,
            'factus_bill_id'        => null,
            'factus_number'         => null,
            'factus_cufe'           => null,
            'factus_status'         => null,
            'factus_response'       => null,
            'factus_validated_at'   => null,
            'tipo_pago'          => $tipoPago,
            'medio_pago'         => $medioPago,
            'fecha'              => now(),
            'fecha_compra'       => now(),
            'fecha_pago'         => null,
            'fecha_vencimiento'  => ($tipoPago === 'credito' && $vencRaw)
                                    ? \Carbon\Carbon::parse($vencRaw)->toDateString()
                                    : null,
            'total'              => 0,
            'saldo'              => 0,
            'estado_pago'        => 'pendiente',
            'observaciones'      => $obs,
            'transferencia_obs'  => $transferObs,
            'tipo_pedido'        => $tipoPedido,
            'costo_empaque'      => $costoEmpaque,
            'cobro_domicilio'    => $cobroDomicilio,
            'dom_nombre'         => $data['dom_nombre'] ?? null,
            'dom_telefono'       => $data['dom_telefono'] ?? null,
            'dom_direccion'      => $data['dom_direccion'] ?? null,
            'dom_observaciones'  => $data['dom_observaciones'] ?? $this->ordenDomObservaciones ?: null,
            'dom_nit'            => $data['dom_nit'] ?? null,
            'dom_email'          => $data['dom_email'] ?? null,
            'dom_razon_social'   => $data['dom_razon_social'] ?? null,
        ]);

        // ===== Detalles & stock =====
        foreach ($this->carrito as $item) {
            $precio = (float)($item['nuevo_precio'] ?? $item['precio'] ?? 0);
            $cant   = $this->normalizarCantidad($item['cantidad'] ?? 1, $this->permiteCantidadDecimal($item));
            $sub    = round($precio * $cant, 2);

            $factura->detalles()->create([
                'producto_id'       => $item['id_producto'],
                'descripcion_larga' => $item['nombre'],
                'cantidad'          => $cant,
                'precio'            => $precio,
                'subtotal'          => $sub,
                'descuento'         => (float)($item['descuento'] ?? 0),
            ]);

           $producto = Product::where('empresa_id', $empresaId)
    ->where('id_producto', $item['id_producto'])
    ->lockForUpdate()
    ->first();

if ($producto) {

    $stockAnterior = (float)$producto->existencias;

    // ðŸ”¥ 1. KARDEX (ANTES DE DESCONTAR)
    guardarKardex(
        $item['id_producto'],
        'venta',
        $cant,
        $empresaId,
        $factura->id,
        $stockAnterior
    );

    // 2. DESCONTAR STOCK (solo si NO tiene receta activa)
    $receta = \App\Models\Receta::where('empresa_id', $empresaId)
        ->where('product_id', $producto->id)
        ->where('activo', true)
        ->with('items.ingrediente')
        ->first();

    if (! $receta) {
        $producto->existencias = $stockAnterior - $cant;
        $producto->save();
    }

    // 3. DESCONTAR INGREDIENTES si el producto tiene receta

    if ($receta && $receta->items->isNotEmpty()) {
        $rendimiento = (float) $receta->rendimiento ?: 1;
        foreach ($receta->items as $recetaItem) {
            $ingrediente = $recetaItem->ingrediente;
            if (!$ingrediente) continue;
            $cantBase = ((float) $recetaItem->cantidad / $rendimiento) * $cant;
            $merma = (float) $recetaItem->merma;
            $cantConMerma = $merma > 0 ? $cantBase * (1 + $merma / 100) : $cantBase;
            $stockIngAnterior = (float) $ingrediente->existencias;
            guardarKardex($ingrediente->id_producto, 'venta', $cantConMerma, $empresaId, $factura->id, $stockIngAnterior);
            $ingrediente->existencias = $stockIngAnterior - $cantConMerma;
            $ingrediente->save();
        }
    }
}
        }

        // Agregar costo de domicilio/empaque como ítem de detalle
        if ($costoEmpaque > 0) {
            $label = match($tipoPedido) {
                'domicilio'   => 'Domicilio + desechables',
                'para_llevar' => 'Empaque / desechables',
                default       => 'Empaque',
            };
            $factura->detalles()->create([
                'producto_id'       => 0,
                'descripcion_larga' => $label,
                'cantidad'          => 1,
                'precio'            => $costoEmpaque,
                'subtotal'          => $costoEmpaque,
                'descuento'         => 0,
            ]);
        }

        // Recalcular totales
        $factura->recalcularTotales();

        // ===== Condiciones de pago =====
        if ($tipoPago === 'credito') {
            $info = $this->clienteCreditoInfo; // accessor existente

            if (!($info['permite'] ?? false)) {
                throw new \Exception('El cliente no tiene crÃ©dito habilitado.');
            }
            if ($factura->total > (float)($info['cupo_disponible'] ?? 0)) {
                throw new \Exception('Cupo insuficiente para otorgar este crÃ©dito.');
            }

            // saldo = total, estado pendiente, fecha_venc por dÃ­as de crÃ©dito si no viene
            $factura->saldo = $factura->total;
            $factura->estado_pago = 'pendiente';

            $dias = (int)($info['dias'] ?? 0);
            $factura->fecha_vencimiento = $vencRaw
                ? \Carbon\Carbon::parse($vencRaw)->toDateString()
                : now()->addDays($dias)->toDateString();

            $factura->save();
        } else {
            // contado: registrar abono por el total
            // Nota: si es transferencia, anexa la observaciÃ³n como nota
            $nota = $medioPago === 'transferencia'
                ? ('Transferencia: '.$transferObs)
                : 'Pago contado';

            $factura->registrarAbono(
                monto: (float)$factura->total,
                medio: $medioPago ?? 'efectivo',
                nota : $nota,
                userId: auth()->id(),
                transferenciaObs: $transferObs
            );

            // Puedes marcar fecha_pago si tu registrarAbono no lo hace
            // $factura->update(['fecha_pago' => now()]);
        }

        $this->validarFacturaElectronicaConFactus($factura);

        DB::commit();

        $this->eliminarPrefacturaCargada();

        // ===== Limpiar UI =====
        $this->carrito = [];
        $this->totalGeneral = 0;
        $this->reset('observacionesPrefactura');
        $this->observacionKey++;
        $this->dispatch('limpiar-observaciones');
        $this->forzarConsumidorFinal();
        $this->vendedorPrefacturaId = null;
        session()->forget('carrito_guardado');
        session()->forget('observaciones_guardadas');
        $this->olvidarCarritoPersistente();

        // Liberar mesa si estamos en modo mesa
        if ($this->mesaId) {
            \App\Models\OrdenMesa::where('mesa_id', $this->mesaId)
                ->whereIn('estado', ['abierta', 'en_preparacion'])
                ->update(['estado' => 'facturada', 'cerrada_en' => now()]);
            // Solo liberar la mesa si no quedan cuentas en espera para esta mesa
            $cuentasEnEspera = \App\Models\OrdenMesa::where('mesa_id', $this->mesaId)
                ->where('estado', 'lista')->count();
            if ($cuentasEnEspera === 0) {
                \App\Models\Mesa::where('id', $this->mesaId)->update(['estado' => 'libre']);
            }
        }

        $this->dispatch('success', $factura->numero_visual . ' creada.');
        if ($this->mesaId) {
            $this->redirect(route('pos'));
        }
    } catch (\Throwable $e) {
        DB::rollBack();
        Log::error('POS no pudo crear factura', [
            'empresa_id' => $empresaId,
            'user_id' => auth()->id(),
            'carrito_count' => count($this->carrito ?? []),
            'data' => $data,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        $this->dispatch('error', 'No se pudo crear la factura: '.$e->getMessage());
    }
}




private function validarFacturaElectronicaConFactus(Factura $factura): void
{
    if ($factura->tipo_factura !== 'electronica') {
        return;
    }

    $factura->load(['cliente.ciudad', 'detalles']);

    app(\App\Services\Factus\FactusInvoiceService::class)->validate($factura);

    $factura->refresh();

    if (blank($factura->factus_number) || blank($factura->factus_cufe) || $factura->factus_status !== 'validada') {
        throw new \RuntimeException('Factus no valido la factura electronica. No se guardo la venta.');
    }
}

private function facturacionElectronicaDisponible(int $empresaId): bool
{
    return ConfiguracionEmpresa::query()
        ->where('empresa_id', $empresaId)
        ->where('factus_enabled', true)
        ->whereNotNull('factus_numbering_range_id')
        ->whereNotNull('nit')
        ->where('nit', '!=', '')
        ->whereNotNull('prefijo')
        ->where('prefijo', '!=', '')
        ->whereNotNull('rango_desde')
        ->whereNotNull('rango_hasta')
        ->whereNotNull('rango_actual')
        ->whereNotNull('numero_resolucion')
        ->where('numero_resolucion', '!=', '')
        ->whereNotNull('fecha_inicio')
        ->whereNotNull('fecha_fin')
        ->exists();
}
public function setTab(string $t)
{
    $this->tab = $t;
    if ($t === 'facturas') $this->cargarFacturas();
}

public function actualizarRangoFacturas()
{
    $min = Carbon::parse($this->minFechaFacturas);
    $max = Carbon::parse($this->maxFechaFacturas);

    $desde = Carbon::parse($this->fDesde ?? $max);
    $hasta = Carbon::parse($this->fHasta ?? $max);

    if ($desde->lt($min))  $desde = $min;
    if ($hasta->gt($max))  $hasta = $max;
    if ($desde->gt($hasta)) $desde = $hasta;

    $this->fDesde = $desde->toDateString();
    $this->fHasta = $hasta->toDateString();

    $this->cargarFacturas();
}

public function cargarFacturas()
{
    $empresaId = $this->getEmpresaId();
    $desde = Carbon::parse($this->fDesde)->startOfDay();
    $hasta = Carbon::parse($this->fHasta)->endOfDay();
    $lim = now()->subMonths(3);

    $query = Factura::with(['cliente','detalles'])
        ->where('empresa_id', $empresaId);

    $user = auth()->user();

    if ($user->hasRole('admin_empresa')) {
        // Admin ve todas las facturas de la empresa.
    } elseif ($user->hasRole('cajero') && $user->hasRole('vendedor')) {
        // Si es cajero y vendedor, ver facturas que facturÃ³ como cajero o que generÃ³ como vendedor.
        $query->where(function ($q) use ($user) {
            $q->where('cajero_id', $user->id)
              ->orWhere('vendedor_id', $user->id);
        });
    } elseif ($user->hasRole('cajero')) {
        $query->where('cajero_id', $user->id);
    } elseif ($user->hasRole('vendedor')) {
        $query->where('vendedor_id', $user->id);
    }

    $this->facturas = $query
        ->whereBetween('fecha', [$desde, $hasta])
        ->where('fecha','>=',$lim)
        ->orderBy('fecha','desc')
        ->get();
}

public function seleccionarFactura(int $id)
{
    $this->facturaSeleccionada = Factura::with('detalles')->find($id);
    if (!$this->facturaSeleccionada) return;

    $this->detalleFacturaSeleccionada = $this->facturaSeleccionada->detalles->map(function($d){
        $pend = max(0, (float)$d->cantidad - (float)$d->devuelto_cantidad);
        return [
            'id' => $d->id,
            'producto_id' => $d->producto_id,
            'descripcion_larga' => $d->descripcion_larga,
            'cantidad' => (float)$d->cantidad,
            'devuelto_cantidad' => (float)$d->devuelto_cantidad,
            'pendiente' => $pend,
            'precio' => (float)$d->precio,
            'subtotal' => (float)$d->subtotal,
        ];
    })->toArray();
}

public function imprimirFacturaSeleccionada()
{
    if (!$this->facturaSeleccionada) return;
    return redirect()->route('factura.imprimir', $this->facturaSeleccionada->id);
}

// DevoluciÃ³n total
public function devolverFacturaCompleta()
{
    if (!$this->facturaSeleccionada) return;

    DB::transaction(function () {
        $f = $this->facturaSeleccionada->fresh(['detalles']);
        if ($f->devuelta_total) {
            $this->dispatch('error','Esta factura ya fue devuelta totalmente.');
            return;
        }
        foreach ($f->detalles as $d) {
            $pend = max(0, (float)$d->cantidad - (float)$d->devuelto_cantidad);
            if ($pend > 0) {
                Product::where('empresa_id', $f->empresa_id)
                    ->where('id_producto', $d->producto_id)
                    ->lockForUpdate()
                    ->increment('existencias', $pend);

                $d->devuelto_cantidad = (float)$d->cantidad;
                $d->save();
            }
        }
        $f->devuelta_total = true;
        $f->observaciones = trim(($f->observaciones ?? '').' | DEVUELTA TOTAL '.now()->toDateTimeString());
        $f->save();

        // ðŸ'‡ AGREGA ESTA LÃNEA
        $f->recalcularTotales();
    });

    $this->seleccionarFactura($this->facturaSeleccionada->id);
    $this->dispatch('success','DevoluciÃ³n total realizada.');
}

// DevoluciÃ³n parcial (por Ã­tem)
public function devolverItemFactura(int $detalleId, $cantidad)
{
    if (!$this->facturaSeleccionada) return;

    $cantidad = (float)$cantidad;
    if ($cantidad <= 0) { $this->dispatch('error','Cantidad invÃ¡lida.'); return; }

    DB::transaction(function () use ($detalleId, $cantidad) {
        /** @var FacturaDetalle $det */
        $det = FacturaDetalle::lockForUpdate()->find($detalleId);
        if (!$det) { $this->dispatch('error','Detalle no encontrado.'); return; }

        $pend = max(0, (float)$det->cantidad - (float)$det->devuelto_cantidad);
        if ($cantidad > $pend) { $this->dispatch('error','Excede lo pendiente por devolver.'); return; }

        Product::where('empresa_id', $this->facturaSeleccionada->empresa_id)
            ->where('id_producto', $det->producto_id)
            ->increment('existencias', $cantidad);

        $det->devuelto_cantidad = (float)$det->devuelto_cantidad + $cantidad;
        $det->save();

        // Si todos los Ã­tems quedaron devueltos, marcamos la factura como total
        $f = $this->facturaSeleccionada->fresh(['detalles']);
        $todosDev = $f->detalles->every(fn($d) => (float)$d->devuelto_cantidad >= (float)$d->cantidad);
        if ($todosDev && !$f->devuelta_total) {
            $f->devuelta_total = true;
            $f->observaciones = trim(($f->observaciones ?? '').' | DEVUELTA TOTAL '.now()->toDateTimeString());
            $f->save();
        }

        // ðŸ'‡ AGREGA ESTA LÃNEA
        $f->recalcularTotales();
    });

    $this->seleccionarFactura($this->facturaSeleccionada->id);
    $this->dispatch('success','DevoluciÃ³n parcial registrada.');
}

private function getConsumidorFinalId(int $empresaId): ?int
{
    return Actor::where('empresa_id', $empresaId)
        ->where('nombre', 'CONSUMIDOR FINAL')
        ->value('id');
}

// Forzar siempre CF (no condicional)
private function forzarConsumidorFinal(): void
{
    $empresaId = $this->getEmpresaId();
    if ($id = $this->getConsumidorFinalId($empresaId)) {
        $this->clienteId = $id;
        $this->clienteSeleccionadoNombre = 'CONSUMIDOR FINAL';
    }
}

// Refrescar modal al abrir/cerrar


public function updatedMostrarModalPrefacturas($isOpen)
{
    if ($isOpen) {
        $this->onOpenModalPrefacturas();
    } else {
        $this->onCloseModalPrefacturas();
    }
}

public function onOpenModalPrefacturas(): void
{
    // PestaÃ±a inicial
    $this->tab = 'prefacturas';
    // Rango 3 meses
    $this->maxFechaFacturas = now()->toDateString();
    $this->minFechaFacturas = now()->subMonths(3)->toDateString();
    $this->fDesde = $this->maxFechaFacturas;
    $this->fHasta = $this->maxFechaFacturas;
    // Cargar listas
    $this->cargarFacturas();          // ya lo tienes
    // (si tienes mÃ©todo para prefacturas, llÃ¡malo aquÃ­)
    // $this->cargarPrefacturasGuardadas();
}

public function onCloseModalPrefacturas(): void
{
    $this->reset([
        'prefacturaSeleccionada','detalleSeleccionado',
        'facturaSeleccionada','detalleFacturaSeleccionada'
    ]);
}


public function facturarEImprimir(array $data = [])
{
    $empresaId = $this->getEmpresaId();
    if (! $this->verificarCajaAbierta()) {
            return ['ok' => false, 'error' => 'Caja cerrada.'];
        }

    try {
        DB::beginTransaction();

        // âœ… Cliente: seleccionado o CONSUMIDOR FINAL
        $clienteId = $this->clienteId ?: $this->getConsumidorFinalId($empresaId);
        if (!$clienteId) {
            throw new \Exception('Falta CONSUMIDOR FINAL en esta empresa.');
        }

        // âœ… Carrito (permitiendo stock negativo)
        if (empty($this->carrito)) {
            throw new \Exception('El carrito estÃ¡ vacÃ­o.');
        }

        foreach ($this->carrito as $item) {
            $prod = Product::where('empresa_id', $empresaId)
                ->where('id_producto', $item['id_producto'])
                ->lockForUpdate()
                ->first();

            if (!$prod) {
                throw new \Exception("Producto {$item['id_producto']} no existe.");
            }

            $cant = $this->normalizarCantidad($item['cantidad'] ?? 1, $this->permiteCantidadDecimal($item));
            if ($cant <= 0) {
                throw new \Exception("Cantidad invÃ¡lida en {$item['id_producto']}.");
            }
        }

        // ===== Datos de cabecera
        $tipoFactura  = $data['tipo_factura'] ?? 'salida';
        $tipoPago     = $data['tipo_pago']    ?? 'contado';
        $medioPago    = $tipoPago === 'contado' ? ($data['medio_pago'] ?? 'efectivo') : null;
        $vencRaw      = $data['fecha_vencimiento'] ?? null;
        $tipoPedido     = $data['tipo_pedido'] ?? 'local';
        $costoEmpaque   = (float)($data['costo_empaque'] ?? 0);
        $cobroDomicilio = $data['cobro_domicilio'] ?? null;

        if ($tipoFactura === 'electronica' && ! $this->facturacionElectronicaDisponible($empresaId)) {
            throw new \Exception('La facturacion electronica no esta activa o no tiene rango Factus configurado para esta empresa.');
        }

        // ðŸ'‡ ObservaciÃ³n especÃ­fica si es transferencia en contado
        $transferObs = ($tipoPago === 'contado' && $medioPago === 'transferencia')
            ? trim((string)($data['transferencia_obs'] ?? ''))
            : null;

        $obs = trim((string)($this->observacionesPrefactura ?? ''));

        // ===== Crear factura
        $vendedorId = auth()->id();
        if ($this->prefacturaCargadaId) {
            $prefactura = Prefactura::find($this->prefacturaCargadaId);
            $vendedorId = $prefactura?->vendedor_id ?? $vendedorId;
        }

        $factura = Factura::create([
            'empresa_id'         => $empresaId,
            'cliente_id'         => $clienteId,
            'user_id'            => auth()->id(),
            'vendedor_id'        => $vendedorId,
            'cajero_id'          => auth()->id(),
            'tipo_factura'       => $tipoFactura,
            'factus_reference_code' => null,
            'factus_bill_id'        => null,
            'factus_number'         => null,
            'factus_cufe'           => null,
            'factus_status'         => null,
            'factus_response'       => null,
            'factus_validated_at'   => null,
            'tipo_pago'          => $tipoPago,
            'medio_pago'         => $medioPago,
            'transferencia_obs'  => $transferObs,
            'fecha'              => now(),
            'fecha_compra'       => now(),
            'fecha_pago'         => null,
            'fecha_vencimiento'  => ($tipoPago === 'credito' && $vencRaw)
                                    ? \Carbon\Carbon::parse($vencRaw)->toDateString()
                                    : null,
            'total'              => 0,
            'saldo'              => 0,
            'estado_pago'        => 'pendiente',
            'observaciones'      => $obs,
            'tipo_pedido'        => $tipoPedido,
            'costo_empaque'      => $costoEmpaque,
            'cobro_domicilio'    => $cobroDomicilio,
            'dom_nombre'         => $data['dom_nombre'] ?? null,
            'dom_telefono'       => $data['dom_telefono'] ?? null,
            'dom_direccion'      => $data['dom_direccion'] ?? null,
            'dom_observaciones'  => $data['dom_observaciones'] ?? $this->ordenDomObservaciones ?: null,
            'dom_nit'            => $data['dom_nit'] ?? null,
            'dom_email'          => $data['dom_email'] ?? null,
            'dom_razon_social'   => $data['dom_razon_social'] ?? null,
        ]);

        // ===== Detalles + existencias
        foreach ($this->carrito as $item) {
            $precio = (float)($item['nuevo_precio'] ?? $item['precio'] ?? 0);
            $cant   = $this->normalizarCantidad($item['cantidad'] ?? 1, $this->permiteCantidadDecimal($item));
            $sub    = round($precio * $cant, 2);

            $factura->detalles()->create([
                'producto_id'       => $item['id_producto'],
                'descripcion_larga' => $item['nombre'],
                'cantidad'          => $cant,
                'precio'            => $precio,
                'subtotal'          => $sub,
                'descuento'         => (float)($item['descuento'] ?? 0),
            ]);

            $producto = Product::where('empresa_id', $empresaId)
    ->where('id_producto', $item['id_producto'])
    ->lockForUpdate()
    ->first();

if ($producto) {

    $stockAnterior = (float)$producto->existencias;

    // ðŸ”¥ 1. KARDEX (ANTES DE DESCONTAR)
    guardarKardex(
        $item['id_producto'],
        'venta',
        $cant,
        $empresaId,
        $factura->id,
        $stockAnterior
    );

    // 2. DESCONTAR STOCK (solo si NO tiene receta activa)
    $receta = \App\Models\Receta::where('empresa_id', $empresaId)
        ->where('product_id', $producto->id)
        ->where('activo', true)
        ->with('items.ingrediente')
        ->first();

    if (! $receta) {
        $producto->existencias = $stockAnterior - $cant;
        $producto->save();
    }


    if ($receta && $receta->items->isNotEmpty()) {
        $rendimiento = (float) $receta->rendimiento ?: 1;
        foreach ($receta->items as $recetaItem) {
            $ingrediente = $recetaItem->ingrediente;
            if (!$ingrediente) continue;
            $cantBase = ((float) $recetaItem->cantidad / $rendimiento) * $cant;
            $merma = (float) $recetaItem->merma;
            $cantConMerma = $merma > 0 ? $cantBase * (1 + $merma / 100) : $cantBase;
            $stockIngAnterior = (float) $ingrediente->existencias;
            guardarKardex($ingrediente->id_producto, 'venta', $cantConMerma, $empresaId, $factura->id, $stockIngAnterior);
            $ingrediente->existencias = $stockIngAnterior - $cantConMerma;
            $ingrediente->save();
        }
    }
}
        }

        // Agregar costo de domicilio/empaque como ítem de detalle
        if ($costoEmpaque > 0) {
            $label = match($tipoPedido) {
                'domicilio'   => 'Domicilio + desechables',
                'para_llevar' => 'Empaque / desechables',
                default       => 'Empaque',
            };
            $factura->detalles()->create([
                'producto_id'       => 0,
                'descripcion_larga' => $label,
                'cantidad'          => 1,
                'precio'            => $costoEmpaque,
                'subtotal'          => $costoEmpaque,
                'descuento'         => 0,
            ]);
        }

        // Totales
        $factura->recalcularTotales();

        // ===== Condiciones de pago
        if ($tipoPago === 'credito') {
            $info = $this->clienteCreditoInfo;

            if (!($info['permite'] ?? false)) {
                throw new \Exception('El cliente no tiene crÃ©dito habilitado.');
            }
            if ($factura->total > (float)($info['cupo_disponible'] ?? 0)) {
                throw new \Exception('Cupo insuficiente para otorgar este crÃ©dito.');
            }

            $dias = (int)($info['dias'] ?? 0);

            $factura->saldo = $factura->total;
            $factura->estado_pago = 'pendiente';
            $factura->fecha_vencimiento = $vencRaw
                ? \Carbon\Carbon::parse($vencRaw)->toDateString()
                : now()->addDays($dias)->toDateString();

            $factura->save();
        } else {
            // contado: un solo abono por el total
            $nota = ($medioPago === 'transferencia' && $transferObs)
                ? ('Transferencia: '.$transferObs)
                : 'Pago contado';

            $factura->registrarAbono(
                monto: (float)$factura->total,
                medio: $medioPago ?? 'efectivo',
                nota : $nota,
                userId: auth()->id(),
                transferenciaObs: $transferObs
            );
        }

        $this->validarFacturaElectronicaConFactus($factura);

        DB::commit();

        $this->eliminarPrefacturaCargada();

        // ===== Limpiar UI
        $this->carrito = [];
        $this->totalGeneral = 0;
        $this->observacionesPrefactura = '';
        $this->observacionKey = ($this->observacionKey ?? 0) + 1;
        $this->dispatch('limpiar-observaciones');
        $this->forzarConsumidorFinal();
        $this->vendedorPrefacturaId = null;
        session()->forget('carrito_guardado');
        session()->forget('observaciones_guardadas');
        $this->olvidarCarritoPersistente();

        // Liberar mesa si estamos en modo mesa
        if ($this->mesaId) {
            \App\Models\OrdenMesa::where('mesa_id', $this->mesaId)
                ->whereIn('estado', ['abierta', 'en_preparacion'])
                ->update(['estado' => 'facturada', 'cerrada_en' => now()]);
            // Solo liberar la mesa si no quedan cuentas en espera para esta mesa
            $cuentasEnEspera = \App\Models\OrdenMesa::where('mesa_id', $this->mesaId)
                ->where('estado', 'lista')->count();
            if ($cuentasEnEspera === 0) {
                \App\Models\Mesa::where('id', $this->mesaId)->update(['estado' => 'libre']);
            }
        }

        // ===== Devolver URL para imprimir (lo consume el .then del botón)
        $url = route('factura.imprimir', $factura->id);
        $this->dispatch('success', $factura->numero_visual . ' creada.');
        return ['ok' => true, 'factura_id' => $factura->id, 'print_url' => $url, 'redirect_url' => $this->mesaId ? route('pos') : null];

    } catch (\Throwable $e) {
        DB::rollBack();
        Log::error('POS no pudo crear/imprimir salida', [
            'empresa_id' => $empresaId,
            'user_id' => auth()->id(),
            'carrito_count' => count($this->carrito ?? []),
            'data' => $data,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        $this->dispatch('error', 'No se pudo crear/imprimir la salida: '.$e->getMessage());
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}


public function getClienteCreditoInfoProperty(): array
{
    $empresaId = $this->getEmpresaId();
    $id = (int) ($this->clienteId ?? 0);

    if ($id <= 0) {
        return [
            'permite' => false,
            'limite'  => 0.0,
            'dias'    => 0,
            'deuda'   => 0.0,
            'cupo_disponible' => 0.0,
            'nombre'  => 'Sin cliente',
        ];
    }

    // âœ… SOLO por PK real de actors.id
    $actor = \App\Models\Actor::where('empresa_id', $empresaId)
        ->where('id', $id)
        ->first();

    if (!$actor) {
        return [
            'permite' => false,
            'limite'  => 0.0,
            'dias'    => 0,
            'deuda'   => 0.0,
            'cupo_disponible' => 0.0,
            'nombre'  => 'No encontrado',
        ];
    }

    // Deuda vigente del cliente
    $deuda = \App\Models\Factura::where('empresa_id', $empresaId)
        ->where('cliente_id', $actor->id)   // â† siempre por actors.id
        ->where('saldo', '>', 0)
        ->where(function ($q) {
            $q->whereNull('devuelta_total')->orWhere('devuelta_total', false);
        })
        ->sum('saldo');

    $limite = (float)($actor->limite_credito ?? 0);
    $disp   = max(0, $limite - (float)$deuda);

    return [
        'permite' => (bool)($actor->permite_credito ?? false),
        'limite'  => $limite,
        'dias'    => (int)($actor->dias_credito ?? 0),
        'deuda'   => (float)$deuda,
        'cupo_disponible' => (float)$disp,
        'nombre'  => (string)($actor->nombre ?? ''),
    ];
}



public function abrirDialogoDevolucion()
{
    // Si no hay factura seleccionada, no seguimos
    if (!$this->facturaSeleccionada) {
        $this->dispatch('error','Selecciona una factura primero.');
        return;
    }

    // ðŸš« Validar: si es crÃ©dito y NO estÃ¡ pagada, no permitir devoluciÃ³n
    if (
        $this->facturaSeleccionada->tipo_pago === 'credito' &&
        $this->facturaSeleccionada->estado_pago !== 'pagada'
    ) {
        $this->dispatch('error','No puedes hacer devoluciones en facturas a crÃ©dito hasta que estÃ©n pagadas.');
        return;
    }

    // Abre un Swal para elegir tipo y luego arma el carrito modal
    $this->dispatch('ui-elegir-tipo-devolucion');
}

public function prepararDevolucion(string $tipo = 'completa')
{
    // Debe haber una factura seleccionada
    if (!$this->facturaSeleccionada) {
        $this->dispatch('error','Selecciona una factura primero.');
        return;
    }

    $this->tipoDevolucion = $tipo;
    $this->carritoDevolucion = [];
    $this->totalDevolucion = 0;

    foreach ($this->detalleFacturaSeleccionada as $d) {
        $pend = max(0, (float)$d['pendiente']);
        if ($pend <= 0) continue;

        $cant = ($tipo === 'completa') ? $pend : 0;
        $sel  = ($tipo === 'completa');

        $precio   = (float)$d['precio'];
        $subtotal = round($precio * $cant, 2);

        $this->carritoDevolucion[(int)$d['id']] = [
            'seleccion'         => $sel,
            'factura_detalle_id'=> (int)$d['id'],
            'producto_id'       => (int)$d['producto_id'],
            'descripcion'       => (string)$d['descripcion_larga'],
            'pendiente'         => $pend,
            'cantidad'          => $cant,
            'precio'            => $precio,
            'subtotal'          => $subtotal,
        ];

        $this->totalDevolucion += $subtotal;
    }

    // Mostrar el modal plantilla
    $this->mostrarModalDevolucion = true;
}

public function toggleSeleccionDevolucion(int $detId)
{
    if (!isset($this->carritoDevolucion[$detId])) return;
    $row = &$this->carritoDevolucion[$detId];

    $row['seleccion'] = !$row['seleccion'];

    // Si se selecciona en parcial y no hay cantidad, poner 1 por defecto
    if ($row['seleccion'] && $row['cantidad'] <= 0) {
        $row['cantidad'] = 1;
    }
    $row['cantidad'] = min($row['cantidad'], $row['pendiente']);

    $row['subtotal'] = round($row['precio'] * $row['cantidad'], 2);
    $this->recalcularTotalDevolucion();
}

public function setCantidadDevolucion(int $detId, $cantidad)
{
    if (!isset($this->carritoDevolucion[$detId])) return;
    $row = &$this->carritoDevolucion[$detId];

    $cant = max(0, (float)$cantidad);
    $cant = min($cant, (float)$row['pendiente']);
    $row['cantidad'] = $cant;

    // Si cantidad pasa a 0, des-seleccionar
    if ($cant <= 0) {
        $row['seleccion'] = false;
    } else {
        $row['seleccion'] = true;
    }

    $row['subtotal'] = round($row['precio'] * $row['cantidad'], 2);
    $this->recalcularTotalDevolucion();
}

public function seleccionarTodosDevolucion()
{
    foreach ($this->carritoDevolucion as $id => &$row) {
        $row['seleccion'] = true;
        if ($row['cantidad'] <= 0) $row['cantidad'] = $row['pendiente'];
        $row['subtotal'] = round($row['precio'] * $row['cantidad'], 2);
    }
    $this->recalcularTotalDevolucion();
}

public function limpiarSeleccionDevolucion()
{
    foreach ($this->carritoDevolucion as $id => &$row) {
        $row['seleccion'] = false;
        $row['cantidad']  = 0;
        $row['subtotal']  = 0;
    }
    $this->recalcularTotalDevolucion();
}

private function recalcularTotalDevolucion()
{
    $this->totalDevolucion = 0;
    foreach ($this->carritoDevolucion as $row) {
        if ($row['seleccion']) $this->totalDevolucion += (float)$row['subtotal'];
    }
}

public function confirmarDevolucion()
{
    if (!$this->facturaSeleccionada) { $this->dispatch('error','No hay factura.'); return; }

    // Filtrar seleccionados con cantidad > 0
    $items = array_filter($this->carritoDevolucion, fn($r) => $r['seleccion'] && $r['cantidad'] > 0);
    if (empty($items)) { $this->dispatch('error','No hay Ã­tems seleccionados.'); return; }

    try {
        DB::transaction(function() use ($items) {
        $f = $this->facturaSeleccionada->fresh(['detalles', 'configuracionEmpresa']);
        $empresaId = $f->empresa_id;

        // Crear cabecera devoluciÃ³n
        $dev = \App\Models\Devolucion::create([
            'empresa_id'   => $empresaId,
            'factura_id'   => $f->id,
            'cliente_id'   => $f->cliente_id,
            'user_id'      => auth()->id(),   // <-- aquÃ­ capturas al usuario logueado
            'fecha'        => now(),
            'total'        => 0,
            'observaciones'=> 'DevoluciÃ³n de factura #'.$f->id,
        ]);

        $total = 0;

        foreach ($items as $row) {
            /** @var \App\Models\FacturaDetalle $det */
            $det = \App\Models\FacturaDetalle::lockForUpdate()->find($row['factura_detalle_id']);
            if (!$det) continue;

            $pend = max(0, (float)$det->cantidad - (float)$det->devuelto_cantidad);
            $cant = min((float)$row['cantidad'], $pend);
            if ($cant <= 0) continue;

            // Inventario: regresa a existencias
         $producto = \App\Models\Product::where('empresa_id', $empresaId)
    ->where('id_producto', $row['producto_id'])
    ->first();

if ($producto) {

    $stockAnterior = $producto->existencias;

    // ðŸ”¥ ACTUALIZA STOCK
    $producto->existencias += $cant;
    $producto->save();

    // ðŸ”¥ KARDEX (ENTRADA POR DEVOLUCIÃ“N)
    guardarKardex(
        $row['producto_id'],
        'devolucion_venta',
        $cant,
        $empresaId,
        $dev->id,
        $stockAnterior // ðŸ'ˆ CLAVE
    );
}

            // Marcar devuelto
            $det->devuelto_cantidad = (float)$det->devuelto_cantidad + $cant;
            $det->save();

            // Detalle devoluciÃ³n
            $precio   = (float)$row['precio'];
            $subtotal = round($precio * $cant, 2);

            \App\Models\DevolucionDetalle::create([
                'devolucion_id'     => $dev->id,
                'factura_detalle_id'=> $det->id,
                'producto_id'       => $row['producto_id'],
                'descripcion_larga' => $row['descripcion'],
                'cantidad'          => $cant,
                'precio'            => $precio,
                'subtotal'          => $subtotal,
            ]);

            $total += $subtotal;
        }

        // Total devoluciÃ³n
        $dev->total = $total;
        $dev->save();

        if ($total <= 0) {
            throw new \RuntimeException('No hay cantidades pendientes para devolver en esta factura.');
        }

        // Si todos los Ã­tems quedaron devueltos, marcar la factura como total
        $f->recalcularTotales();

        $f2 = $f->fresh(['detalles', 'configuracionEmpresa']);
        $todosDev = $f2->detalles->every(fn($d) => (float)$d->devuelto_cantidad >= (float)$d->cantidad);
        if ($todosDev && !$f2->devuelta_total) {
            $f2->devuelta_total = true;
            $f2->observaciones = trim(($f2->observaciones ?? '').' | DEVUELTA TOTAL '.now()->toDateTimeString());
            $f2->save();
        }

        if ($f2->tipo_factura === 'electronica') {
            app(\App\Services\Factus\FactusCreditNoteService::class)->validate($dev);

            $dev->refresh();

            if ($dev->factus_credit_note_status !== 'validada') {
                throw new \RuntimeException('Factus no valido la nota credito electronica.');
            }
        }

        // Cerrar modal y abrir impresiÃ³n
        $this->mostrarModalDevolucion = false;
        $this->carritoDevolucion = [];
        $this->totalDevolucion = 0;

        // Refrescar la selecciÃ³n de factura en la UI
        $this->seleccionarFactura($f2->id);

        // Imprimir
        $url = route('devolucion.imprimir', $dev->id);
        $this->dispatch('open-print', url: $url);
        $this->dispatch('success', $f2->tipo_factura === 'electronica'
            ? 'Devolucion registrada y nota credito enviada a la DIAN.'
            : 'Devolucion registrada.');
        });
    } catch (\Throwable $e) {
        Log::warning('No se pudo registrar la devolucion', [
            'factura_id' => $this->facturaSeleccionada?->id,
            'error' => $e->getMessage(),
        ]);

        $this->dispatch('error', 'No se pudo registrar la devolucion: '.$e->getMessage());
    }
}

private function resolveClienteActorId(): ?int
{
    if (!$this->clienteId) return null;
    $empresaId = $this->getEmpresaId();

    $actor = \App\Models\Actor::where('empresa_id', $empresaId)
        ->where(function($q){
            $q->where('id', $this->clienteId)
              ->orWhere('id_clip_pro', $this->clienteId);
        })->first();

    return $actor?->id;
}

public function abrirModalCartera(): void
{
    if (
    !auth()->user()->hasRole('cajero') &&
    !auth()->user()->hasRole('admin_empresa')
) {
    abort(403);
}

    $this->carteraBuscar = '';
    $this->carteraClienteId = null;
    $this->carteraFacturas = [];
    $this->carteraTotalCliente = 0;
    $this->cargarClientesConCartera();
    $this->mostrarModalCartera = true;
}

public function updatedCarteraBuscar(): void
{
    $this->cargarClientesConCartera();
}

private function cargarClientesConCartera(): void
{
    $empresaId = $this->getEmpresaId();
    $term = trim($this->carteraBuscar);

    // 1) Traemos SOLO facturas que pueden deber algo
    $facturas = \App\Models\Factura::query()
        ->select(['id','cliente_id','fecha_vencimiento'])
        ->where('empresa_id', $empresaId)
        ->whereIn('estado_pago', ['pendiente', 'parcial', 'vencida'])
        ->where(function ($q) {
            $q->whereNull('devuelta_total')
              ->orWhere('devuelta_total', false);
        })
        ->with([
            // solo campos usados en el cÃ¡lculo
            'detalles:id,factura_id,precio,cantidad,devuelto_cantidad',
            'pagos:id,factura_id,monto',
        ])
        ->get();

    // 2) Agrupamos por cliente calculando VENCE = max(0, total - devuelto - pagado)
    $porCliente = []; // [cliente_id => ['saldo'=>..., 'facturas'=>..., 'max_venc'=>...]]
    foreach ($facturas as $f) {
        $totalOriginal = (float) $f->detalles->sum(fn($d) => $d->precio * $d->cantidad);
        $totalDevuelto = (float) $f->detalles->sum(fn($d) => $d->precio * ($d->devuelto_cantidad ?? 0));
        $totalPagado   = (float) $f->pagos->sum('monto');
        $vence         = max(0, $totalOriginal - $totalDevuelto - $totalPagado);

        if ($vence <= 0) {
            continue; // esta factura ya no debe nada segÃºn el cÃ¡lculo real
        }

        $cid = (int) $f->cliente_id;
        if (!isset($porCliente[$cid])) {
            $porCliente[$cid] = [
                'saldo'    => 0.0,
                'facturas' => 0,
                'max_venc' => $f->fecha_vencimiento?->toDateString(),
            ];
        }

        $porCliente[$cid]['saldo']    += $vence;
        $porCliente[$cid]['facturas'] += 1;

        // actualizar fecha de vencimiento mÃ¡xima
        $cur = $porCliente[$cid]['max_venc'];
        $fv  = $f->fecha_vencimiento?->toDateString();
        if ($fv && (!$cur || $fv > $cur)) {
            $porCliente[$cid]['max_venc'] = $fv;
        }
    }

    // 3) Cargamos nombres de actores y aplicamos filtro de bÃºsqueda
    $ids = array_keys($porCliente);
    $actores = $ids
        ? \App\Models\Actor::query()
            ->where('empresa_id', $empresaId)
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id')
        : collect();

    $rows = [];
    foreach ($porCliente as $cid => $agg) {
        $actor = $actores->get($cid);
        if (!$actor) continue;

        $nombre = (string) $actor->nombre;
        $ident  = (string) ($actor->identificacion ?? '');

        if ($term !== '') {
            $t = mb_strtolower($term);
            if (mb_strpos(mb_strtolower($nombre), $t) === false &&
                mb_strpos(mb_strtolower($ident),  $t) === false) {
                continue;
            }
        }

        $rows[] = [
            'id'       => (int) $cid,
            'nombre'   => $nombre,
            'saldo'    => (float) $agg['saldo'],      // ðŸ'ˆ ahora es la suma de VENCE
            'facturas' => (int) $agg['facturas'],
            'max_venc' => (string) ($agg['max_venc'] ?? 'â€”'),
        ];
    }

    // 4) Ordenamos por nombre y asignamos al estado
    usort($rows, fn($a, $b) => strcasecmp($a['nombre'], $b['nombre']));
    $this->carteraClientes = $rows;

    // 5) Si el seleccionado desapareciÃ³, limpiamos
    if ($this->carteraClienteId && !collect($this->carteraClientes)->firstWhere('id', $this->carteraClienteId)) {
        $this->carteraClienteId = null;
        $this->carteraFacturas = [];
        $this->carteraTotalCliente = 0;
    }
}

public function seleccionarClienteCartera(int $clienteId): void
{
    $empresaId = $this->getEmpresaId();
    $this->carteraClienteId = $clienteId;
    $this->cargandoFacturas = true;

    // Cargar facturas del cliente
    $facturas = \App\Models\Factura::query()
    ->where('empresa_id', $empresaId)
    ->where('cliente_id', $clienteId)
    ->whereIn('estado_pago', ['pendiente', 'parcial', 'vencida'])
    ->where(function ($q) {
        $q->whereNull('devuelta_total')
          ->orWhere('devuelta_total', false);
    })
    ->orderByDesc('id')
    ->get();

    $this->carteraFacturas = $facturas->map(function ($f) {
        $totalOriginal = (float) $f->detalles()->sum(\DB::raw('precio * cantidad'));
        $totalDevuelto = (float) $f->detalles()->sum(\DB::raw('precio * devuelto_cantidad'));
        $totalPagado   = (float) $f->pagos()->sum('monto');
        $vence         = max(0, $totalOriginal - $totalDevuelto - $totalPagado);

        return [
            'id'       => $f->id,
            'numero_visual' => $f->numero_visual,
            'fecha'    => $f->fecha,
            'total'    => $totalOriginal,
            'devuelto' => $totalDevuelto,
            'pagado'   => $totalPagado,
            'vence'    => $vence,
            'estado'   => $f->estado_pago,
        ];
    })->values()->all();

    $this->carteraTotalCliente = array_sum(array_column($this->carteraFacturas, 'vence'));
    $this->cargandoFacturas = false;
}


public function verFacturaEnModal(int $id): void
{
    $this->verFacturaId = $id;
    $this->mostrarModalFactura = true;
}

public function cerrarFacturaModal(): void
{
    $this->mostrarModalFactura = false;
    $this->verFacturaId = null;
}

public function cerrarModalCartera(): void
{
    $this->mostrarModalCartera = false;
}


    
public function abrirAbono(int $id): void
{
    $empresaId = $this->getEmpresaId();
    $f = Factura::where('empresa_id', $empresaId)->with('cliente', 'detalles', 'pagos')->findOrFail($id);

    // Calcula el valor real pendiente (vence)
    $totalOriginal = (float) $f->detalles()->sum(\DB::raw('precio * cantidad'));
    $totalDevuelto = (float) $f->detalles()->sum(\DB::raw('precio * devuelto_cantidad'));
    $totalPagado   = (float) $f->pagos()->sum('monto');
    $vence         = max(0, $totalOriginal - $totalDevuelto - $totalPagado);

    $this->abonoFacturaId     = $f->id;
    $this->abonoSaldo         = (float) $f->saldo;
    $this->abonoVence         = $vence; // <-- Ahora sÃ­ estÃ¡ definido
    $this->abonoMonto         = $vence; // Valor sugerido por defecto
    $this->abonoMedio         = 'efectivo';
    $this->abonoTransferObs   = '';
    $this->abonoClienteNombre = $f->cliente->nombre ?? 'Consumidor final';
    $this->mostrarModalAbono  = true;
}

    public function cerrarAbono(): void
    {
        $this->mostrarModalAbono = false;
    }

    // ================ CONFIRMAR ABONO ===================
    public function confirmarAbono(): void
    {
        if (!$this->abonoFacturaId) return;

        // Normaliza (2.000, 2,000 â†' 2000)
        $this->abonoMonto = (float) preg_replace('/\D/', '', (string) $this->abonoMonto);

        $empresaId = $this->getEmpresaId();
        $f = Factura::where('empresa_id', $empresaId)->lockForUpdate()->findOrFail($this->abonoFacturaId);

        $this->validate([
            'abonoMonto' => ['required','numeric','min:1', function ($attr, $value, $fail) use ($f) {
                if ((float)$value > (float)$f->saldo) {
                    $fail('El abono no puede superar el saldo ($'.number_format($f->saldo,0,',','.').').');
                }
            }],
            'abonoMedio' => ['required','string','in:efectivo,transferencia,otro'],
            'abonoTransferObs' => [$this->abonoMedio === 'transferencia' ? 'required' : 'nullable','string','max:255'],
        ]);

        $payload = [
            'monto'             => (float) $this->abonoMonto,
            'medio'             => $this->abonoMedio,
            'nota'              => 'Abono desde Cartera',
            'transferencia_obs' => $this->abonoMedio === 'transferencia' ? trim($this->abonoTransferObs) : null,
        ];

        $this->abonarEnCartera($this->abonoFacturaId, $payload);

        $this->mostrarModalAbono = false;

        // refresca UI
        $f->refresh();
        $this->cargarCarteraCliente($f->cliente_id);
    }


 // ================ PAGO RÃPIDO (saldo total) =========
    public function pagarFactura(int $id): void
    {
        $empresaId = $this->getEmpresaId();
        $f = Factura::where('empresa_id', $empresaId)->lockForUpdate()->findOrFail($id);

        $saldo = (float) $f->saldo;
        if ($saldo <= 0) { $this->dispatch('info','La factura ya estÃ¡ pagada.'); return; }

        $this->abonarEnCartera($f->id, [
            'monto' => $saldo,
            'medio' => 'efectivo',
            'nota'  => 'Pago total desde Cartera',
            'transferencia_obs' => null,
        ]);

        $f->refresh();
        $this->cargarCarteraCliente($f->cliente_id);

        // cierra cartera y abre impresiÃ³n
        $this->mostrarModalCartera = false;

        // abre en nueva pestaÃ±a: usa el que prefieras
        // $url = route('factura.imprimir', $f->id); // ticket
        $url = route('factura.ver', $f->id);         // misma vista del "Ver"
        $this->dispatch('open-print', url: $url);

        // re-render
        $this->carteraRefreshKey++;
        $this->dispatch('$refresh');

        $this->dispatch('success','Factura pagada.');
    }
protected function cargarCarteraCliente(int $clienteId): void
{
    $empresaId = $this->getEmpresaId();

    $facturas = \App\Models\Factura::query()
        ->where('empresa_id', $empresaId)
        ->where('cliente_id', $clienteId)
        ->whereIn('estado_pago', ['pendiente', 'parcial', 'vencida'])
        ->where(function ($q) {
            $q->whereNull('devuelta_total')
              ->orWhere('devuelta_total', false);
        })
        ->orderByDesc('id')
        ->get();

    $this->carteraClienteId = $clienteId;

    // Calcula el saldo real de cada factura
    $this->carteraFacturas = $facturas->map(function ($f) {
        $totalOriginal = (float) $f->detalles()->sum(\DB::raw('precio * cantidad'));
        $totalDevuelto = (float) $f->detalles()->sum(\DB::raw('precio * devuelto_cantidad'));
        $totalPagado   = (float) $f->pagos()->sum('monto');
        $vence         = max(0, $totalOriginal - $totalDevuelto - $totalPagado);

        return [
            'id'       => $f->id,
            'numero_visual' => $f->numero_visual,
            'fecha'    => $f->fecha,
            'total'    => $totalOriginal,
            'devuelto' => $totalDevuelto,
            'pagado'   => $totalPagado,
            'vence'    => $vence,
            'estado'   => $f->estado_pago,
        ];
    })->values()->all();

    // El total del cliente es la suma de los saldos reales (vence) de todas sus facturas pendientes:
    $this->carteraTotalCliente = array_sum(array_column($this->carteraFacturas, 'vence'));
}
 public function updatedAbonoMonto($value): void
    {
        // Soporta 2.000 / 2,000 / "2000"
        $n = (int) preg_replace('/\D/', '', (string) $value);
        if ($n < 0) $n = 0;
        if ($n > (int) $this->abonoSaldo) $n = (int) $this->abonoSaldo;
        $this->abonoMonto = (float) $n;
    }

// âœ… NO imprime aquÃ­. Solo registra el abono y refresca la UI.
public function abonarEnCartera(int $facturaId, array $data): void
{
    $empresaId = $this->getEmpresaId();

    $factura = Factura::where('empresa_id', $empresaId)
        ->where('id', $facturaId)
        ->lockForUpdate()
        ->firstOrFail();

    $monto   = (float) ($data['monto'] ?? 0);
    $medio   = (string) ($data['medio'] ?? 'efectivo');
    $nota    = trim((string) ($data['nota'] ?? 'Abono cartera'));
    $transferObs = ($medio === 'transferencia') ? trim((string) ($data['transferencia_obs'] ?? '')) : null;

    if ($monto <= 0) {
        $this->dispatch('error', 'El monto del abono debe ser mayor a cero.');
        return;
    }
    if ($monto > (float) $factura->saldo) {
        $this->dispatch('error', 'El abono no puede superar el saldo ($'.number_format($factura->saldo,0,',','.').').');
        return;
    }

    DB::beginTransaction();
    try {
        if ($medio === 'transferencia' && $transferObs) {
            $nota = $nota ? ($nota.' | Transferencia: '.$transferObs) : ('Transferencia: '.$transferObs);
        }

        $pago = $factura->registrarAbono(
            monto: $monto,
            medio: $medio,
            nota : $nota,
            userId: auth()->id(),
            transferenciaObs: $transferObs
        );

        // Refresca el modelo para obtener el saldo actualizado
        $factura->refresh();

        // ðŸ'‡ AquÃ­ va tu bloque para actualizar el estado correctamente:
        $totalOriginal = (float) $factura->detalles()->sum(\DB::raw('precio * cantidad'));
        $totalDevuelto = (float) $factura->detalles()->sum(\DB::raw('precio * devuelto_cantidad'));
        $totalPagado   = (float) $factura->pagos()->sum('monto');
        $vence         = max(0, $totalOriginal - $totalDevuelto - $totalPagado);

        if ($vence <= 0) {
            if ($totalPagado >= ($totalOriginal - $totalDevuelto) && !$factura->devuelta_total) {
                $factura->estado_pago = 'pagada';
                $factura->fecha_pago = now();
            } elseif ($factura->devuelta_total || $totalDevuelto >= $totalOriginal) {
                $factura->estado_pago = 'pagada';
                $factura->fecha_pago = null;
            } else {
                $factura->estado_pago = 'parcial';
            }
            $factura->save();
        }

        DB::commit();

        // ðŸ”„ Refresca UI (sin imprimir)
        $this->abonoTransferObs = '';
        $this->abonoSaldo       = (float) $factura->saldo;
        $this->abonoMonto       = $this->abonoSaldo;

        $this->cargarCarteraCliente($factura->cliente_id);

        $this->carteraRefreshKey = ($this->carteraRefreshKey ?? 0) + 1;
        $this->dispatch('$refresh');

        // ðŸ–¨ï¸ Imprimir ticket de abono
        $url = route('abono.imprimir', $pago->id);
        $this->dispatch('open-print', url: $url);

        $this->dispatch('success', 'Abono registrado correctamente.');
    } catch (\Throwable $e) {
        DB::rollBack();
        $this->dispatch('error', 'No se pudo registrar el abono: '.$e->getMessage());
    }
}


public function abrirFacturaLectura(int $id): void
{
    $empresaId = $this->getEmpresaId();
    $f = \App\Models\Factura::where('empresa_id',$empresaId)->findOrFail($id);

    $this->verFacturaId    = $f->id;
    $this->verFacturaSaldo = (float) $f->saldo; // ðŸ'ˆ saldo disponible para el botÃ³n
    $this->mostrarModalFactura = true;
}

// âœ… AquÃ­ SÃ decide imprimir si quedÃ³ saldada, y asegura que "Ver" NO se abra.
public function confirmarAbonoConValor($montoRaw): void
{
    if (!$this->abonoFacturaId) return;

    $monto = (float) preg_replace('/\D/', '', (string) $montoRaw);

    $empresaId = $this->getEmpresaId();
    $f = \App\Models\Factura::where('empresa_id', $empresaId)
        ->lockForUpdate()
        ->findOrFail($this->abonoFacturaId);

    if ($monto <= 0) { $this->dispatch('error','Monto invÃ¡lido.'); return; }
    if ($monto > (float) $f->saldo) $monto = (float) $f->saldo;

    $medio = in_array($this->abonoMedio, ['efectivo','transferencia','otro'], true)
        ? $this->abonoMedio
        : 'efectivo';

    $transferObs = $medio === 'transferencia'
        ? trim((string) $this->abonoTransferObs)
        : null;

    // Registra abono (sin imprimir adentro)
    $pago = $f->registrarAbono(
        monto: $monto,
        medio: $medio,
        nota : 'Abono desde Cartera',
        userId: auth()->id(),
        transferenciaObs: $transferObs
    );


    // Refresca desde DB
    $f->refresh();

    // Cierra el modal de abono y evita que quede abierto "Ver"
    $this->mostrarModalAbono   = false;
    $this->abonoTransferObs    = '';

    $this->mostrarModalFactura = false; // por si estaba abierto
    $this->verFacturaId        = null;  // IMPORTANT: null, no false

    // Refresca la lista
    $this->cargarCarteraCliente($f->cliente_id);
    $this->carteraRefreshKey = ($this->carteraRefreshKey ?? 0) + 1;
    $this->dispatch('$refresh');

    // ðŸ–¨ï¸ Imprime ticket de abono SIEMPRE
    $url = route('abono.imprimir', $pago->id);
    $this->dispatch('open-print', url: $url);
    $this->dispatch('success','Abono registrado.');
}




    public function abrirHistorial(): void
{
    // Rango por defecto: Ãºltimos 30 dÃ­as
    $this->histDesde = now()->subDays(30)->toDateString();
    $this->histHasta = now()->toDateString();
    $this->histBuscar = '';
    $this->histSoloCliente = false;

    $this->cargarHistorial();
    $this->mostrarModalHistorial = true;
}

public function cerrarHistorial(): void
{
    $this->mostrarModalHistorial = false;
}

public function updatedHistDesde(): void { $this->cargarHistorial(); }
public function updatedHistHasta(): void { $this->cargarHistorial(); }
public function updatedHistBuscar(): void { $this->cargarHistorial(); }
public function updatedHistSoloCliente(): void { $this->cargarHistorial(); }

public function cargarHistorial(): void
{
    $empresaId = $this->getEmpresaId();

    $q = \App\Models\Factura::query()
        ->with('cliente')
        ->where('empresa_id', $empresaId)
        ->where('tipo_pago', 'credito')
        // pagadas o saldo 0
        ->where(function ($w) {
            $w->where('estado_pago', 'pagada')
              ->orWhere('saldo', 0);
        });

    // Solo cliente seleccionado (opcional)
    if ($this->histSoloCliente && $this->carteraClienteId) {
        $q->where('cliente_id', $this->carteraClienteId);
    }

    // Filtro por fechas usando fecha_pago si existe, de lo contrario fecha
    if ($this->histDesde) {
        $desde = $this->histDesde;
        $q->where(function ($w) use ($desde) {
            $w->whereDate('fecha_pago', '>=', $desde)
              ->orWhere(function ($x) use ($desde) {
                  $x->whereNull('fecha_pago')->whereDate('fecha', '>=', $desde);
              });
        });
    }
    if ($this->histHasta) {
        $hasta = $this->histHasta;
        $q->where(function ($w) use ($hasta) {
            $w->whereDate('fecha_pago', '<=', $hasta)
              ->orWhere(function ($x) use ($hasta) {
                  $x->whereNull('fecha_pago')->whereDate('fecha', '<=', $hasta);
              });
        });
    }

    // BÃºsqueda por #factura o nombre de cliente
    if ($this->histBuscar !== '') {
        $term = trim($this->histBuscar);
        $q->where(function ($w) use ($term) {
            if (is_numeric($term)) {
                $w->orWhere('id', (int) $term);
            }
            $w->orWhereHas('cliente', function ($c) use ($term) {
                $c->where('nombre', 'like', '%'.$term.'%');
            });
        });
    }

    // Totales (clonar para no duplicar filtros)
    $sumQ = (clone $q);
    $cntQ = (clone $q);

    $this->historialTotal = (float) $sumQ->sum('total');
    $this->historialCount = (int) $cntQ->count();

    // Datos (limite razonable para no reventar UI)
    $rows = $q->orderByRaw('COALESCE(fecha_pago, fecha) DESC')
              ->limit(200)
              ->get();

    $this->historialFacturas = $rows->map(function ($f) {
        return [
            'id'         => $f->id,
            'numero_visual' => $f->numero_visual,
            'fecha_pago' => ($f->fecha_pago ? \Carbon\Carbon::parse($f->fecha_pago)->format('Y-m-d') : \Carbon\Carbon::parse($f->fecha)->format('Y-m-d')),
            'cliente'    => optional($f->cliente)->nombre ?? 'Consumidor final',
            'total'      => (float) $f->total,
            'saldo'      => (float) $f->saldo,
            'estado'     => $f->estado_pago,
        ];
    })->values()->all();

    $this->historialRefreshKey++;
}

public function imprimirFacturaActual(): void
{
    if (!$this->verFacturaId) return;

    // Usa tu ruta de impresiÃ³n. Si en tu proyecto no existe 'factura.imprimir',
    // cambia por route('factura.ver', $this->verFacturaId)
    $url = route('factura.imprimir', $this->verFacturaId);

    // Dispara evento al front para abrir ventana de impresiÃ³n
    $this->dispatch('open-print', url: $url);
}
public function pagarEImprimir(int $facturaId, array $data = [])
{
    $empresaId = $this->getEmpresaId();

    // === ValidaciÃ³n rÃ¡pida de entrada
    $medio = (string)($data['medio'] ?? 'efectivo');
    if (!in_array($medio, ['efectivo','transferencia','otro'], true)) {
        $medio = 'efectivo';
    }
    $transferObs = $medio === 'transferencia'
        ? trim((string)($data['transferencia_obs'] ?? ''))
        : null;
    $monto = (float)($data['monto'] ?? 0);
    if ($monto <= 0) {
        throw new \Exception('Monto invÃ¡lido.');
    }

    DB::beginTransaction();
    try {
        /** @var \App\Models\Factura $factura */
        $factura = \App\Models\Factura::where('empresa_id', $empresaId)
            ->lockForUpdate()
            ->findOrFail($facturaId);

        if ($monto > (float)$factura->saldo) {
            $monto = (float)$factura->saldo; // clamp
        }

        // Registra el abono
        $nota = 'Pago total desde Cartera';
        if ($medio === 'transferencia' && $transferObs) {
            $nota .= ' | Transferencia: '.$transferObs;
        }

        $factura->registrarAbono(
            monto:  $monto,
            medio:  $medio,
            nota:   $nota,
            userId: auth()->id(),
            transferenciaObs: $transferObs
        );

        // Recalcular estado/saldo por si quedÃ³ en 0
        $factura->refresh();
        $this->cargarCarteraCliente($factura->cliente_id);

        DB::commit();

        // Cierra modal de cartera y fuerza re-render
        $this->mostrarModalCartera = false;
        $this->carteraRefreshKey   = ($this->carteraRefreshKey ?? 0) + 1;

        // Devuelve URL de impresiÃ³n (usa tu ruta real)
        return [
            'ok'        => true,
            'facturaId' => $factura->id,
            'print_url' => route('factura.imprimir', $factura->id), // <-- ajusta a tu ruta
        ];
    } catch (\Throwable $e) {
        DB::rollBack();
        $this->dispatch('error', 'No se pudo registrar el pago: '.$e->getMessage());
        throw $e;
    }
}

 

    // Dispara evento para mostrar modal de abrir caja (frontend)
    public function abrirCajaModal()
{
if (
    !auth()->user()->hasRole('cajero') &&
    !auth()->user()->hasRole('admin_empresa')
) {
    abort(403);
}

    $this->montoApertura = 0;
    $this->mostrarModalAbrirCaja = true;
}

    // Dispara evento para confirmar cierre de caja con resumen de ventas
   public function cerrarCajaModal()
{
    if (
    !auth()->user()->hasRole('cajero') &&
    !auth()->user()->hasRole('admin_empresa')
) {
    abort(403);
}

    $this->montoCierre = 0;
    $this->resumenCaja = $this->calcularResumenCaja();
    $this->mostrarModalCerrarCaja = true;
}

    // Cierra la caja con el monto que recibe desde el frontend
    public function cerrarCajaConMonto($montoCierre)
    {
        $this->cargarCajaActual();
        if (! $this->cajaActual) {
            $this->dispatch('ui-caja-no-abierta');
            return;
        }

        $this->cajaActual->monto_cierre = floatval($montoCierre);
        $this->cajaActual->closed_at = now();
        $this->cajaActual->estado = 'cerrada';
        $this->cajaActual->save();

        $this->cargarCajaActual(); // refrescar estado
        $this->dispatch('ui-caja-cerrada', ['monto_cierre' => $this->cajaActual->monto_cierre]);
    }

    // Abre caja con monto de apertura (desde frontend)
    public function abrirCajaConMonto($montoApertura)
    {
        $user = auth()->user();
        if (! $user) {
            $this->dispatch('ui-error', ['msg' => 'Usuario no identificado']);
            return;
        }

        $empresaId = $this->getEmpresaId();

        // evita abrir si ya hay caja abierta
        $this->cargarCajaActual();
        if ($this->cajaActual) {
            $this->dispatch('ui-error', ['msg' => 'Ya tienes caja abierta']);
            return;
        }

        $caja = Caja::create([
            'user_id' => $user->id,
            'empresa_id' => $empresaId,
            'monto_apertura' => floatval($montoApertura),
            'opened_at' => now(),
            'estado' => 'abierta',
        ]);

        $this->cajaActual = $caja;
        $this->cajaEstado = 'abierta';
        $this->dispatch('ui-caja-abierta', ['monto_apertura' => $caja->monto_apertura]);
    }

    // Verificar antes de facturar (ya la usas; asegÃºrate de llamarla)
    private function verificarCajaAbierta(): bool
    {
        $this->cargarCajaActual();
        if ($this->cajaEstado !== 'abierta' || ! $this->cajaActual) {
            $this->dispatch('ui-caja-cerrada-advertencia');
            return false;
        }
        return true;
    }
public function confirmarAbrirCaja()
{
    $this->cargarCajaActual();

    if ($this->cajaActual) {
        $this->dispatch('error', 'Ya tienes una caja abierta.');
        return;
    }

    if (
    !auth()->user()->hasAnyRole([
        'cajero',
        'admin_empresa'
    ])
) {
    abort(403);
}

    $this->validate([
        'montoApertura' => 'required|numeric|min:0',
    ]);

    \App\Models\Caja::create([
        'user_id'        => auth()->id(),
        'empresa_id'     => $this->getEmpresaId(),
        'monto_apertura' => $this->montoApertura,
        'opened_at'      => now(),
        'estado'         => 'abierta',
    ]);

    $this->mostrarModalAbrirCaja = false;
    $this->reset('montoApertura');

    // âœ… actualizar estado + rerender
    $this->cargarCajaActual();
    
    $this->dispatch('caja-abierta');             // opcional: por si escuchas fuera
}


public function confirmarCerrarCaja()
{
    $this->resumenCaja = $this->calcularResumenCaja();
    $empresaId = $this->getEmpresaId();

    $caja = \App\Models\Caja::where('user_id', auth()->id())
        ->where(fn ($query) =>
            $query->where('empresa_id', $empresaId)
                ->orWhereNull('empresa_id')
        )
        ->where('estado', 'abierta')
        ->latest('opened_at')
        ->first();

    if ($caja) {
        // Si comparas contra total contado del dÃ­a:
        $this->diferenciaCaja = ($this->montoCierre ?? 0) - (float)($this->resumenCaja['efectivo'] ?? 0);

        $caja->update([
            'empresa_id'    => $empresaId,
            'monto_cierre' => $this->montoCierre,
            'closed_at'    => now(),
            'estado'       => 'cerrada',
        ]);
    }

    $this->mostrarModalCerrarCaja = false;
    $this->reset('montoCierre');

    // âœ… actualizar estado + rerender
    $this->cargarCajaActual();
  
    $this->dispatch('$refresh');
    $this->dispatch('caja-cerrada', diferencia: $this->diferenciaCaja);

    // si ademÃ¡s imprimes:
    if ($caja) {
        $this->dispatch('imprimir-cierre-caja', $caja->id);
    }
}

public function abrirMovimientoCajaModal(string $tipo = 'salida'): void
{
    if (! auth()->user()->hasAnyRole(['cajero', 'admin_empresa'])) {
        abort(403);
    }

    if (! $this->verificarCajaAbierta()) {
        return;
    }

    $this->movimientoCaja = [
        'tipo' => $tipo === 'entrada' ? 'entrada' : 'salida',
        'categoria' => $tipo === 'entrada' ? 'Base adicional' : 'Otros',
        'descripcion' => '',
        'monto' => 0,
        'metodo_pago' => 'Efectivo',
        'observacion' => '',
    ];

    $this->mostrarModalMovimientoCaja = true;
}

public function registrarMovimientoCaja(): void
{
    if (! auth()->user()->hasAnyRole(['cajero', 'admin_empresa'])) {
        abort(403);
    }

    $this->cargarCajaActual();

    if (! $this->cajaActual) {
        $this->dispatch('error', 'Debes tener una caja abierta para registrar movimientos.');
        return;
    }

    $data = $this->validate([
        'movimientoCaja.tipo' => ['required', 'in:entrada,salida'],
        'movimientoCaja.categoria' => ['required', 'string', 'max:100'],
        'movimientoCaja.descripcion' => ['required', 'string', 'max:255'],
        'movimientoCaja.monto' => ['required', 'numeric', 'min:1'],
        'movimientoCaja.metodo_pago' => ['required', 'in:Efectivo,Transferencia,Nequi,Otro'],
        'movimientoCaja.observacion' => ['nullable', 'string'],
    ])['movimientoCaja'];

    $empresaId = $this->getEmpresaId();
    $ultimo = Gasto::where('empresa_id', $empresaId)->max('id_gasto');

    Gasto::create([
        'id_gasto' => $ultimo ? $ultimo + 1 : 1,
        'empresa_id' => $empresaId,
        'tipo' => $data['tipo'],
        'categoria' => $data['categoria'],
        'descripcion' => $data['descripcion'],
        'monto' => $data['monto'],
        'fecha' => now()->toDateString(),
        'metodo_pago' => $data['metodo_pago'],
        'observacion' => $data['observacion'] ?: $data['descripcion'],
        'created_by' => auth()->id(),
        'caja_id' => $this->cajaActual->id,
    ]);

    $this->mostrarModalMovimientoCaja = false;
    $this->resumenCaja = $this->calcularResumenCaja();
    $this->dispatch('success', 'Movimiento de caja registrado.');
}

public function cargarCajaActual()
{
    $empresaId = $this->getEmpresaId();

    $caja = \App\Models\Caja::where('user_id', auth()->id())
        ->where(fn ($query) =>
            $query->where('empresa_id', $empresaId)
                ->orWhereNull('empresa_id')
        )
        ->where('estado', 'abierta')
        ->latest('opened_at')
        ->first();

    if ($caja) {
        $this->cajaEstado = 'abierta';
        $this->cajaActual = $caja;
    } else {
        $this->cajaEstado = 'cerrada';
        $this->cajaActual = null;
    }
}


public function calcularResumenCaja(?int $userId = null, ?int $empresaId = null, ?Carbon $inicio = null, ?Carbon $fin = null)
{
    $userId = $userId ?? auth()->id();
    $empresaId = $empresaId ?? $this->getEmpresaId();
    $inicio = $inicio ?? now()->startOfDay();
    $fin = $fin ?? now()->endOfDay();

    // ----- VENTAS DEL DÃA -----
    $qVentas = \App\Models\Factura::query()
        ->where('empresa_id', $empresaId)
        ->where('user_id', $userId)
        ->whereBetween('fecha', [$inicio, $fin]);

    // Ventas contado por medio
    $ventasContadoEfectivo      = (clone $qVentas)->where('tipo_pago','contado')->where('medio_pago','efectivo')->sum('total');
    $ventasContadoTransferencia = (clone $qVentas)->where('tipo_pago','contado')->where('medio_pago','transferencia')->sum('total');

    // Ventas a crÃ©dito
    $ventasCredito = (clone $qVentas)->where('tipo_pago','credito')->sum('total');

    // ----- COBROS EN CARTERA (ABONOS / PAGOS) -----
    // OJO: usamos FacturaPago y filtramos empresa con whereHas('factura')
    $qPagosCredito = \App\Models\FacturaPago::query()
    ->where('user_id', $userId)
    ->whereBetween('fecha', [$inicio, $fin])
    ->whereHas('factura', function ($q) use ($empresaId) {
        $q->where('empresa_id', $empresaId)
          ->where('tipo_pago', 'credito');   // ðŸ'ˆ clave para no contar pagos de contado
    });

$carteraEfectivo      = (clone $qPagosCredito)->where('medio_pago','efectivo')->sum('monto');
$carteraTransferencia = (clone $qPagosCredito)->where('medio_pago','transferencia')->sum('monto');
$carteraOtro          = (clone $qPagosCredito)->where('medio_pago','otro')->sum('monto'); // opcional

    $qMovimientosCaja = Gasto::query()
        ->where('empresa_id', $empresaId)
        ->where('created_by', $userId)
        ->whereDate('fecha', '>=', $inicio->toDateString())
        ->whereDate('fecha', '<=', $fin->toDateString());

    $entradasEfectivo = (clone $qMovimientosCaja)
        ->where('tipo', 'entrada')
        ->where('metodo_pago', 'Efectivo')
        ->sum('monto');

    $salidasEfectivo = (clone $qMovimientosCaja)
        ->where('tipo', 'salida')
        ->where('metodo_pago', 'Efectivo')
        ->sum('monto');

    $entradasTransferencia = (clone $qMovimientosCaja)
        ->where('tipo', 'entrada')
        ->whereIn('metodo_pago', ['Transferencia', 'Nequi'])
        ->sum('monto');

    $salidasTransferencia = (clone $qMovimientosCaja)
        ->where('tipo', 'salida')
        ->whereIn('metodo_pago', ['Transferencia', 'Nequi'])
        ->sum('monto');

    // ----- DOMICILIOS (fees collected by company, to pay domiciliario) -----
    $qDom = \App\Models\Factura::query()
        ->where('empresa_id', $empresaId)
        ->where('user_id', $userId)
        ->where('tipo_pedido', 'domicilio')
        ->where('costo_empaque', '>', 0)
        ->whereBetween('fecha', [$inicio, $fin]);

    $domCobradoEfectivo      = (clone $qDom)->where('medio_pago', 'efectivo')->sum('costo_empaque');
    $domCobradoTransferencia = (clone $qDom)->where('medio_pago', 'transferencia')->sum('costo_empaque');
    $domCobradoTotal         = $domCobradoEfectivo + $domCobradoTransferencia;

    // ----- DEVOLUCIONES -----
    // Regla: SIEMPRE restan al EFECTIVO del dÃ­a y al TOTAL CONTADO
    $devolucionesConPago = \App\Models\Devolucion::query()
    ->where('empresa_id', $empresaId)
    ->where('user_id', $userId)
    ->whereBetween('fecha', [$inicio, $fin])
    ->whereHas('factura.pagos')   // ðŸ'ˆ la factura tiene pagos (cualquier medio)
    ->sum('total');

// B) Devoluciones de facturas SIN pagos/abonos (NO afectan efectivo ni contado)
$devolucionesSinPago = \App\Models\Devolucion::query()
    ->where('empresa_id', $empresaId)
    ->where('user_id', $userId)
    ->whereBetween('fecha', [$inicio, $fin])
    ->whereDoesntHave('factura.pagos')  // ðŸ'ˆ la factura no tiene pagos
    ->sum('total');

$devolucionesDia = $devolucionesConPago + $devolucionesSinPago; // informativo

// ----- TOTALES DE FLUJO DE CAJA -----
// Efectivo del dÃ­a = ventas contado (efectivo) + cobros cartera (efectivo) - devoluciones de facturas CON pago
$efectivo = ($ventasContadoEfectivo + $carteraEfectivo + $entradasEfectivo) - $devolucionesConPago - $salidasEfectivo;

    // Transferencia del dÃ­a = ventas contado (transferencia) + cobros cartera (transferencia)
    $transferencia = $ventasContadoTransferencia + $carteraTransferencia + $entradasTransferencia - $salidasTransferencia;

    // Total contado (lo que comparas contra "Monto de cierre")
    $totalContado = $efectivo + $transferencia;
    $montoApertura = (float) ($this->cajaActual?->monto_apertura ?? 0);
    $efectivoEsperado = $montoApertura + $efectivo;

    // Total de ventas (informativo: contado + crÃ©dito)
    $totalVentas = $ventasContadoEfectivo + $ventasContadoTransferencia + $ventasCredito;

    return [
        // Desglose ventas contado
        'ventas_contado_efectivo'       => $ventasContadoEfectivo,
        'ventas_contado_transferencia'  => $ventasContadoTransferencia,
        'ventas_credito'                => $ventasCredito,

        // Cobros cartera
        'cartera_efectivo'              => $carteraEfectivo,
        'cartera_transferencia'         => $carteraTransferencia,
        'cartera_otro'                  => $carteraOtro,

        'entradas_efectivo'             => $entradasEfectivo,
        'salidas_efectivo'              => $salidasEfectivo,
        'entradas_transferencia'        => $entradasTransferencia,
        'salidas_transferencia'         => $salidasTransferencia,

        // Devoluciones (siempre restadas al efectivo y al contado)
        'devoluciones_con_pago' => $devolucionesConPago,     // ðŸ'ˆ restadas al EFECTIVO y al CONTADO
    'devoluciones_sin_pago' => $devolucionesSinPago,     // ðŸ'ˆ no afectan flujo
    'devoluciones'          => $devolucionesDia,     

        // Totales de flujo
        'efectivo'                      => $efectivo,
        'monto_apertura'                => $montoApertura,
        'efectivo_esperado'             => $efectivoEsperado,
        'transferencia'                 => $transferencia,
        'total_contado'                 => $totalContado,

        // Totales informativos
        'total_ventas'                  => $totalVentas,
        'total_ventas_sin_devoluciones'=> $totalVentas - $devolucionesDia,

        // Domicilios cobrados por empresa (para liquidar con domiciliario)
        'dom_cobrado_efectivo'      => $domCobradoEfectivo,
        'dom_cobrado_transferencia' => $domCobradoTransferencia,
        'dom_cobrado_total'         => $domCobradoTotal,
    ];
}
public function updatedMontoCierre($value)
{
    $efectivoEsperado = $this->resumenCaja['efectivo_esperado'] ?? ($this->resumenCaja['efectivo'] ?? 0);
    $this->diferenciaCaja = floatval($value) - floatval($efectivoEsperado);
}

public function cerrarCajaAutomaticaSiEsFinDeDia()
{
    $this->cargarCajaActual();

    if ($this->cajaActual && $this->cajaActual->opened_at->format('Y-m-d') === now()->format('Y-m-d')) {
        // Si la caja sigue abierta al final del dÃ­a, ciÃ©rrala automÃ¡ticamente
        if ($this->cajaActual->estado === 'abierta' && now()->isEndOfDay()) {
            $this->cajaActual->update([
                'monto_cierre' => $this->cajaActual->monto_apertura,
                'closed_at'    => now(),
                'estado'       => 'cerrada',
            ]);
            $this->cargarCajaActual();
        }
    }
}

public function uiCreditoActual(): array
{
    // reutiliza el computed que ya hiciste
    return $this->clienteCreditoInfo;
}
   

   

    // ===== MÉTODOS DE MESA =====

    private function recalcularTotalOrden(int $ordenId): void
    {
        $total = \App\Models\OrdenMesaItem::where('orden_mesa_id', $ordenId)->sum('subtotal');
        \App\Models\OrdenMesa::where('id', $ordenId)->update(['total' => $total]);
    }

    private function obtenerOrdenMesaActiva(int $empresaId): \App\Models\OrdenMesa
    {
        // Buscar orden activa (abierta o en preparación)
        $existente = \App\Models\OrdenMesa::where('empresa_id', $empresaId)
            ->where('mesa_id', $this->mesaId)
            ->whereIn('estado', ['abierta', 'en_preparacion'])
            ->latest()
            ->first();

        if ($existente) return $existente;

        // Crear nueva si no existe
        return \App\Models\OrdenMesa::create([
            'empresa_id' => $empresaId,
            'mesa_id'    => $this->mesaId,
            'estado'     => 'abierta',
            'usuario_id' => auth()->id(),
            'abierta_en' => now(),
            'total'      => 0,
        ]);
    }

    private function cargarCarritoDesdeOrdenMesa(int $empresaId): void
    {
        $orden = \App\Models\OrdenMesa::where('empresa_id', $empresaId)
            ->where('mesa_id', $this->mesaId)
            ->whereIn('estado', ['abierta', 'en_preparacion'])
            ->with('items.producto')
            ->latest()
            ->first();

        if (! $orden) return;

        foreach ($orden->items as $item) {
            $producto = $item->producto;
            if (! $producto) continue;

            $key = (string) ($item->id);
            $precio = (float) $item->precio_unitario;
            $cant   = (float) $item->cantidad;

            $this->carrito[$key] = [
                'uuid'             => $key,
                'id_producto'      => $producto->id_producto,
                'nombre'           => $this->textoUtf8($producto->descripcion_larga),
                'cantidad'         => $cant,
                'precio'           => $precio,
                'nuevo_precio'     => $precio,
                'descuento'        => 0,
                'iva_venta'        => (float) ($producto->iva_venta ?? 0),
                'utilidad1'        => 0,
                'costo'            => 0,
                'costo_iva'        => 0,
                'utilidad_nueva'   => 0,
                'total'            => round($precio * $cant, 2),
                'existencias'      => (float) ($producto->existencias ?? 0),
                'porciones_receta' => null,
                'id_unidad_de_medida' => (int) ($producto->id_unidad_de_medida ?? 1),
                'vende_por'        => (string) ($producto->vende_por ?? 'unidad'),
                'permite_fraccion' => (bool) ($producto->permite_fraccion ?? false),
                'permite_decimal'  => $producto->permiteCantidadDecimal(),
                'enviado_cocina'   => in_array($item->estado_cocina, ['enviado', 'preparando', 'listo']),
            ];
        }
    }

    #[On('mesa-enviar-cocina-confirmado')]
    public function mesaEnviarCocinaConfirmado(array $data = []): void
    {
        $tipoPedido     = $data['tipo_pedido'] ?? 'mesa';
        $costoDomicilio = (float)($data['costo_domicilio'] ?? 0);
        $costoEmpaque   = (float)($data['costo_empaque'] ?? 0);
        $domNombre      = $data['dom_nombre'] ?? null;
        $domTelefono    = $data['dom_telefono'] ?? null;
        $domDireccion   = $data['dom_direccion'] ?? null;
        $domObs         = $data['dom_observaciones'] ?? null;
        $this->mesaEnviarACocina($tipoPedido, $costoDomicilio, $costoEmpaque, $domNombre, $domTelefono, $domDireccion, $domObs);
    }

    public function mesaEnviarACocina(
        string $tipoPedido = 'mesa',
        float $costoDomicilio = 0,
        float $costoEmpaque = 0,
        ?string $domNombre = null,
        ?string $domTelefono = null,
        ?string $domDireccion = null,
        ?string $domObservaciones = null
    ): void
    {
        if (! $this->mesaId) return;

        $empresaId = $this->getEmpresaId();

        $orden = \App\Models\OrdenMesa::where('empresa_id', $empresaId)
            ->where('mesa_id', $this->mesaId)
            ->whereIn('estado', ['abierta', 'en_preparacion'])
            ->latest()->first();

        if (! $orden) {
            $this->dispatch('error', 'No hay productos para enviar.');
            return;
        }

        $pendientes = $orden->items()->where('estado_cocina', 'pendiente')->count();
        if ($pendientes === 0) {
            $this->dispatch('error', 'No hay productos nuevos para enviar a cocina.');
            return;
        }

        // Marcar pendientes como enviados
        $orden->items()->where('estado_cocina', 'pendiente')
            ->update(['estado_cocina' => 'enviado', 'enviado_cocina_en' => now()]);

        // Asignar número de orden del día si aún no tiene
        $numeroCocina = $orden->numero_cocina_dia;
        if (! $numeroCocina) {
            $hoy = now()->toDateString();
            $ultimo = \App\Models\OrdenMesa::where('empresa_id', $empresaId)
                ->whereDate('created_at', $hoy)
                ->whereNotNull('numero_cocina_dia')
                ->max('numero_cocina_dia');
            $numeroCocina = ($ultimo ?? 0) + 1;
        }

        // Solo las observaciones de cocina van a la pantalla de cocina
        $obsBase = trim($this->observacionesPrefactura ?? '');
        $this->ordenDomObservaciones = $domObservaciones ?? '';

        $orden->update([
            'estado'               => 'en_preparacion',
            'observaciones'        => $obsBase,
            'numero_cocina_dia'    => $numeroCocina,
            'tipo_pedido'          => $tipoPedido,
            'costo_empaque'        => $costoDomicilio + $costoEmpaque,
            'cliente_id'           => $this->clienteId ? \App\Models\Actor::where(function($q){ $q->where('id', $this->clienteId)->orWhere('id_clip_pro', $this->clienteId); })->value('id') : null,
            'dom_nombre'           => $domNombre,
            'dom_telefono'         => $domTelefono,
            'dom_direccion'        => $domDireccion,
            'dom_observaciones'    => $domObservaciones,
            'dom_costo_domicilio'  => $costoDomicilio,
            'dom_costo_desechables'=> $costoEmpaque,
        ]);

        // Marcar en el carrito local como enviado
        foreach ($this->carrito as $key => $item) {
            if (empty($item['enviado_cocina'])) {
                $this->carrito[$key]['enviado_cocina'] = true;
            }
        }

        $this->recalcularTotalOrden($orden->id);
        $this->actualizarTotales();
        // Actualizar propiedades reactivas de estado
        $this->ordenEstadoActual  = 'en_preparacion';
        $this->ordenTipoPedido    = $tipoPedido;
        $this->ordenCostoEmpaque  = $costoDomicilio + $costoEmpaque;
        $this->ordenDomNombre           = $domNombre;
        $this->ordenDomTelefono         = $domTelefono;
        $this->ordenDomDireccion        = $domDireccion;
        $this->ordenDomCostoDomicilio   = $costoDomicilio;
        $this->ordenDomCostoDesechables = $costoEmpaque;
        $this->dispatch('success', '📤 Comanda enviada a cocina');
    }

    public function mesaPreFacturar(): void
    {
        if (empty($this->carrito)) {
            $this->dispatch('error', 'Agregue productos a la comanda antes de facturar.');
            return;
        }
        // Desmarcar enviado_cocina para que el total se calcule con todos los ítems
        foreach ($this->carrito as $k => $item) {
            $this->carrito[$k]['enviado_cocina'] = false;
        }
        $this->confirmarFacturar();
    }

    public function mesaLiberar(): void
    {
        if (! $this->mesaId) return;
        if (auth()->user()->hasRole('mesero') && ! auth()->user()->hasAnyRole(['cajero','admin_empresa','vendedor'])) return;

        // Solo cancelar la orden activa, no las cuentas en espera (estado lista)
        \App\Models\OrdenMesa::where('mesa_id', $this->mesaId)
            ->whereIn('estado', ['abierta', 'en_preparacion'])
            ->update(['estado' => 'cancelada', 'cerrada_en' => now()]);

        // Liberar la mesa solo si no quedan cuentas en espera
        $cuentasEnEspera = \App\Models\OrdenMesa::where('mesa_id', $this->mesaId)
            ->where('estado', 'lista')->count();
        if ($cuentasEnEspera === 0) {
            \App\Models\Mesa::where('id', $this->mesaId)->update(['estado' => 'libre']);
        }

        $this->limpiarCarrito();
        $this->redirect(route('pos'));
    }

    public function mesaEnEspera(): void
    {
        if (! $this->mesaId) return;
        if (auth()->user()->hasRole('mesero') && ! auth()->user()->hasAnyRole(['cajero','admin_empresa','vendedor'])) return;

        \App\Models\OrdenMesa::where('mesa_id', $this->mesaId)
            ->whereIn('estado', ['abierta', 'en_preparacion'])
            ->update(['estado' => 'lista']);

        // Mesa queda libre para nuevos clientes
        \App\Models\Mesa::where('id', $this->mesaId)->update(['estado' => 'libre']);

        $this->limpiarCarrito();
        $this->redirect(route('pos'));
    }


}
