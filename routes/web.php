<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;
use App\Http\Controllers\DevolucionController;
use App\Http\Controllers\NotaCreditoPdfController;
use App\Models\Caja;
use App\Http\Controllers\InventarioController;
use App\Http\Controllers\KardexController;




/*
|--------------------------------------------------------------------------
| Rutas públicas o de inicio
|--------------------------------------------------------------------------
*/

// Puedes dejar esto si tienes un landing o ruta base
Route::get('/', fn () => view('welcome'));

/*
|--------------------------------------------------------------------------
| Redirección después del login
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'no.cocina.en.pos'])->get('/eleccion', function () {

    $user = Auth::user();

    $esVendedor = $user->hasRole('vendedor');

    $esDigitador = $user->hasRole('digitador');

    $esCajero = $user->hasRole('cajero');

    // SUPER ADMIN
    if ($user->hasRole('super_admin')) {

        return redirect()->route('filament.admin.pages.dashboard');

    }

    // ADMIN EMPRESA
    if ($user->hasRole('admin_empresa')) {

        if ($user->necesitaConfiguracionInicial()) {
            return redirect()->route('filament.admin.resources.configuracion-empresas.create');
        }

        return response()->view('eleccion');

    }

    // DIGITADOR PURO
    if (
        $esDigitador &&
        ! $esVendedor &&
        ! $esCajero
    ) {

        return redirect()->route('filament.admin.resources.products.index');

    }

    // DIGITADOR + VENDEDOR O CAJERO
    if (
        $esDigitador &&
        ($esVendedor || $esCajero)
    ) {

        return response()->view('eleccion');

    }

    // VENDEDOR O CAJERO
    if ($esVendedor || $esCajero) {

        return redirect()->route('pos');

    }

    abort(403, 'No autorizado');
})->name('eleccion');

/*
|--------------------------------------------------------------------------
| Punto de Venta (POS)
| Solo pueden acceder vendedores. Se controla con un middleware personalizado
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'no_digitadores_en_pos', 'no.cocina.en.pos'])->get('/pos', function () {
    return view('pos');
})->name('pos');

Route::middleware(['auth'])->get('/taller', function () {
    return view('taller');
})->name('taller');

Route::middleware(['auth'])->get('/taller/orden/{ordenId}', function ($ordenId) {
    return view('taller-orden', ['ordenId' => (int) $ordenId]);
})->name('taller.orden');

// PDF de una sola orden
Route::middleware(['auth'])->get('/taller/pdf/orden/{id}', function ($id) {
    $empresaId = auth()->user()->getEmpresaActualId();
    $orden  = \App\Models\TallerOrden::where('empresa_id', $empresaId)->with('repuestos')->findOrFail($id);
    $config = \App\Models\ConfiguracionEmpresa::where('empresa_id', $empresaId)->first();
    $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.taller-orden', compact('orden', 'config'))
        ->setPaper([0, 0, 612, 792]);
    return $pdf->stream('orden-' . str_pad($orden->numero_orden, 4, '0', STR_PAD_LEFT) . '.pdf');
})->name('taller.orden.pdf');

// PDF del listado (reporte)
Route::middleware(['auth'])->get('/taller/pdf/reporte', function (\Illuminate\Http\Request $request) {
    $empresaId = auth()->user()->getEmpresaActualId();
    $estado   = $request->get('estado', 'todos');
    $desde    = $request->get('desde');
    $hasta    = $request->get('hasta');
    $busqueda = $request->get('busqueda');

    $query = \App\Models\TallerOrden::where('empresa_id', $empresaId)->with('repuestos')
        ->when($estado === 'activas', fn($q) => $q->where('estado', '!=', 'entregado'))
        ->when($estado !== 'todos' && $estado !== 'activas', fn($q) => $q->where('estado', $estado))
        ->when($desde,    fn($q) => $q->whereDate('created_at', '>=', $desde))
        ->when($hasta,    fn($q) => $q->whereDate('created_at', '<=', $hasta))
        ->when($busqueda, fn($q) => $q->where(fn($q2) =>
            $q2->where('placa', 'like', "%$busqueda%")
               ->orWhere('cliente_nombre', 'like', "%$busqueda%")
        ))
        ->orderByDesc('created_at')
        ->get();

    $config = \App\Models\ConfiguracionEmpresa::where('empresa_id', $empresaId)->first();
    $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.taller-reporte', compact('query', 'config', 'estado', 'desde', 'hasta'))
        ->setPaper('letter', 'portrait');
    return $pdf->stream('reporte-taller-' . now()->format('Ymd') . '.pdf');
})->name('taller.reporte.pdf');


Route::middleware(['auth'])->get('/prefactura/imprimir/{id}', [\App\Http\Controllers\PrefacturaController::class, 'imprimir'])->name('prefactura.imprimir');
Route::middleware(['auth'])->get('/salida/imprimir/{id}', function ($id) {
    $factura = \App\Models\Factura::with(['cliente','detalles'])
        ->where('empresa_id', auth()->user()->getEmpresaActualId())
        ->findOrFail($id);
    $config  = \App\Models\ConfiguracionEmpresa::where('empresa_id', $factura->empresa_id)->first();

    return view('facturas.imprimir-salida', compact('factura','config'));
})->name('factura.imprimir');

Route::middleware(['auth'])->get('/devoluciones/{id}/imprimir', [\App\Http\Controllers\DevolucionController::class, 'imprimir'])
    ->name('devolucion.imprimir');

Route::middleware(['auth'])->get('/facturas/{id}/ver', function ($id) {
    $factura = \App\Models\Factura::with(['cliente','detalles'])
        ->where('empresa_id', auth()->user()->getEmpresaActualId())
        ->findOrFail($id);
    $config  = \App\Models\ConfiguracionEmpresa::where('empresa_id', $factura->empresa_id)->first();

    return view('facturas.ver-ticket', compact('factura','config'));
})->name('factura.ver');
/*
|--------------------------------------------------------------------------
| Panel /dashboard exclusivo para digitadores
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:digitador'])->get('/dashboard', function () {
    return view('dashboard');
})->name('dashboard');

Route::post('/logout', function (Request $request) {

   

    return redirect('/admin/login'); // ✅ AQUÍ EL CAMBIO

})->name('logout');

Route::get('/abono/imprimir/{id}', function($id) {
    $abono = \App\Models\FacturaPago::with('factura.cliente')
        ->whereHas('factura', fn ($query) => $query->where('empresa_id', auth()->user()->getEmpresaActualId()))
        ->findOrFail($id);
    return view('tickets.abono', compact('abono'));
})->middleware('auth')->name('abono.imprimir');

Route::get('/ticket-cierre-caja/{id}', function($id) {
    $caja = Caja::with('usuario')
        ->where('empresa_id', auth()->user()->getEmpresaActualId())
        ->findOrFail($id);
    $user = $caja->usuario->name ?? 'N/A';
    $fecha = $caja->closed_at ? $caja->closed_at->format('Y-m-d H:i') : now()->format('Y-m-d H:i');
    $inicio = $caja->opened_at ?: now()->startOfDay();
    $fin = $caja->closed_at ?: now();
    $empresaId = $caja->empresa_id ?: $caja->user_id;
    $resumen = app(\App\Livewire\CarritoVenta::class)->calcularResumenCaja($caja->user_id, $empresaId, $inicio, $fin);

    return view('tickets.cierre-caja', [
        'fecha'         => $fecha,
        'usuario'       => $user,
        'efectivo'      => $resumen['efectivo'],
        'transferencia' => $resumen['transferencia'],
        'credito'       => $resumen['ventas_credito'], // <-- aquí el cambio
        'entradas_efectivo' => $resumen['entradas_efectivo'] ?? 0,
        'salidas_efectivo' => $resumen['salidas_efectivo'] ?? 0,
        'entradas_transferencia' => $resumen['entradas_transferencia'] ?? 0,
        'salidas_transferencia' => $resumen['salidas_transferencia'] ?? 0,
        'total_contado' => $resumen['total_contado'],
        'total'         => $resumen['total_ventas'],   // <-- usa la clave correcta si necesitas el total general
        'monto_cierre'  => $caja->monto_cierre,
        'diferencia'    => $caja->monto_cierre - $resumen['efectivo'],
    ]);
})->middleware('auth');

Route::get('/inventario', function () {
    return view('inventario.rapido');
});
Route::middleware(['auth'])->group(function () {
Route::get('/producto/{codigo}', [InventarioController::class, 'buscar']);
Route::post('/inventario/guardar', [InventarioController::class, 'guardar']);
Route::get('/inventario/buscar-lista', [InventarioController::class, 'buscarLista']);
Route::post('/inventario/aplicar', [InventarioController::class, 'aplicar']);
Route::get('/inventario/borradores', [InventarioController::class, 'borradores']);
Route::delete('/inventario/borrador/{id}', [InventarioController::class, 'eliminar']);
Route::get('/inventario/reporte/{id}', [InventarioController::class, 'reporte']);
Route::get('/inventario/reporte-pdf/{id}', [InventarioController::class, 'reportePdf']);
Route::get('/inventario/borrador/{id}', [InventarioController::class, 'verBorrador']);
});
Route::get('/notas-credito/{nota}/pdf', [NotaCreditoPdfController::class, 'pdf'])
    ->middleware('auth')
    ->name('notas-credito.pdf');


    Route::middleware(['auth'])->group(function () {

    Route::get('/inventario', [InventarioController::class, 'index']);

    Route::post('/inventario/aplicar', [InventarioController::class, 'aplicar']);

    Route::get('/kardex', [KardexController::class, 'index'])->name('kardex');
    Route::get('/kardex/documento', [KardexController::class, 'documento']);

});

// Página neutral para sesión desactivada (no requiere auth, no re-registra tab)
Route::get('/sesion-desactivada', function () {
    return response('<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Sesión cerrada</title>
    <style>body{margin:0;background:#111827;display:flex;align-items:center;justify-content:center;height:100vh;font-family:system-ui,sans-serif;}
    .box{background:#1f2937;border:2px solid #374151;border-radius:12px;padding:40px;text-align:center;max-width:400px;}
    h2{color:#9ca3af;margin:0 0 10px;}p{color:#6b7280;font-size:14px;margin:0 0 20px;}
    a{background:#2563eb;color:white;text-decoration:none;border-radius:8px;padding:10px 24px;font-size:14px;font-weight:700;}</style>
    </head><body><div class="box"><div style="font-size:48px;margin-bottom:16px;">🔒</div>
    <h2>Esta pestaña fue desactivada</h2>
    <p>Otra sesión tomó el control. Puede cerrar esta pestaña o iniciar sesión nuevamente.</p>
    <a href="/admin/login">Iniciar sesión</a></div></body></html>', 200, ['Content-Type' => 'text/html']);
})->name('sesion.desactivada');

Route::get('/check-session', function () {

    if (!auth()->check()) {
        return response()->json(['error' => 'no auth'], 401);
    }

    $user = auth()->user();
    $sessionToken = session('session_token');

    if ($sessionToken && $user->session_token !== $sessionToken) {
        return response()->json(['error' => 'session_invalid'], 401);
    }

    return response()->json(['ok' => true]);

});

Route::middleware('auth')->post('/register-tab', function (\Illuminate\Http\Request $request) {
    $tabId = $request->input('tab_id');
    if ($tabId) {
        auth()->user()->update(['active_tab_id' => $tabId]);
    }
    return response()->json(['ok' => true]);
});

Route::middleware('auth')->get('/check-tab', function (\Illuminate\Http\Request $request) {
    $user = auth()->user();
    $tabId = $request->query('tab_id');

    // Verificar también session_token
    $sessionToken = session('session_token');
    if ($sessionToken && $user->session_token !== $sessionToken) {
        return response()->json(['error' => 'session_invalid'], 401);
    }

    if ($tabId && $user->active_tab_id !== $tabId) {
        return response()->json(['error' => 'tab_replaced'], 401);
    }

    return response()->json(['ok' => true]);
});

Route::middleware(['auth', 'no.cocina.en.pos'])->group(function () {

    Route::view('/pos', 'pos')
        ->name('pos');

    Route::get('/pos/mesa/{mesa}', function (\App\Models\Mesa $mesa) {
        $empresaId = auth()->user()->getEmpresaActualId();
        abort_if($mesa->empresa_id !== $empresaId, 403);
        return view('pos-mesa', compact('mesa'));
    })->name('pos.mesa');

    Route::get('/pos/mesa/{mesa}/cuenta', function (\App\Models\Mesa $mesa) {
        $empresaId = auth()->user()->getEmpresaActualId();
        abort_if($mesa->empresa_id !== $empresaId, 403);
        $orden = \App\Models\OrdenMesa::where('empresa_id', $empresaId)
            ->where('mesa_id', $mesa->id)
            ->whereIn('estado', ['abierta', 'en_preparacion', 'lista'])
            ->with(['items.producto', 'usuario'])
            ->latest()->first();
        abort_if(! $orden, 404);
        $items = $orden->items;
        $total = $items->sum('subtotal');
        $config = \App\Models\ConfiguracionEmpresa::where('empresa_id', $empresaId)->first();
        return view('tickets.cuenta-mesa', compact('mesa', 'orden', 'items', 'total', 'config'));
    })->name('pos.mesa.cuenta');

});

Route::middleware(['auth'])->group(function () {
    Route::get('/cocina', \App\Livewire\PantallaCocina::class)
        ->middleware('solo.cocina')
        ->name('cocina');
});

// Logout de cocina (redirige al login del admin)
Route::post('/cocina/logout', function (\Illuminate\Http\Request $request) {
    $user = auth()->user();
    if ($user) {
        $user->update(['session_id' => null, 'session_token' => null]);
    }
    \Illuminate\Support\Facades\Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();
    return redirect()->route('filament.admin.auth.login');
})->name('cocina.logout')->middleware('auth');
Route::get('/productos/buscar', function(Request $request) {

    $texto = trim($request->texto);

    $query = DB::table('products')
        ->where('empresa_id', auth()->user()->getEmpresaActualId());

    if ($texto) {

        $palabras = explode(' ', $texto);

        foreach ($palabras as $palabra) {
            $query->where(function($q) use ($palabra) {
                $q->where('descripcion_larga', 'like', "%$palabra%")
                  ->orWhere('id_producto', 'like', "%$palabra%");
            });
        }
    }

    return $query
        ->limit(20)
        ->get(['id_producto', 'descripcion_larga']);
})->middleware('auth');

Route::post('/cerrar-sesion', function () {

    $user = auth()->user();

    if ($user && request()->hasSession()) {
        $empresaId = method_exists($user, 'getEmpresaActualId') ? $user->getEmpresaActualId() : ($user->empresa_id ?: $user->id);
        $carrito = session('carrito_guardado', []);
        $observaciones = session('observaciones_guardadas', '');

        if (! empty($carrito)) {
            Cache::put('pos_carrito_' . $empresaId . '_' . $user->id, $carrito, now()->addDays(7));
        }

        if (trim((string) $observaciones) !== '') {
            Cache::put('pos_observaciones_' . $empresaId . '_' . $user->id, $observaciones, now()->addDays(7));
        }
    }

    // Limpiar session_id y session_token en BD para liberar la sesión
    if ($user) {
        $user->update(['session_id' => null, 'session_token' => null]);
    }

    auth()->logout();

    request()->session()->invalidate();

    request()->session()->regenerateToken();

    if (request()->expectsJson() || request()->wantsJson() || request()->ajax()) {
        return response()->noContent();
    }

    return redirect('/admin/login');

})->name('cerrar.sesion');





    
