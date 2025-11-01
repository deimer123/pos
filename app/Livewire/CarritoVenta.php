<?php
// filepath: c:\laragon\www\posapp\app\Livewire\CarritoVenta.php

namespace App\Livewire;

use App\Models\Actor;
use App\Models\Prefactura;
use App\Models\Product;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;
use Illuminate\Validation\Rule;
use App\Models\Factura;
use App\Models\FacturaDetalle;
use App\Models\FacturaPago;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Caja;
class CarritoVenta extends Component
{
    public $preciosBase               = [];
    public $carrito                   = [];
    public $mostrarModal              = false;
    public $clienteId                 = null;
    public $observaciones             = '';
    public $mostrarModalPrefacturas   = false;
    public $prefacturasDisponibles    = [];
    public $prefacturaSeleccionada    = null;
    public $detalleSeleccionado       = [];
    public $observacionesPrefactura   = '';
    public $search                    = '';
    public $clientes                  = [];
    public $mostrarModalClientes      = false;
    public $clienteSeleccionadoNombre = null;
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
    public $facturas = []; // colección de facturas
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
    public ?int $abonoFacturaId = null;
    public string $abonoMedio = 'efectivo';
    public $verFacturaSaldo = 0;
    public float $abonoSaldo = 0.0;
    public float $abonoMonto = 0.0;
    public string $abonoTransferObs = '';              // observación transferencia
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
    

    
    

    
    
    


    
    



   
    protected $listeners = [
        'productoAgregado'               => 'agregarProducto',
       'confirmar-guardar-prefactura'  => 'guardarPrefacturaConfirmada',      
        'agregarProductoAlCarrito'       => 'agregarProductoAlCarrito',
        'agregarManual'                  => 'agregarProductoManual',
        'limpiarCarrito',
        'borrar-prefactura-confirmada'   => 'borrarPrefacturaConfirmada',
        'reiniciar-prefactura'           => 'reiniciarPrefactura',
        
    ];

    public function handleProductoAgregado($data)
    {
        $idProducto = $data['id'] ?? $data;
        $this->agregarProducto($idProducto);
    }

    
    

    

    

    
    public function handleAgregarProductoAlCarrito($data)
    {
        $idProducto = $data['id'] ?? $data;
        $this->agregarProductoAlCarrito($idProducto);
    }

   
    private function getEmpresaId()
{
    $user = auth()->user();

    if ($user->hasRole('admin_empresa')) {
        return $user->id;
    }

    if ($user->hasRole('vendedor') && !empty($user->empresa_id)) {
        return $user->empresa_id;
    }

    return $user->id;
    return auth()->user()->empresa_id ?? null;
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
            'nuevoCliente.identificacion.required' => 'Debe ingresar una identificación.',
            'nuevoCliente.identificacion.unique'   => 'Este número de identificación ya está registrado.',
            'nuevoCliente.nombre.required'         => 'Debe ingresar un nombre.',
            'nuevoCliente.nombre.unique'           => 'El nombre ya existe.',
            'nuevoCliente.email.required'          => 'Debe ingresar un correo electrónico.',
            'nuevoCliente.email.email'             => 'Debe ingresar un correo válido.',
            'nuevoCliente.email.unique'            => 'Este correo ya está registrado.',
            'nuevoCliente.telefono.required'       => 'Debe ingresar un teléfono.',
            'nuevoCliente.departamento_id.required' => 'Seleccione un departamento.',
            'nuevoCliente.ciudad_id.required'      => 'Seleccione una ciudad.',
            'nuevoCliente.direccion.required'      => 'Ingrese una dirección.',
            'nuevoCliente.responsable_iva.required' => 'Indique si es responsable de IVA.',
            'nuevoCliente.regimen_tributario.required' => 'Seleccione un régimen tributario.',
            'nuevoCliente.tipo_persona.required'   => 'Seleccione un tipo de persona.',
        ];
    }



     public function mount()
{
    
    

    $empresaId = $this->getEmpresaId();

   if (request()->hasSession() && session()->has('carrito_guardado')) {
    $carritoGuardado = session('carrito_guardado');
    
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
        $cantidad    = intval($item['cantidad'] ?? 1);
        $precioCosto = floatval($producto->precio_costo ?? 0);
        $descuento   = floatval($item['descuento'] ?? 0);
                
        $this->carrito[$clave] = [
            'uuid'          => (string)($item['uuid'] ?? $clave),
            'id_producto'   => $producto->id_producto,
            'nombre'        => $item['nombre'] ?? $producto->descripcion_larga, // ✅ conserva nombre guardado
            'cantidad'      => $cantidad,
            'precio'        => $precioVenta,
            'nuevo_precio'  => $precioVenta,
            'descuento'     => $descuento,
            'iva_venta'     => floatval($producto->iva_venta ?? 0),
            'utilidad1'     => floatval($producto->utilidad1 ?? 0),
            'costo'         => $precioCosto,
            'utilidad_nueva'=> floatval($producto->utilidad1 ?? 0),
            'total'         => round($precioVenta * $cantidad, 2),
            'existencias'   => intval($producto->existencias ?? 0),
        ];
    }
}

