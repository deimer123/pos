<?php

use Illuminate\Support\Facades\Route;
use App\Livewire\PosProductos;

Route::get('/pos', function () {
    return view('punto-venta');
});

Route::get('/dashboard', function () {
    return view('dashboard'); // o cualquier vista que quieras mostrar
})->name('dashboard');