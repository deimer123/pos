<?php

use App\Http\Controllers\Api\PairingController;
use App\Http\Controllers\PosSyncController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Emparejamiento y sincronizacion de terminales de Turion (POS de
// escritorio con base de datos local). "bootstrap" es publico (el codigo
// de un solo uso es la autenticacion); el resto requiere el token de
// Sanctum que "bootstrap" entrega.
Route::prefix('pairing')->group(function () {
    Route::post('/bootstrap', [PairingController::class, 'bootstrap'])
        ->name('api.pairing.bootstrap');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/catalogo', [PairingController::class, 'refrescarCatalogo'])
            ->name('api.pairing.catalogo');

        // Chequeo liviano de conectividad real (no solo "hay red", sino
        // "el droplet responde ahora"): lo usa ConectividadDroplet::
        // estaEnLinea() para decidir si el boton "Facturar" de Turion
        // puede facturar en el momento.
        Route::get('/ping', fn () => response()->json(['ok' => true]))
            ->name('api.pairing.ping');

        // Prefacturas activas ("borrador") de la empresa, para que Turion
        // las baje al sincronizar (ver PosSyncPrefacturas).
        Route::get('/prefacturas', [PairingController::class, 'prefacturas'])
            ->name('api.pairing.prefacturas');

        // Subida de lo hecho offline en una terminal de Turion (boton
        // "Subir"): mismo controlador que ya usaba el navegador (sesion
        // web), aqui autenticado por el token de Sanctum que "bootstrap"
        // entrego al emparejar.
        Route::prefix('subir')->group(function () {
            Route::post('/venta', [PosSyncController::class, 'venta'])->name('api.pairing.subir.venta');
            Route::post('/mesa/item', [PosSyncController::class, 'mesaItem'])->name('api.pairing.subir.mesa-item');
            Route::post('/mesa/facturar', [PosSyncController::class, 'mesaFacturar'])->name('api.pairing.subir.mesa-facturar');
            Route::post('/taller/crear', [PosSyncController::class, 'tallerCrear'])->name('api.pairing.subir.taller-crear');
            Route::post('/taller/item', [PosSyncController::class, 'tallerItem'])->name('api.pairing.subir.taller-item');
            Route::post('/taller/facturar', [PosSyncController::class, 'tallerFacturar'])->name('api.pairing.subir.taller-facturar');
            Route::post('/hotel/crear', [PosSyncController::class, 'hotelCrear'])->name('api.pairing.subir.hotel-crear');
            Route::post('/hotel/item', [PosSyncController::class, 'hotelItem'])->name('api.pairing.subir.hotel-item');
            Route::post('/hotel/facturar', [PosSyncController::class, 'hotelFacturar'])->name('api.pairing.subir.hotel-facturar');
            Route::post('/prefactura/guardar', [PosSyncController::class, 'prefacturaGuardar'])->name('api.pairing.subir.prefactura-guardar');
            Route::post('/actor/crear', [PosSyncController::class, 'actorCrear'])->name('api.pairing.subir.actor-crear');
        });
    });
});