if (request()->hasSession() && session()->has('observaciones_guardadas')) {
    $this->observacionesPrefactura = session('observaciones_guardadas');
}


    // Cargar clientes
    $this->clientes = Actor::where('tipo', 1)
        ->where('empresa_id', $empresaId)
        ->orderBy('nombre')
        ->get(['id', 'id_clip_pro', 'nombre']);

    if ($this->clienteId) {
        $cliente = $this->clientes->firstWhere('id_clip_pro', $this->clienteId);
        if ($cliente) {
            $this->clienteSeleccionadoNombre = $cliente->nombre;
        }
    }
    $this->asignarConsumidorFinalPorDefecto();
    $this->codigoCliente = Actor::max('id_clip_pro') + 1;
    $this->dispatch('limpiar-input-busqueda')->to('pos-productos');
    
    // ✅ CALCULAR TOTAL INICIAL DESPUÉS DE CARGAR EL CARRITO
    $this->actualizarTotales();
    $this->maxFechaFacturas = now()->toDateString();
    $this->minFechaFacturas = now()->subMonths(3)->toDateString();
    $this->fDesde = $this->maxFechaFacturas;
    $this->fHasta = $this->maxFechaFacturas;
    $this->cargarCajaActual();

    // Verificar si hay caja abierta de días anteriores
    if ($this->cajaActual && $this->cajaActual->opened_at->format('Y-m-d') !== now()->format('Y-m-d')) {
        // Cierra automáticamente la caja anterior
        $this->cajaActual->update([
            'monto_cierre' => $this->cajaActual->monto_apertura,
            'closed_at'    => now()->startOfDay(),
            'estado'       => 'cerrada',
        ]);
        $this->cargarCajaActual();
    }

    // Si no hay caja abierta hoy, pedir abrir caja
    if (!$this->cajaActual) {
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
            $this->clienteSeleccionadoNombre = $consumidor->nombre;
        }
    }
}

    // ✅ CORREGIR: Solo un método abrirModalClientes
    public function abrirModalBuscarCliente()
    {
        $empresaId = $this->getEmpresaId();
        
        $this->clientes = Actor::whereIn('tipo', [1, 2])
            ->where('empresa_id', $empresaId)
            ->orderBy('nombre')
            ->get(['id', 'id_clip_pro', 'nombre']);

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
        $this->addError('nuevoCliente.responsable_iva', 'Un régimen simplificado no puede ser responsable de IVA.');
    }

    if ($this->nuevoCliente['regimen_tributario'] === 'comun' && $this->nuevoCliente['responsable_iva'] != 1) {
        $this->addError('nuevoCliente.responsable_iva', 'Un régimen común debe ser responsable de IVA.');
    }

    if ($this->nuevoCliente['tipo_persona'] === 'juridica' && $this->nuevoCliente['tipo_documento_id'] != 6) {
        $this->addError('nuevoCliente.tipo_documento_id', 'Una persona jurídica solo puede tener NIT como tipo de documento.');
    }

    if ($this->getErrorBag()->isNotEmpty()) {
        return;
    }

    $this->validate();

    $empresaId = $this->getEmpresaId(); // Esta función debe estar definida en este mismo componente

    

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

    $this->clienteId                 = $cliente->id_clip_pro;
    $this->clienteSeleccionadoNombre = $cliente->nombre;
    $this->mostrarModalCrearCliente  = false;
}


    public function confirmarGuardarPrefactura()
{
    // ✅ VALIDAR CARRITO VACÍO
    if (empty($this->carrito)) {
        $this->dispatch('mostrar-carrito-vacio');
        return;
    }
    
    // ✅ VALIDAR CLIENTE SELECCIONADO  
    if (!$this->clienteId) {
        $this->dispatch('mostrar-cliente-requerido');
        return;
    }
    
    // ✅ SI TODO ESTÁ BIEN, MOSTRAR CONFIRMACIÓN
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

    $this->clienteId = $cliente->id; // ✅ ID real
    $this->clienteSeleccionadoNombre = $cliente->nombre;

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
        // ✅ CONVERTIR EXPLÍCITAMENTE A NÚMEROS
        $precioVenta = floatval($producto->precio_venta1);
        $precioCosto = floatval($producto->precio_costo ?? 0);
        $ivaVenta    = floatval($producto->iva_venta ?? 0);
        $utilidad1   = floatval($producto->utilidad1 ?? 0);
        $existencias = intval($producto->existencias ?? 0);
        
        $this->carrito[$key] = [
            'uuid'          => $key, // clave propia para diferenciar instancias
            'id_producto'   => $producto->id_producto,
            'nombre'        => $producto->descripcion_larga,
            'cantidad'      => 1,
            'precio'        => $precioVenta,
            'nuevo_precio'  => $precioVenta,
            'descuento'     => 0,
            'iva_venta'     => $ivaVenta,
            'utilidad1'     => $utilidad1,
            'costo'         => $precioCosto,
            'utilidad_nueva'=> $utilidad1,
            'total'         => $precioVenta,
            'existencias'   => $existencias,
        ];
    }
    $this->actualizarTotales();
}
    public function agregarProductoAlCarrito($idProducto)
    {
        $this->agregarProducto($idProducto);
        $this->dispatch('limpiar-input-busqueda')->to('pos-productos');
    }

    // ✅ MÉTODO PARA AGREGAR PRODUCTO MANUAL
    public function agregarProductoManual($data)
{
    if (!is_array($data)) {
     
        return;
    }

    $uuid = (string) Str::uuid();
    
    // ✅ CONVERTIR EXPLÍCITAMENTE A NÚMEROS
    $precio = floatval($data['precio']);
    
    $this->carrito[$uuid] = [
        'uuid'         => $uuid,
        'id_producto'  => $data['codigo'],
        'nombre'       => $data['nombre'],
        'precio'       => $precio,
        'cantidad'     => 1,
        'total'        => $precio, // ✅ CONVERSIÓN SEGURA
        'costo'        => 0,
        'nuevo_precio' => $precio,
        'descuento'    => 0,
        'existencias'  => 0,
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
            unset($this->carrito[$index]);
            break;
        }
    }
    
    $this->actualizarTotales();
}
public function updatedCarrito($value, $key)
{
    // Detectar si cambió la cantidad de algún producto
    if (strpos($key, '.cantidad') !== false) {
        // Obtener el ID del producto desde el key
        $productId = str_replace('.cantidad', '', $key);
        
        if (isset($this->carrito[$productId])) {
            $cantidad = max(1, intval($value));
            $nuevoPrecio = isset($this->carrito[$productId]['nuevo_precio']) 
                ? floatval($this->carrito[$productId]['nuevo_precio']) 
                : floatval($this->carrito[$productId]['precio']);
            
            // ✅ RECALCULAR SUBTOTAL CON EL PRECIO CORRECTO
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
    $this->carrito = [];
    $this->totalGeneral = 0;

    $this->reset([
        'observacionesPrefactura',
        'prefacturaSeleccionada',
        'detalleSeleccionado',
        'clienteId', // ✅ LIMPIAR CLIENTE
        'clienteSeleccionadoNombre', // ✅ LIMPIAR NOMBRE
    ]);

    $this->observacionKey++; // ✅ Forzar re-render del textarea
    session()->forget('carrito_guardado');
    session()->forget('observaciones_guardadas');

    $this->dispatch('limpiar-carrito-en-cache');

    // ✅ Volver a asignar consumidor final
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
    foreach ($this->carrito as $id => $item) {
        // ✅ USAR nuevo_precio SI EXISTE, SI NO usar precio original
        $nuevoPrecio = isset($item['nuevo_precio']) ? floatval($item['nuevo_precio']) : floatval($item['precio']);
        $cantidad = intval($item['cantidad']);
        $costo = floatval($item['costo']);

        // ✅ CALCULAR SUBTOTAL CON EL PRECIO CORRECTO
        $subtotal = round($nuevoPrecio * $cantidad, 2);
        
        // Calcular nueva utilidad
        $utilidad_nueva = $costo > 0 ? round((($nuevoPrecio - $costo) / $costo) * 100, 2) : 0;
        
        // ✅ ACTUALIZAR VALORES EN EL CARRITO
        $this->carrito[$id]['utilidad_nueva'] = $utilidad_nueva;
        $this->carrito[$id]['total'] = $subtotal;
        
        // ✅ ASEGURARSE DE QUE nuevo_precio ESTÉ ESTABLECIDO
        if (!isset($this->carrito[$id]['nuevo_precio'])) {
            $this->carrito[$id]['nuevo_precio'] = $nuevoPrecio;
        }
    }

    // ✅ RECALCULAR TOTAL GENERAL
    $this->calcularTotalGeneral();
    
    $this->dispatch('guardar-carrito-en-cache', $this->carrito);
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

// ✅ MÉTODO SEPARADO PARA ACTUALIZAR CUANDO SE MODIFICA PRECIO DIRECTAMENTE
public function recalcularPorPrecio($id, $nuevoPrecio)
{
    if (isset($this->carrito[$id])) {
        $precioBase = floatval($this->carrito[$id]['precio']);
        $descuento = $precioBase > 0 ? round((($nuevoPrecio / $precioBase) - 1) * 100, 2) : 0;
        
        $this->carrito[$id]['nuevo_precio'] = intval($nuevoPrecio);
        $this->carrito[$id]['descuento'] = $descuento;
        
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
            // ✅ APLICAR DIRECTAMENTE LOS VALORES DEL MODAL
            $this->carrito[$id]['nuevo_precio'] = intval($datos['nuevo_precio']);
            $this->carrito[$id]['descuento'] = floatval($datos['descuento']);
            
            // ✅ RECALCULAR SUBTOTAL INMEDIATAMENTE
            $cantidad = intval($this->carrito[$id]['cantidad']);
            $nuevoPrecio = intval($datos['nuevo_precio']);
            $costo = floatval($this->carrito[$id]['costo']);
            
            // Calcular nueva utilidad
            $utilidad_nueva = $costo > 0 ? round((($nuevoPrecio - $costo) / $costo) * 100, 2) : 0;
            
            // ✅ ACTUALIZAR VALORES EN EL CARRITO
            $this->carrito[$id]['utilidad_nueva'] = $utilidad_nueva;
            $this->carrito[$id]['total'] = round($nuevoPrecio * $cantidad, 2);
            
           
        }
    }
    
    // ✅ RECALCULAR TOTAL GENERAL
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
session()->put('observaciones_guardadas', $this->observacionesPrefactura); // ✅ AGREGAR ESTA LÍNEA
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
        $this->dispatch('error', 'El carrito está vacío. Agregue productos antes de guardar.');
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
        $prefactura = Prefactura::create([
            'empresa_id'    => $empresaId,
            'cliente_id'    => $this->clienteId,
            'observaciones' => $this->observacionesPrefactura ?? '',
            'estado'        => 'borrador',
        ]);

        foreach ($this->carrito as $item) {
            $precioUnitario = $item['nuevo_precio'] ?? $item['precio'];
            $cantidad = $item['cantidad'];
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

        // ✅ Limpieza completa antes de asignar nuevo cliente
        $this->reset(['carrito', 'observacionesPrefactura', 'clienteId', 'clienteSeleccionadoNombre']);
        $this->totalGeneral = 0; 
        $this->observacionKey++; 
        session()->forget('carrito_guardado');

        // ✅ Ahora sí: asignar "CONSUMIDOR FINAL"
        $this->asignarConsumidorFinalPorDefecto();

        session()->flash('success', '✅ Prefactura guardada correctamente');
        $this->dispatch('prefactura-guardada-limpia-campo');
    } catch (\Exception $e) {
        $this->dispatch('error', 'Error al guardar la prefactura: ' . $e->getMessage());
    }
}

    public function verPrefacturas()
    {
        $empresaId = $this->getEmpresaId();
        
        $this->prefacturasDisponibles = Prefactura::with(['cliente', 'productos'])
    ->where('empresa_id', $this->getEmpresaId())
    ->where('estado', 'borrador')
    ->latest()
    ->get();

        $this->mostrarModalPrefacturas = true;
    }

    public function seleccionarPrefactura($id)
    {
        if ($this->cargandoPrefactura) {
            return;
        }

        $this->cargandoPrefactura = true;
        $empresaId = $this->getEmpresaId();

        if ($this->prefacturaSeleccionada && $this->prefacturaSeleccionada->id === $id) {
            $this->prefacturaSeleccionada  = null;
            $this->detalleSeleccionado     = [];
            $this->observacionesPrefactura = '';
            $this->cargandoPrefactura      = false;
            return;
        }

        $prefactura = Prefactura::with('productos.producto', 'cliente')
            ->where('id', $id)
            ->where('empresa_id', $empresaId)
            ->first();

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

    foreach ($prefactura->productos as $item) {
    $producto = Product::where('id_producto', $item->producto_id)
        ->where('empresa_id', $empresaId)
        ->first();

    if (! $producto) continue;

    $esNoAcumulable = in_array((int)$item->producto_id, $this->noAcumulables, true);
    $key = $esNoAcumulable ? (string) Str::uuid() : (string) $item->producto_id;

    $this->carrito[$key] = [
        'uuid'          => $key,
        'id_producto'   => $item->producto_id,
        'nombre'        => $item->descripcion_larga, // ✅ conserva nombre de la instancia
        'cantidad'      => $item->cantidad,
        'precio'        => $item->precio_unitario,    // ✅ conserva precio de la instancia
        'nuevo_precio'  => $item->precio_unitario,
        'descuento'     => $item->descuento ?? 0,
        'iva_venta'     => $producto->iva_venta ?? 0,
        'utilidad1'     => $producto->utilidad1 ?? 0,
        'costo'         => $producto->precio_costo ?? 0,
        'utilidad_nueva'=> 0,
        'total'         => $item->subtotal,
        'existencias'   => $producto->existencias ?? 0,
    ];
}

    if ($prefactura->cliente) {
        $this->clienteId = $prefactura->cliente->id; // ✅ ID real
        $this->clienteSeleccionadoNombre = $prefactura->cliente->nombre;
    } else {
        $this->clienteId = null;
        $this->clienteSeleccionadoNombre = 'Sin cliente';
    }

    $this->observacionesPrefactura = $prefactura->observaciones;
    $this->observacionKey++;

    $prefactura->productos()->delete();
    $prefactura->delete();

    $this->prefacturaSeleccionada = null;
    $this->detalleSeleccionado = [];

    $this->prefacturasDisponibles = Prefactura::with(['cliente', 'productos'])
        ->where('empresa_id', $empresaId)
        ->where('estado', 'borrador')
        ->latest()
        ->get();

    $this->cargandoPrefactura = false;
    $this->mostrarModalPrefacturas = false;

$this->calcularTotalGeneral(); // ✅ RECALCULAR TOTAL DESPUÉS DE CARGAR
$this->dispatch('guardar-carrito-en-cache', $this->carrito); // ✅ GUARDAR EN CACHE
    
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

    session()->put('carrito_guardado', $this->carrito);
    session()->put('observaciones_guardadas', $this->observacionesPrefactura); // ✅ AGREGAR ESTA LÍNEA

    // ✅ CORREGIR: ASEGURAR CONVERSIONES NUMÉRICAS
    foreach ($this->carrito as $id => &$item) {
        $item['cantidad'] = max(1, intval($item['cantidad']));
        
        // ✅ SOLO RECALCULAR SI NO HAY TOTAL O SI LA CANTIDAD CAMBIÓ
        if (!isset($item['total']) || !isset($item['nuevo_precio'])) {
            $precio = isset($item['nuevo_precio']) ? floatval($item['nuevo_precio']) : floatval($item['precio']);
            $cantidad = intval($item['cantidad']);
            $item['total'] = round($precio * $cantidad, 2);
        }
    }
    unset($item);

    // ✅ CALCULAR TOTAL GENERAL BASADO EN LOS TOTALES ACTUALES
    $totalGeneral = 0;
    $itemsActivos = 0;
    foreach ($this->carrito as $item) {
        $totalGeneral += floatval($item['total'] ?? 0);
    }

    return view('livewire.carrito-venta', [
        'carrito'                 => $this->carrito,
        'totalGeneral'            => $totalGeneral,
        'itemsActivos'            => $itemsActivos,
        'creditoInfo'             => $this->clienteCreditoInfo,
        'mostrarModal'            => $this->mostrarModal,
        'mostrarModalPrefacturas' => $this->mostrarModalPrefacturas,
        'prefacturas'             => $this->prefacturasDisponibles,
        'prefacturaSeleccionada'  => $this->prefacturaSeleccionada,
        'detallesPrefactura'      => $this->detalleSeleccionado,
        'observacionesPrefactura' => $this->observacionesPrefactura,
        'preciosBase'             => $this->preciosBase,
        'cajaEstado'               => $this->cajaEstado,
        'cajaActual'               => $this->cajaActual,
        
    ]);
}

    // ✅ MÉTODOS ADICIONALES NECESARIOS
    public function abrirModalRenombrar($uuid)
    {
        $this->uuidProductoEditando  = $uuid;
        $this->nuevoNombreTemporal   = $this->carrito[$uuid]['nombre'] ?? '';
        $this->mostrarModalRenombrar = true;
    }

    public function guardarNuevoNombre()
    {
        if ($this->uuidProductoEditando && isset($this->carrito[$this->uuidProductoEditando])) {
            $this->carrito[$this->uuidProductoEditando]['nombre'] = $this->nuevoNombreTemporal;
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

    // ✅ LIMPIAR TODO DESPUÉS DE BORRAR
    $this->reset([
        'prefacturaSeleccionada',
        'detalleSeleccionado', 
        'observacionesPrefactura', // ✅ LIMPIAR OBSERVACIONES
        'prefacturaAEliminarId'
    ]);
    
    $this->observacionKey++; // ✅ FORZAR RE-RENDER DEL TEXTAREA
    $this->calcularTotalGeneral(); // ✅ RECALCULAR TOTAL

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
        // validar caja primero
        if (! $this->verificarCajaAbierta()) return;

        if (empty($this->carrito)) { $this->dispatch('error','El carrito está vacío.'); return; }
        if (!$this->clienteId)     { $this->dispatch('error','Seleccione un cliente.');  return; }
        $this->dispatch('confirmar-facturar'); // abre tu modal de confirmación
    }


#[On('facturar-confirmada')]
public function facturarConfirmada(array $data = [])
{
    $empresaId = $this->getEmpresaId();

     if (! $this->verificarCajaAbierta()) return;


    try {
        DB::beginTransaction();

        // ✅ CLIENTE: usa el seleccionado o fuerza CF
        $clienteId = $this->clienteId ?: $this->getConsumidorFinalId($empresaId);
        if (!$clienteId) {
            throw new \Exception('Falta CONSUMIDOR FINAL en esta empresa.');
        }

        // ✅ Permitir stock negativo: solo valida que exista y cantidad > 0
        foreach ($this->carrito as $item) {
            $prod = Product::where('empresa_id', $empresaId)
                ->where('id_producto', $item['id_producto'])
                ->lockForUpdate()
                ->first();

            if (!$prod) {
                throw new \Exception("Producto {$item['id_producto']} no existe.");
            }

            $cant = (int)($item['cantidad'] ?? 1);
            if ($cant <= 0) {
                throw new \Exception("Cantidad inválida en {$item['id_producto']}.");
            }
        }

        // ===== Datos de la petición =====
        $tipoFactura = $data['tipo_factura'] ?? 'salida';
        $tipoPago    = $data['tipo_pago']    ?? 'contado';
        $medioPago   = $tipoPago === 'contado' ? ($data['medio_pago'] ?? 'efectivo') : null;
        $vencRaw     = $data['fecha_vencimiento'] ?? null;

        // NUEVO: observación exclusiva para transferencia
        $transferObs = ($tipoPago === 'contado' && $medioPago === 'transferencia')
            ? trim((string)($data['transferencia_obs'] ?? ''))
            : null;

        // Observaciones generales de la prefactura (se mantienen aparte)
        $obs = trim((string)($this->observacionesPrefactura ?? ''));

        // ===== Crear factura =====
        $factura = Factura::create([
            'empresa_id'         => $empresaId,
            'cliente_id'         => $clienteId,
            'user_id'            => auth()->id(),
            'tipo_factura'       => $tipoFactura,
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
            'observaciones'      => $obs,            // 👈 sigue siendo tu campo general
            'transferencia_obs'  => $transferObs,    // 👈 NUEVO campo exclusivo
        ]);

        // ===== Detalles & stock =====
        foreach ($this->carrito as $item) {
            $precio = (float)($item['nuevo_precio'] ?? $item['precio'] ?? 0);
            $cant   = (int)($item['cantidad'] ?? 1);
            $sub    = round($precio * $cant, 2);

            $factura->detalles()->create([
                'producto_id'       => $item['id_producto'],
                'descripcion_larga' => $item['nombre'],
                'cantidad'          => $cant,
                'precio'            => $precio,
                'subtotal'          => $sub,
                'descuento'         => (float)($item['descuento'] ?? 0),
            ]);

            // existencias pueden quedar en negativo
            Product::where('empresa_id', $empresaId)
                ->where('id_producto', $item['id_producto'])
                ->update(['existencias' => DB::raw('COALESCE(existencias,0) - '.(int)$cant)]);
        }

        // Recalcular totales
        $factura->recalcularTotales();

        // ===== Condiciones de pago =====
        if ($tipoPago === 'credito') {
            $info = $this->clienteCreditoInfo; // accessor existente

            if (!($info['permite'] ?? false)) {
                throw new \Exception('El cliente no tiene crédito habilitado.');
            }
            if ($factura->total > (float)($info['cupo_disponible'] ?? 0)) {
                throw new \Exception('Cupo insuficiente para otorgar este crédito.');
            }

            // saldo = total, estado pendiente, fecha_venc por días de crédito si no viene
            $factura->saldo = $factura->total;
            $factura->estado_pago = 'pendiente';

            $dias = (int)($info['dias'] ?? 0);
            $factura->fecha_vencimiento = $vencRaw
                ? \Carbon\Carbon::parse($vencRaw)->toDateString()
                : now()->addDays($dias)->toDateString();

            $factura->save();
        } else {
            // contado: registrar abono por el total
            // Nota: si es transferencia, anexa la observación como nota
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

        DB::commit();

        // ===== Limpiar UI =====
        $this->carrito = [];
        $this->totalGeneral = 0;
        $this->reset('observacionesPrefactura');
        $this->observacionKey++;
        $this->dispatch('limpiar-observaciones');
        $this->forzarConsumidorFinal();

        $this->dispatch('success', "Factura #{$factura->id} creada.");
    } catch (\Throwable $e) {
        DB::rollBack();
        $this->dispatch('error', 'No se pudo crear la factura: '.$e->getMessage());
    }
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

    $this->facturas = Factura::with(['cliente','detalles'])
        ->where('empresa_id', $empresaId)
        ->where('user_id', auth()->id()) // <-- SOLO facturas del vendedor logueado
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

// Devolución total
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

        // 👇 AGREGA ESTA LÍNEA
        $f->recalcularTotales();
    });

    $this->seleccionarFactura($this->facturaSeleccionada->id);
    $this->dispatch('success','Devolución total realizada.');
}

// Devolución parcial (por ítem)
public function devolverItemFactura(int $detalleId, $cantidad)
{
    if (!$this->facturaSeleccionada) return;

    $cantidad = (float)$cantidad;
    if ($cantidad <= 0) { $this->dispatch('error','Cantidad inválida.'); return; }

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

        // Si todos los ítems quedaron devueltos, marcamos la factura como total
        $f = $this->facturaSeleccionada->fresh(['detalles']);
        $todosDev = $f->detalles->every(fn($d) => (float)$d->devuelto_cantidad >= (float)$d->cantidad);
        if ($todosDev && !$f->devuelta_total) {
            $f->devuelta_total = true;
            $f->observaciones = trim(($f->observaciones ?? '').' | DEVUELTA TOTAL '.now()->toDateTimeString());
            $f->save();
        }

        // 👇 AGREGA ESTA LÍNEA
        $f->recalcularTotales();
    });

    $this->seleccionarFactura($this->facturaSeleccionada->id);
    $this->dispatch('success','Devolución parcial registrada.');
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
    // Pestaña inicial
    $this->tab = 'prefacturas';
    // Rango 3 meses
    $this->maxFechaFacturas = now()->toDateString();
    $this->minFechaFacturas = now()->subMonths(3)->toDateString();
    $this->fDesde = $this->maxFechaFacturas;
    $this->fHasta = $this->maxFechaFacturas;
    // Cargar listas
    $this->cargarFacturas();          // ya lo tienes
    // (si tienes método para prefacturas, llámalo aquí)
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

        // ✅ Cliente: seleccionado o CONSUMIDOR FINAL
        $clienteId = $this->clienteId ?: $this->getConsumidorFinalId($empresaId);
        if (!$clienteId) {
            throw new \Exception('Falta CONSUMIDOR FINAL en esta empresa.');
        }

        // ✅ Carrito (permitiendo stock negativo)
        if (empty($this->carrito)) {
            throw new \Exception('El carrito está vacío.');
        }

        foreach ($this->carrito as $item) {
            $prod = Product::where('empresa_id', $empresaId)
                ->where('id_producto', $item['id_producto'])
                ->lockForUpdate()
                ->first();

            if (!$prod) {
                throw new \Exception("Producto {$item['id_producto']} no existe.");
            }

            $cant = (int)($item['cantidad'] ?? 1);
            if ($cant <= 0) {
                throw new \Exception("Cantidad inválida en {$item['id_producto']}.");
            }
        }

        // ===== Datos de cabecera
        $tipoFactura = $data['tipo_factura'] ?? 'salida';
        $tipoPago    = $data['tipo_pago']    ?? 'contado';
        $medioPago   = $tipoPago === 'contado' ? ($data['medio_pago'] ?? 'efectivo') : null;
        $vencRaw     = $data['fecha_vencimiento'] ?? null;

        // 👇 Observación específica si es transferencia en contado
        $transferObs = ($tipoPago === 'contado' && $medioPago === 'transferencia')
            ? trim((string)($data['transferencia_obs'] ?? ''))
            : null;

        // Observaciones generales de la prefactura
        $obs = trim((string)($this->observacionesPrefactura ?? ''));

        // ===== Crear factura
        $factura = Factura::create([
            'empresa_id'         => $empresaId,
            'cliente_id'         => $clienteId,
            'user_id'            => auth()->id(),
            'tipo_factura'       => $tipoFactura,
            'tipo_pago'          => $tipoPago,
            'medio_pago'         => $medioPago,
            'transferencia_obs'  => $transferObs, // 👈 guarda aquí
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
        ]);

        // ===== Detalles + existencias
        foreach ($this->carrito as $item) {
            $precio = (float)($item['nuevo_precio'] ?? $item['precio'] ?? 0);
            $cant   = (int)($item['cantidad'] ?? 1);
            $sub    = round($precio * $cant, 2);

            $factura->detalles()->create([
                'producto_id'       => $item['id_producto'],
                'descripcion_larga' => $item['nombre'],
                'cantidad'          => $cant,
                'precio'            => $precio,
                'subtotal'          => $sub,
                'descuento'         => (float)($item['descuento'] ?? 0),
            ]);

            Product::where('empresa_id', $empresaId)
                ->where('id_producto', $item['id_producto'])
                ->update(['existencias' => DB::raw('COALESCE(existencias,0) - '.(int)$cant)]);
        }

        // Totales
        $factura->recalcularTotales();

        // ===== Condiciones de pago
        if ($tipoPago === 'credito') {
            $info = $this->clienteCreditoInfo;

            if (!($info['permite'] ?? false)) {
                throw new \Exception('El cliente no tiene crédito habilitado.');
            }
            if ($factura->total > (float)($info['cupo_disponible'] ?? 0)) {
                throw new \Exception('Cupo insuficiente para otorgar este crédito.');
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

        DB::commit();

        // ===== Limpiar UI
        $this->carrito = [];
        $this->totalGeneral = 0;
        $this->observacionesPrefactura = '';
        $this->observacionKey = ($this->observacionKey ?? 0) + 1;
        $this->dispatch('limpiar-observaciones');
        $this->forzarConsumidorFinal();

        // ===== Devolver URL para imprimir (lo consume el .then del botón)
        $url = route('factura.imprimir', $factura->id);
        $this->dispatch('success', "Salida #{$factura->id} creada.");
        return ['ok' => true, 'factura_id' => $factura->id, 'print_url' => $url];

    } catch (\Throwable $e) {
        DB::rollBack();
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

    // ✅ SOLO por PK real de actors.id
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
        ->where('cliente_id', $actor->id)   // ← siempre por actors.id
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

    // 🚫 Validar: si es crédito y NO está pagada, no permitir devolución
    if (
        $this->facturaSeleccionada->tipo_pago === 'credito' &&
        $this->facturaSeleccionada->estado_pago !== 'pagada'
    ) {
        $this->dispatch('error','No puedes hacer devoluciones en facturas a crédito hasta que estén pagadas.');
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
    if (empty($items)) { $this->dispatch('error','No hay ítems seleccionados.'); return; }

    DB::transaction(function() use ($items) {
        $f = $this->facturaSeleccionada->fresh(['detalles']);
        $empresaId = $f->empresa_id;

        // Crear cabecera devolución
        $dev = \App\Models\Devolucion::create([
            'empresa_id'   => $empresaId,
            'factura_id'   => $f->id,
            'cliente_id'   => $f->cliente_id,
            'user_id'      => auth()->id(),   // <-- aquí capturas al usuario logueado
            'fecha'        => now(),
            'total'        => 0,
            'observaciones'=> 'Devolución de factura #'.$f->id,
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
            \App\Models\Product::where('empresa_id', $empresaId)
                ->where('id_producto', $row['producto_id'])
                ->increment('existencias', $cant);

            // Marcar devuelto
            $det->devuelto_cantidad = (float)$det->devuelto_cantidad + $cant;
            $det->save();

            // Detalle devolución
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

        // Total devolución
        $dev->total = $total;
        $dev->save();

        // Si todos los ítems quedaron devueltos, marcar la factura como total
        $f2 = $f->fresh(['detalles']);
        $todosDev = $f2->detalles->every(fn($d) => (float)$d->devuelto_cantidad >= (float)$d->cantidad);
        if ($todosDev && !$f2->devuelta_total) {
            $f2->devuelta_total = true;
            $f2->observaciones = trim(($f2->observaciones ?? '').' | DEVUELTA TOTAL '.now()->toDateTimeString());
            $f2->save();
        }

        // Cerrar modal y abrir impresión
        $this->mostrarModalDevolucion = false;
        $this->carritoDevolucion = [];
        $this->totalDevolucion = 0;

        // Refrescar la selección de factura en la UI
        $this->seleccionarFactura($f2->id);

        // Imprimir
        $url = route('devolucion.imprimir', $dev->id);
        $this->dispatch('open-print', url: $url);
        $this->dispatch('success','Devolución registrada.');
    });
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
            // solo campos usados en el cálculo
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
            continue; // esta factura ya no debe nada según el cálculo real
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

        // actualizar fecha de vencimiento máxima
        $cur = $porCliente[$cid]['max_venc'];
        $fv  = $f->fecha_vencimiento?->toDateString();
        if ($fv && (!$cur || $fv > $cur)) {
            $porCliente[$cid]['max_venc'] = $fv;
        }
    }

    // 3) Cargamos nombres de actores y aplicamos filtro de búsqueda
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
            'saldo'    => (float) $agg['saldo'],      // 👈 ahora es la suma de VENCE
            'facturas' => (int) $agg['facturas'],
            'max_venc' => (string) ($agg['max_venc'] ?? '—'),
        ];
    }

    // 4) Ordenamos por nombre y asignamos al estado
    usort($rows, fn($a, $b) => strcasecmp($a['nombre'], $b['nombre']));
    $this->carteraClientes = $rows;

    // 5) Si el seleccionado desapareció, limpiamos
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
    $this->abonoVence         = $vence; // <-- Ahora sí está definido
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

        // Normaliza (2.000, 2,000 → 2000)
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


 // ================ PAGO RÁPIDO (saldo total) =========
    public function pagarFactura(int $id): void
    {
        $empresaId = $this->getEmpresaId();
        $f = Factura::where('empresa_id', $empresaId)->lockForUpdate()->findOrFail($id);

        $saldo = (float) $f->saldo;
        if ($saldo <= 0) { $this->dispatch('info','La factura ya está pagada.'); return; }

        $this->abonarEnCartera($f->id, [
            'monto' => $saldo,
            'medio' => 'efectivo',
            'nota'  => 'Pago total desde Cartera',
            'transferencia_obs' => null,
        ]);

        $f->refresh();
        $this->cargarCarteraCliente($f->cliente_id);

        // cierra cartera y abre impresión
        $this->mostrarModalCartera = false;

        // abre en nueva pestaña: usa el que prefieras
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

// ✅ NO imprime aquí. Solo registra el abono y refresca la UI.
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

        $factura->registrarAbono(
            monto: $monto,
            medio: $medio,
            nota : $nota,
            userId: auth()->id(),
            transferenciaObs: $transferObs
        );

        // Refresca el modelo para obtener el saldo actualizado
        $factura->refresh();

        // 👇 Aquí va tu bloque para actualizar el estado correctamente:
        $totalOriginal = (float) $factura->detalles()->sum(\DB::raw('precio * cantidad'));
        $totalDevuelto = (float) $factura->detalles()->sum(\DB::raw('precio * devuelto_cantidad'));
        $totalPagado   = (float) $factura->pagos()->sum('monto');
        $vence         = max(0, $totalOriginal - $totalDevuelto - $totalPagado);

        if ($vence <= 0) {
            if ($totalPagado >= ($totalOriginal - $totalDevuelto) && !$factura->devuelta_total) {
                $factura->estado_pago = 'pagada';
                $factura->fecha_pago = now();
            } elseif ($factura->devuelta_total || $totalDevuelto >= $totalOriginal) {
                $factura->estado_pago = 'devuelta';
                $factura->fecha_pago = null;
            } else {
                $factura->estado_pago = 'parcial';
            }
            $factura->save();
        }

        DB::commit();

        // 🔄 Refresca UI (sin imprimir)
        $this->abonoTransferObs = '';
        $this->abonoSaldo       = (float) $factura->saldo;
        $this->abonoMonto       = $this->abonoSaldo;

        $this->cargarCarteraCliente($factura->cliente_id);

        $this->carteraRefreshKey = ($this->carteraRefreshKey ?? 0) + 1;
        $this->dispatch('$refresh');

        // 🖨️ Imprimir ticket de abono
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
    $this->verFacturaSaldo = (float) $f->saldo; // 👈 saldo disponible para el botón
    $this->mostrarModalFactura = true;
}

// ✅ Aquí SÍ decide imprimir si quedó saldada, y asegura que "Ver" NO se abra.
public function confirmarAbonoConValor($montoRaw): void
{
    if (!$this->abonoFacturaId) return;

    $monto = (float) preg_replace('/\D/', '', (string) $montoRaw);

    $empresaId = $this->getEmpresaId();
    $f = \App\Models\Factura::where('empresa_id', $empresaId)
        ->lockForUpdate()
        ->findOrFail($this->abonoFacturaId);

    if ($monto <= 0) { $this->dispatch('error','Monto inválido.'); return; }
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

    // 🖨️ Imprime ticket de abono SIEMPRE
    $url = route('abono.imprimir', $pago->id);
    $this->dispatch('open-print', url: $url);
    $this->dispatch('success','Abono registrado.');
}




    public function abrirHistorial(): void
{
    // Rango por defecto: últimos 30 días
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

    // Búsqueda por #factura o nombre de cliente
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

    // Usa tu ruta de impresión. Si en tu proyecto no existe 'factura.imprimir',
    // cambia por route('factura.ver', $this->verFacturaId)
    $url = route('factura.imprimir', $this->verFacturaId);

    // Dispara evento al front para abrir ventana de impresión
    $this->dispatch('open-print', url: $url);
}
public function pagarEImprimir(int $facturaId, array $data = [])
{
    $empresaId = $this->getEmpresaId();

    // === Validación rápida de entrada
    $medio = (string)($data['medio'] ?? 'efectivo');
    if (!in_array($medio, ['efectivo','transferencia','otro'], true)) {
        $medio = 'efectivo';
    }
    $transferObs = $medio === 'transferencia'
        ? trim((string)($data['transferencia_obs'] ?? ''))
        : null;
    $monto = (float)($data['monto'] ?? 0);
    if ($monto <= 0) {
        throw new \Exception('Monto inválido.');
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

        // Recalcular estado/saldo por si quedó en 0
        $factura->refresh();
        $this->cargarCarteraCliente($factura->cliente_id);

        DB::commit();

        // Cierra modal de cartera y fuerza re-render
        $this->mostrarModalCartera = false;
        $this->carteraRefreshKey   = ($this->carteraRefreshKey ?? 0) + 1;

        // Devuelve URL de impresión (usa tu ruta real)
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
    $this->montoApertura = 0;
    $this->mostrarModalAbrirCaja = true;
}

    // Dispara evento para confirmar cierre de caja con resumen de ventas
   public function cerrarCajaModal()
{
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

    // Verificar antes de facturar (ya la usas; asegúrate de llamarla)
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
    $this->validate([
        'montoApertura' => 'required|numeric|min:0',
    ]);

    \App\Models\Caja::create([
        'user_id'        => auth()->id(),
        'empresa_id'     => auth()->user()->empresa_id ?? null,
        'monto_apertura' => $this->montoApertura,
        'opened_at'      => now(),
        'estado'         => 'abierta',
    ]);

    $this->mostrarModalAbrirCaja = false;
    $this->reset('montoApertura');

    // ✅ actualizar estado + rerender
    $this->cargarCajaActual();
     $this->uiCajaKey++;  // fuerza re-render del componente Livewire
    $this->dispatch('$refresh');                 // fuerza re-render del mismo componente
    $this->dispatch('caja-abierta');             // opcional: por si escuchas fuera
}


public function confirmarCerrarCaja()
{
    $this->resumenCaja = $this->calcularResumenCaja();

    $caja = \App\Models\Caja::where('user_id', auth()->id())
        ->where('estado', 'abierta')
        ->latest('opened_at')
        ->first();

    if ($caja) {
        // Si comparas contra total contado del día:
        $this->diferenciaCaja = ($this->montoCierre ?? 0) - (float)($this->resumenCaja['total_contado'] ?? 0);

        $caja->update([
            'monto_cierre' => $this->montoCierre,
            'closed_at'    => now(),
            'estado'       => 'cerrada',
        ]);
    }

    $this->mostrarModalCerrarCaja = false;
    $this->reset('montoCierre');

    // ✅ actualizar estado + rerender
    $this->cargarCajaActual();
    $this->uiCajaKey++;   // fuerza re-render del componente Livewire
    $this->dispatch('$refresh');
    $this->dispatch('caja-cerrada', diferencia: $this->diferenciaCaja);

    // si además imprimes:
    if ($caja) {
        $this->dispatch('imprimir-cierre-caja', $caja->id);
    }
}

public function cargarCajaActual()
{
    $caja = \App\Models\Caja::where('user_id', auth()->id())
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


public function calcularResumenCaja()
{
    $userId    = auth()->id();
    $empresaId = $this->getEmpresaId();
    $hoy       = now()->format('Y-m-d');

    // ----- VENTAS DEL DÍA -----
    $qVentas = \App\Models\Factura::query()
        ->where('empresa_id', $empresaId)
        ->where('user_id', $userId)
        ->whereDate('fecha', $hoy);

    // Ventas contado por medio
    $ventasContadoEfectivo      = (clone $qVentas)->where('tipo_pago','contado')->where('medio_pago','efectivo')->sum('total');
    $ventasContadoTransferencia = (clone $qVentas)->where('tipo_pago','contado')->where('medio_pago','transferencia')->sum('total');

    // Ventas a crédito
    $ventasCredito = (clone $qVentas)->where('tipo_pago','credito')->sum('total');

    // ----- COBROS EN CARTERA (ABONOS / PAGOS) -----
    // OJO: usamos FacturaPago y filtramos empresa con whereHas('factura')
    $qPagosCredito = \App\Models\FacturaPago::query()
    ->where('user_id', $userId)
    ->whereDate('fecha', $hoy)
    ->whereHas('factura', function ($q) use ($empresaId) {
        $q->where('empresa_id', $empresaId)
          ->where('tipo_pago', 'credito');   // 👈 clave para no contar pagos de contado
    });

$carteraEfectivo      = (clone $qPagosCredito)->where('medio_pago','efectivo')->sum('monto');
$carteraTransferencia = (clone $qPagosCredito)->where('medio_pago','transferencia')->sum('monto');
$carteraOtro          = (clone $qPagosCredito)->where('medio_pago','otro')->sum('monto'); // opcional
    // ----- DEVOLUCIONES -----
    // Regla: SIEMPRE restan al EFECTIVO del día y al TOTAL CONTADO
    $devolucionesConPago = \App\Models\Devolucion::query()
    ->where('empresa_id', $empresaId)
    ->where('user_id', $userId)
    ->whereDate('fecha', $hoy)
    ->whereHas('factura.pagos')   // 👈 la factura tiene pagos (cualquier medio)
    ->sum('total');

// B) Devoluciones de facturas SIN pagos/abonos (NO afectan efectivo ni contado)
$devolucionesSinPago = \App\Models\Devolucion::query()
    ->where('empresa_id', $empresaId)
    ->where('user_id', $userId)
    ->whereDate('fecha', $hoy)
    ->whereDoesntHave('factura.pagos')  // 👈 la factura no tiene pagos
    ->sum('total');

$devolucionesDia = $devolucionesConPago + $devolucionesSinPago; // informativo

// ----- TOTALES DE FLUJO DE CAJA -----
// Efectivo del día = ventas contado (efectivo) + cobros cartera (efectivo) - devoluciones de facturas CON pago
$efectivo = ($ventasContadoEfectivo + $carteraEfectivo) - $devolucionesConPago;

    // Transferencia del día = ventas contado (transferencia) + cobros cartera (transferencia)
    $transferencia = $ventasContadoTransferencia + $carteraTransferencia;

    // Total contado (lo que comparas contra "Monto de cierre")
    $totalContado = $efectivo + $transferencia;

    // Total de ventas (informativo: contado + crédito)
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

        // Devoluciones (siempre restadas al efectivo y al contado)
        'devoluciones_con_pago' => $devolucionesConPago,     // 👈 restadas al EFECTIVO y al CONTADO
    'devoluciones_sin_pago' => $devolucionesSinPago,     // 👈 no afectan flujo
    'devoluciones'          => $devolucionesDia,     

        // Totales de flujo
        'efectivo'                      => $efectivo,
        'transferencia'                 => $transferencia,
        'total_contado'                 => $totalContado,

        // Totales informativos
        'total_ventas'                  => $totalVentas,
        'total_ventas_sin_devoluciones'=> $totalVentas - $devolucionesDia, // opcional
    ];
}
public function updatedMontoCierre($value)
{
    $totalContado = $this->resumenCaja['total_contado'] ?? 0;
    $this->diferenciaCaja = floatval($value) - floatval($totalContado);
}

public function cerrarCajaAutomaticaSiEsFinDeDia()
{
    $this->cargarCajaActual();

    if ($this->cajaActual && $this->cajaActual->opened_at->format('Y-m-d') === now()->format('Y-m-d')) {
        // Si la caja sigue abierta al final del día, ciérrala automáticamente
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
   

   

}