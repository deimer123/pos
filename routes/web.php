<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\DevolucionController;
use App\Models\Caja;


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
Route::middleware(['auth'])->get('/redirect-after-login', function () {
    $user = Auth::user();

    if ($user->hasRole('super_admin')) {
        return redirect()->route('filament.admin.pages.dashboard');
    }

    if ($user->hasRole('admin_empresa')) {
        return view('eleccion');
    }

    $esVendedor = $user->hasRole('vendedor');
    $esDigitador = $user->hasRole('digitador');

    if ($esVendedor && $esDigitador) {
        return view('eleccion');
    }

    if ($esVendedor && ! $esDigitador) {
        return redirect()->route('pos');
    }

    if ($esDigitador && ! $esVendedor) {
        return redirect()->route('filament.admin.pages.dashboard');
    }

    abort(403, 'No autorizado');
})->name('redirect-after-login');

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
    $factura = \App\Models\Factura::with(['cliente','detalles'])->findOrFail($id);
    $config  = \App\Models\ConfiguracionEmpresa::where('empresa_id', $factura->empresa_id)->first();

    return view('facturas.imprimir-salida', compact('factura','config'));
})->name('factura.imprimir');

Route::middleware(['auth'])->get('/devoluciones/{id}/imprimir', [\App\Http\Controllers\DevolucionController::class, 'imprimir'])
    ->name('devolucion.imprimir');

Route::middleware(['auth'])->get('/facturas/{id}/ver', function ($id) {
    $factura = \App\Models\Factura::with(['cliente','detalles'])->findOrFail($id);
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
    Auth::logout();

    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect('/admin/login'); // 👈 Redirección al login correcto
})->name('logout');

Route::get('/abono/imprimir/{id}', function($id) {
    $abono = \App\Models\FacturaPago::with('factura.cliente')->findOrFail($id);
    return view('tickets.abono', compact('abono'));
})->name('abono.imprimir');

Route::get('/ticket-cierre-caja/{id}', function($id) {
    $caja = Caja::findOrFail($id);
    $user = $caja->user->name ?? 'N/A';
    $fecha = $caja->closed_at ? $caja->closed_at->format('Y-m-d H:i') : now()->format('Y-m-d H:i');
    // Calcula el resumen como lo haces en Livewire
    $resumen = app(\App\Livewire\CarritoVenta::class)->calcularResumenCaja();

    return view('tickets.cierre-caja', [
        'fecha'         => $fecha,
        'usuario'       => $user,
        'efectivo'      => $resumen['efectivo'],
        'transferencia' => $resumen['transferencia'],
        'credito'       => $resumen['ventas_credito'], // <-- aquí el cambio
        'total_contado' => $resumen['total_contado'],
        'total'         => $resumen['total_ventas'],   // <-- usa la clave correcta si necesitas el total general
        'monto_cierre'  => $caja->monto_cierre,
        'diferencia'    => $caja->monto_cierre - $resumen['total_contado'],
    ]);
});
