<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cocina — Acceso</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body { margin:0; background:#111827; font-family:system-ui,sans-serif; display:flex; align-items:center; justify-content:center; min-height:100vh; }
    </style>
</head>
<body>
    <div style="background:#1f2937; border-radius:16px; padding:40px 36px; width:100%; max-width:380px; border:1px solid #374151;">
        <div style="text-align:center; margin-bottom:28px;">
            <div style="font-size:48px; margin-bottom:8px;">🍳</div>
            <h1 style="color:white; font-size:22px; font-weight:700; margin:0;">Pantalla de Cocina</h1>
            <p style="color:#9ca3af; font-size:13px; margin-top:6px;">Ingrese sus credenciales</p>
        </div>

        @if($errors->any())
            <div style="background:#7f1d1d; color:#fca5a5; border-radius:8px; padding:10px 14px; margin-bottom:16px; font-size:13px;">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('cocina.login.post') }}">
            @csrf
            <div style="margin-bottom:14px;">
                <label style="display:block; color:#d1d5db; font-size:13px; margin-bottom:6px;">Correo electrónico</label>
                <input type="email" name="email" value="{{ old('email') }}" required autofocus
                    style="width:100%; background:#374151; color:white; border:1px solid #4b5563; border-radius:8px; padding:10px 14px; font-size:14px; outline:none; box-sizing:border-box;">
            </div>
            <div style="margin-bottom:24px;">
                <label style="display:block; color:#d1d5db; font-size:13px; margin-bottom:6px;">Contraseña</label>
                <input type="password" name="password" required
                    style="width:100%; background:#374151; color:white; border:1px solid #4b5563; border-radius:8px; padding:10px 14px; font-size:14px; outline:none; box-sizing:border-box;">
            </div>
            <button type="submit"
                style="width:100%; background:#d97706; color:white; border:none; border-radius:8px; padding:12px; font-size:15px; font-weight:700; cursor:pointer;">
                Entrar a Cocina
            </button>
        </form>
    </div>
</body>
</html>
