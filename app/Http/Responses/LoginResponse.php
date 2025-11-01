<?php

namespace App\Http\Responses;

use Illuminate\Support\Facades\Auth;
use Filament\Facades\Filament;
use Filament\Http\Responses\Auth\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request)
    {
        return redirect()->intended('/redirect-after-login'); // 🟢 Esta debe ser la ruta de decisión
    }
}
