<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Crear usuario administrador — Sistema POS Local</title>
    <style>
        html, body {
            margin: 0; min-height: 100%;
            display: flex; align-items: center; justify-content: center;
            background: #0f172a; color: #e2e8f0;
            font-family: system-ui, sans-serif;
            padding: 2rem 0;
        }
        .card {
            background: #1e293b; border-radius: 12px; padding: 2rem;
            width: 100%; max-width: 480px;
        }
        h1 { font-size: 1.25rem; margin: 0 0 .25rem; }
        p.sub { color: #94a3b8; margin: 0 0 1.5rem; font-size: .9rem; }
        .empresa-box {
            background: #0f172a; border: 1px solid #334155; border-radius: 8px;
            padding: .75rem 1rem; margin-bottom: 1.5rem; font-size: .9rem;
        }
        .empresa-box .label { color: #94a3b8; font-size: .78rem; }
        label { display: block; font-size: .85rem; margin-bottom: .35rem; color: #cbd5e1; }
        input {
            width: 100%; box-sizing: border-box; padding: .6rem .75rem;
            border-radius: 8px; border: 1px solid #334155; background: #0f172a;
            color: #e2e8f0; font-size: 1rem; margin-bottom: 1rem;
        }
        button {
            width: 100%; padding: .7rem; border-radius: 8px; border: none;
            background: #16a34a; color: white; font-size: 1rem; cursor: pointer;
        }
        button:hover { background: #15803d; }
        .error { background: #7f1d1d; color: #fecaca; padding: .6rem .75rem; border-radius: 8px; margin-bottom: 1rem; font-size: .85rem; }
        .ayuda { font-size: .78rem; color: #94a3b8; margin: -.5rem 0 1rem; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Crear el usuario administrador</h1>
        <p class="sub">Código válido. Este será el usuario principal de esta instalación.</p>

        <div class="empresa-box">
            <div class="label">Empresa</div>
            {{ $empresaNombre }}
        </div>

        @if ($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('activar.empresa') }}">
            @csrf
            <input type="hidden" name="licencia_id" value="{{ $licenciaId }}">
            <input type="hidden" name="empresa_id" value="{{ $empresaId }}">
            <input type="hidden" name="empresa_nombre" value="{{ $empresaNombre }}">
            <input type="hidden" name="machine_id" value="{{ $machineId }}">
            <input type="hidden" name="codigo_raw" value="{{ $codigoRaw }}">

            <label for="admin_nombre">Tu nombre</label>
            <input type="text" id="admin_nombre" name="admin_nombre" value="{{ old('admin_nombre') }}" required autofocus>

            <label for="admin_email">Correo (con esto vas a iniciar sesión)</label>
            <input type="email" id="admin_email" name="admin_email" value="{{ old('admin_email') }}" required>

            <label for="admin_password">Contraseña</label>
            <input type="password" id="admin_password" name="admin_password" required minlength="8">
            <p class="ayuda">Mínimo 8 caracteres.</p>

            <label for="admin_password_confirmation">Repetir contraseña</label>
            <input type="password" id="admin_password_confirmation" name="admin_password_confirmation" required minlength="8">

            <button type="submit">Crear usuario y entrar</button>
        </form>
    </div>
</body>
</html>
