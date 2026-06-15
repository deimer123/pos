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
Route::middleware(['auth'])->get('/eleccion', function () {

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
Route::middleware(['auth', 'no_digitadores_en_pos'])->get('/pos', function () {
    return view('pos');
})->name('pos');


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

Route::get('/check-session', function () {

    if (!auth()->check()) {
        return response()->json(['error' => 'no auth'], 401);
    }

    return response()->json(['ok' => true]);

});

Route::middleware(['auth'])->group(function () {

    Route::view('/pos', 'pos')
        ->name('pos');

});
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

    auth()->logout();

    request()->session()->invalidate();

    request()->session()->regenerateToken();

    if (request()->expectsJson() || request()->wantsJson() || request()->ajax()) {
        return response()->noContent();
    }

    return redirect('/admin/login');

})->name('cerrar.sesion');





    
