<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Conectar este terminal</title>
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
        label { display: block; font-size: .85rem; margin-bottom: .35rem; color: #cbd5e1; }
        .machine-id-box {
            background: #0f172a; border: 1px solid #334155; border-radius: 8px;
            padding: 1rem; text-align: center; margin-bottom: 1.5rem;
        }
        .machine-id-box .valor {
            font-family: monospace; font-size: 1.1rem; letter-spacing: .05em;
            color: #4ade80; word-break: break-all;
        }
        .machine-id-box .ayuda { font-size: .78rem; color: #94a3b8; margin-top: .5rem; }
        textarea {
            width: 100%; box-sizing: border-box; padding: .6rem .75rem;
            border-radius: 8px; border: 1px solid #334155; background: #0f172a;
            color: #e2e8f0; font-size: .9rem; margin-bottom: 1rem; font-family: monospace;
            resize: vertical;
        }
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
        <h1>Conectar este terminal</h1>
        <p class="sub">Este equipo se va a conectar al servidor de esta red local -- no crea una empresa nueva, se suma como un terminal más. Se activa una sola vez con el código emitido para este terminal.</p>

        <div class="machine-id-box">
            <div class="valor">{{ $machineId ?: 'No se pudo calcular' }}</div>
            <div class="ayuda">Este es el identificador de este terminal. Envíalo a quien te vendió el sistema para que te emita el código.</div>
        </div>

        @if ($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ url('/emparejar-terminal') }}">
            @csrf
            <input type="hidden" name="machine_id" value="{{ $machineId }}">
            <label for="codigo">Código de activación del terminal</label>
            <textarea id="codigo" name="codigo" rows="4" required autofocus>{{ old('codigo') }}</textarea>

            <button type="submit">Conectar</button>
        </form>
    </div>
</body>
</html>
