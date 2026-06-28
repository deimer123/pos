<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @php
        use Illuminate\Support\Facades\Auth;
        $empresaId = Auth::user()->empresa_id ?? Auth::id();
        $cfgLayout = \App\Models\ConfiguracionEmpresa::where('empresa_id', $empresaId)->first();
        $nombreEmpresa = $cfgLayout?->nombre_empresa ?? 'Empresa';
    @endphp
    <title>Domicilios — {{ $nombreEmpresa }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body style="min-height:100vh; margin:0; padding:0; background:#f1f5f9; color:#111827; font-family:system-ui,sans-serif;">

    {{-- Header --}}
    <div style="width:100%; height:56px; background:#f59e0b; display:flex; align-items:center; justify-content:space-between; padding:0 16px; box-shadow:0 2px 8px rgba(0,0,0,.12); position:sticky; top:0; z-index:40;">
        <div style="display:flex; align-items:center; gap:12px;">
            <a href="{{ route('eleccion') }}" style="color:white; text-decoration:none; font-size:20px; line-height:1;" title="Volver">&#8592;</a>
            <span style="color:white; font-size:20px; font-weight:700;">🛵 Domicilios</span>
        </div>
        <span style="color:white; font-size:14px; font-weight:600; opacity:.9;">{{ $nombreEmpresa }}</span>
        <form method="POST" action="{{ route('cerrar.sesion') }}">
            @csrf
            <button type="submit" style="background:rgba(255,255,255,.2); border:1px solid rgba(255,255,255,.4); color:white; padding:5px 14px; border-radius:8px; font-size:13px; cursor:pointer;">
                Salir
            </button>
        </form>
    </div>

    <div style="padding:16px; max-width:1200px; margin:0 auto;">
        {{ $slot }}
    </div>

    @livewireScripts
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</body>
</html>
