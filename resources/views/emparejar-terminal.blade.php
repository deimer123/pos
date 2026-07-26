<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Emparejar Sistema POS Offline</title>
    <style>
        html, body {
            margin: 0; height: 100%;
            display: flex; align-items: center; justify-content: center;
            background: #0f172a; color: #e2e8f0;
            font-family: system-ui, sans-serif;
        }
        .card {
            background: #1e293b; border-radius: 12px; padding: 2rem;
            width: 100%; max-width: 420px;
        }
        h1 { font-size: 1.25rem; margin: 0 0 .25rem; }
        p.sub { color: #94a3b8; margin: 0 0 1.5rem; font-size: .9rem; }
        label { display: block; font-size: .85rem; margin-bottom: .35rem; color: #cbd5e1; }
        input {
            width: 100%; box-sizing: border-box; padding: .6rem .75rem;
            border-radius: 8px; border: 1px solid #334155; background: #0f172a;
            color: #e2e8f0; font-size: 1rem; margin-bottom: 1rem;
        }
        input[name="codigo"] { text-transform: uppercase; letter-spacing: .2em; text-align: center; font-family: monospace; }
        button {
            width: 100%; padding: .7rem; border-radius: 8px; border: none;
            background: #16a34a; color: white; font-size: 1rem; cursor: pointer;
        }
        button:hover { background: #15803d; }
        .error { background: #7f1d1d; color: #fecaca; padding: .6rem .75rem; border-radius: 8px; margin-bottom: 1rem; font-size: .85rem; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Emparejar esta terminal</h1>
        <p class="sub">Escribe el código generado en el panel del negocio (Filament → Emparejar equipo offline) y la dirección de tu servidor.</p>

        @if ($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ url('/emparejar') }}">
            @csrf
            <label for="codigo">Código de emparejamiento</label>
            <input type="text" id="codigo" name="codigo" value="{{ old('codigo') }}" maxlength="12" required autofocus>

            <label for="servidor">Servidor</label>
            <input type="url" id="servidor" name="servidor" value="{{ old('servidor', 'https://www.sistemapo.com') }}" required>

            <button type="submit">Emparejar</button>
        </form>
    </div>
</body>
</html>
