<?php

use App\Http\Controllers\Api\PairingController;
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
    });
});
