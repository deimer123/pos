<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Seleccionar Panel</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

@php
    $user = auth()->user();
    $empresaId = $user?->getEmpresaActualId();
    $config = $empresaId
        ? \App\Models\ConfiguracionEmpresa::where('empresa_id', $empresaId)->first()
        : null;
    $nombreEmpresa = strtoupper($config?->nombre_empresa ?: 'EMPRESA NO CONFIGURADA');
@endphp

<body style="
    margin:0;
    padding:0;
    background:#f1f5f9;
    font-family:sans-serif;
;">

    {{-- HEADER --}}
    <div style="
        height:70px;
        background:#2563eb;
        display:flex;
        align-items:center;
        justify-content:center;
        position:relative;
        box-shadow:0 2px 10px rgba(0,0,0,.1);
    ">

        <h1 style="
            color:white;
            font-size:38px;
            font-weight:bold;
            margin:0;
        ">
            {{ $nombreEmpresa }}
        </h1>

        <div style="
            position:absolute;
            right:20px;
        ">

            <form method="POST" action="{{ route('cerrar.sesion') }}">
                @csrf

                <button type="submit" style="
                    background:#dc2626;
                    color:white;
                    border:none;
                    padding:12px 20px;
                    border-radius:12px;
                    font-weight:bold;
                    cursor:pointer;
                    font-size:14px;
                ">
                    Cerrar sesión
                </button>
            </form>

        </div>

    </div>

    {{-- CONTENIDO --}}
    <div style="
        width:100%;
        min-height:calc(100vh - 70px);
        display:flex;
        justify-content:center;
        align-items:center;
        overflow-y:auto;
        padding:30px 0;
        box-sizing:border-box;
    ">

        <div style="
            width:500px;
            max-width:92vw;
            background:white;
            border-radius:30px;
            padding:40px;
            box-shadow:0 10px 30px rgba(0,0,0,.12);
        ">

            <h2 style="
                text-align:center;
                margin-bottom:35px;
                color:#334155;
                font-size:32px;
            ">
                ¿A dónde deseas ingresar?
            </h2>

            {{-- BOTÓN POS --}}
            <a href="{{ route('pos', ['modo' => 'normal']) }}"
                style="
                    text-decoration:none;
                    display:block;
                    margin-bottom:25px;
                ">

                <div style="
                    background:linear-gradient(135deg,#4f46e5,#4338ca);
                    border-radius:25px;
                    padding:25px;
                    color:white;
                    display:flex;
                    align-items:center;
                    gap:20px;
                    box-shadow:0 8px 20px rgba(79,70,229,.3);
                    transition:.2s;
                ">

                    <div style="
                        width:70px;
                        height:70px;
                        border-radius:20px;
                        background:rgba(255,255,255,.2);
                        display:flex;
                        align-items:center;
                        justify-content:center;
                        font-size:35px;
                    ">
                        🛒
                    </div>

                    <div>

                        <div style="
                            font-size:24px;
                            font-weight:bold;
                            margin-bottom:5px;
                        ">
                            Punto de Venta
                        </div>

                        <div style="
                            opacity:.9;
                            font-size:14px;
                        ">
                            Ventas, caja y facturación
                        </div>

                    </div>

                </div>

            </a>

            {{-- BOTÓN POS CON MESAS (solo admin_empresa con el módulo activo) --}}
            @if($user->hasRole('admin_empresa') && $config?->usa_mesas)
            <a href="{{ route('pos', ['modo' => 'mesas']) }}"
                style="
                    text-decoration:none;
                    display:block;
                    margin-bottom:25px;
                ">

                <div style="
                    background:linear-gradient(135deg,#0891b2,#0e7490);
                    border-radius:25px;
                    padding:25px;
                    color:white;
                    display:flex;
                    align-items:center;
                    gap:20px;
                    box-shadow:0 8px 20px rgba(8,145,178,.3);
                    transition:.2s;
                ">

                    <div style="
                        width:70px;
                        height:70px;
                        border-radius:20px;
                        background:rgba(255,255,255,.2);
                        display:flex;
                        align-items:center;
                        justify-content:center;
                        font-size:35px;
                    ">
                        🍽️
                    </div>

                    <div>

                        <div style="
                            font-size:24px;
                            font-weight:bold;
                            margin-bottom:5px;
                        ">
                            POS con Mesas
                        </div>

                        <div style="
                            opacity:.9;
                            font-size:14px;
                        ">
                            Mapa de mesas y comandas
                        </div>

                    </div>

                </div>

            </a>
            @endif

            {{-- BOTÓN POS CON DOMICILIOS (solo admin_empresa con el módulo activo) --}}
            @if($user->hasRole('admin_empresa') && $config?->usa_domicilios)
            <a href="{{ route('pos', ['modo' => 'domicilios']) }}"
                style="
                    text-decoration:none;
                    display:block;
                    margin-bottom:25px;
                ">

                <div style="
                    background:linear-gradient(135deg,#ea580c,#c2410c);
                    border-radius:25px;
                    padding:25px;
                    color:white;
                    display:flex;
                    align-items:center;
                    gap:20px;
                    box-shadow:0 8px 20px rgba(234,88,12,.3);
                    transition:.2s;
                ">

                    <div style="
                        width:70px;
                        height:70px;
                        border-radius:20px;
                        background:rgba(255,255,255,.2);
                        display:flex;
                        align-items:center;
                        justify-content:center;
                        font-size:35px;
                    ">
                        🛵
                    </div>

                    <div>

                        <div style="
                            font-size:24px;
                            font-weight:bold;
                            margin-bottom:5px;
                        ">
                            POS con Domicilios
                        </div>

                        <div style="
                            opacity:.9;
                            font-size:14px;
                        ">
                            Pedidos y entregas a domicilio
                        </div>

                    </div>

                </div>

            </a>
            @endif

            {{-- BOTÓN POS CON TALLER (solo admin_empresa con el módulo activo) --}}
            @if($user->hasRole('admin_empresa') && $config?->usa_taller)
            <a href="{{ route('taller') }}"
                style="
                    text-decoration:none;
                    display:block;
                    margin-bottom:25px;
                ">

                <div style="
                    background:linear-gradient(135deg,#0f766e,#0d9488);
                    border-radius:25px;
                    padding:25px;
                    color:white;
                    display:flex;
                    align-items:center;
                    gap:20px;
                    box-shadow:0 8px 20px rgba(15,118,110,.3);
                    transition:.2s;
                ">

                    <div style="
                        width:70px;
                        height:70px;
                        border-radius:20px;
                        background:rgba(255,255,255,.2);
                        display:flex;
                        align-items:center;
                        justify-content:center;
                        font-size:35px;
                    ">
                        🔧
                    </div>

                    <div>

                        <div style="
                            font-size:24px;
                            font-weight:bold;
                            margin-bottom:5px;
                        ">
                            POS con Taller
                        </div>

                        <div style="
                            opacity:.9;
                            font-size:14px;
                        ">
                            Órdenes de trabajo y mecánicos
                        </div>

                    </div>

                </div>

            </a>
            @endif

            {{-- BOTÓN POS CON SERVICIO TÉCNICO (solo admin_empresa con el módulo activo) --}}
            @if($user->hasRole('admin_empresa') && $config?->usa_servicio_tecnico)
            <a href="{{ route('servicio-tecnico') }}"
                style="
                    text-decoration:none;
                    display:block;
                    margin-bottom:25px;
                ">

                <div style="
                    background:linear-gradient(135deg,#0f766e,#0d9488);
                    border-radius:25px;
                    padding:25px;
                    color:white;
                    display:flex;
                    align-items:center;
                    gap:20px;
                    box-shadow:0 8px 20px rgba(15,118,110,.3);
                    transition:.2s;
                ">

                    <div style="
                        width:70px;
                        height:70px;
                        border-radius:20px;
                        background:rgba(255,255,255,.2);
                        display:flex;
                        align-items:center;
                        justify-content:center;
                        font-size:35px;
                    ">
                        📱
                    </div>

                    <div>

                        <div style="
                            font-size:24px;
                            font-weight:bold;
                            margin-bottom:5px;
                        ">
                            POS con Servicio Técnico
                        </div>

                        <div style="
                            opacity:.9;
                            font-size:14px;
                        ">
                            Órdenes de trabajo y técnicos
                        </div>

                    </div>

                </div>

            </a>
            @endif

            {{-- BOTÓN POS CON HOTEL (solo admin_empresa con el módulo activo) --}}
            @if($user->hasRole('admin_empresa') && $config?->usa_hotel)
            <a href="{{ route('hotel') }}"
                style="
                    text-decoration:none;
                    display:block;
                    margin-bottom:25px;
                ">

                <div style="
                    background:linear-gradient(135deg,#7c3aed,#a855f7);
                    border-radius:25px;
                    padding:25px;
                    color:white;
                    display:flex;
                    align-items:center;
                    gap:20px;
                    box-shadow:0 8px 20px rgba(124,58,237,.3);
                    transition:.2s;
                ">

                    <div style="
                        width:70px;
                        height:70px;
                        border-radius:20px;
                        background:rgba(255,255,255,.2);
                        display:flex;
                        align-items:center;
                        justify-content:center;
                        font-size:35px;
                    ">
                        🏨
                    </div>

                    <div>

                        <div style="
                            font-size:24px;
                            font-weight:bold;
                            margin-bottom:5px;
                        ">
                            POS con Hotel
                        </div>

                        <div style="
                            opacity:.9;
                            font-size:14px;
                        ">
                            Habitaciones, reservas y hospedaje
                        </div>

                    </div>

                </div>

            </a>
            @endif

            {{-- BOTÓN COCINA (solo admin_empresa con el módulo activo) --}}
            @if($user->hasRole('admin_empresa') && $config?->usa_cocina)
            <a href="{{ route('cocina') }}"
                style="
                    text-decoration:none;
                    display:block;
                    margin-bottom:25px;
                ">

                <div style="
                    background:linear-gradient(135deg,#d97706,#b45309);
                    border-radius:25px;
                    padding:25px;
                    color:white;
                    display:flex;
                    align-items:center;
                    gap:20px;
                    box-shadow:0 8px 20px rgba(217,119,6,.3);
                    transition:.2s;
                ">

                    <div style="
                        width:70px;
                        height:70px;
                        border-radius:20px;
                        background:rgba(255,255,255,.2);
                        display:flex;
                        align-items:center;
                        justify-content:center;
                        font-size:35px;
                    ">
                        🍳
                    </div>

                    <div>

                        <div style="
                            font-size:24px;
                            font-weight:bold;
                            margin-bottom:5px;
                        ">
                            Cocina
                        </div>

                        <div style="
                            opacity:.9;
                            font-size:14px;
                        ">
                            Pantalla de comandas para cocina
                        </div>

                    </div>

                </div>

            </a>
            @endif

            {{-- BOTÓN ADMIN --}}
            <a href="{{ route('filament.admin.pages.dashboard') }}"
                style="
                    text-decoration:none;
                    display:block;
                ">

                <div style="
                    background:linear-gradient(135deg,#334155,#0f172a);
                    border-radius:25px;
                    padding:25px;
                    color:white;
                    display:flex;
                    align-items:center;
                    gap:20px;
                    box-shadow:0 8px 20px rgba(15,23,42,.3);
                    transition:.2s;
                ">

                    <div style="
                        width:70px;
                        height:70px;
                        border-radius:20px;
                        background:rgba(255,255,255,.15);
                        display:flex;
                        align-items:center;
                        justify-content:center;
                        font-size:35px;
                    ">
                        ⚙️
                    </div>

                    <div>

                        <div style="
                            font-size:24px;
                            font-weight:bold;
                            margin-bottom:5px;
                        ">
                            Panel Administrativo
                        </div>

                        <div style="
                            opacity:.9;
                            font-size:14px;
                        ">
                            Inventario, compras y empleados
                        </div>

                    </div>

                </div>

            </a>

        </div>

    </div>

</body>

</html>
