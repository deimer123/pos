<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sistema POS — Punto de venta, inventario y facturación para tu negocio</title>
    <meta name="description" content="Sistema POS: punto de venta, inventario, compras con carga masiva y facturación electrónica en un solo sistema para tiendas y negocios en Colombia.">

    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <link rel="manifest" href="/manifest.json">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=fraunces:500,600,700|ibm-plex-sans:400,500,600,700|ibm-plex-mono:400,500,600&display=swap" rel="stylesheet">

    <style>
        :root {
            --bg: #F5F8FE;
            --bg-alt: #E9F0FE;
            --card: #FFFFFF;
            --ink: #121C33;
            --ink-soft: #4B5875;
            --ink-faint: #8492AC;
            --accent: #4F46E5;
            --accent-strong: #3730A3;
            --accent-tint: #E0E7FF;
            --peach: #F59E0B;
            --peach-tint: #FFEDD5;
            --line: #DCE4F5;
            --shadow: 0 20px 44px rgba(30, 41, 89, 0.10);
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #0A1120;
                --bg-alt: #101B32;
                --card: #131F38;
                --ink: #E7ECFB;
                --ink-soft: #A6B3CE;
                --ink-faint: #67759A;
                --accent: #818CF8;
                --accent-strong: #6366F1;
                --accent-tint: #1E2A4A;
                --peach: #FBBF61;
                --peach-tint: #3B2A14;
                --line: #223357;
                --shadow: 0 20px 44px rgba(0, 0, 0, 0.45);
            }
        }

        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
            margin: 0;
            background: var(--bg);
            color: var(--ink);
            font-family: 'IBM Plex Sans', system-ui, sans-serif;
            -webkit-font-smoothing: antialiased;
            overflow-x: hidden;
        }
        img { max-width: 100%; display: block; }
        a { color: inherit; }
        h1, h2, h3 { font-family: 'Fraunces', Georgia, serif; font-weight: 600; text-wrap: balance; margin: 0; }
        .mono { font-family: 'IBM Plex Mono', ui-monospace, monospace; }
        .wrap { max-width: 1080px; margin: 0 auto; padding: 0 24px; }
        section { position: relative; }

        /* ---------- Nav ---------- */
        header.nav {
            position: sticky; top: 0; z-index: 30;
            background: color-mix(in srgb, var(--bg) 88%, transparent);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--line);
        }
        .nav-row {
            display: flex; align-items: center; justify-content: space-between;
            padding: 16px 24px;
        }
        .brand { display: flex; align-items: center; gap: 10px; font-family: 'Fraunces', serif; font-weight: 600; font-size: 1.15rem; text-decoration: none; }
        .brand svg { flex-shrink: 0; }
        .nav-links { display: flex; align-items: center; gap: 28px; font-size: 0.92rem; font-weight: 500; }
        .nav-links a { text-decoration: none; color: var(--ink-soft); }
        .nav-links a:hover { color: var(--accent); }
        .btn {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 11px 22px; border-radius: 999px; font-weight: 600; font-size: 0.92rem;
            text-decoration: none; border: 1px solid transparent; cursor: pointer;
            transition: transform .15s ease, box-shadow .15s ease, background .15s ease;
        }
        .btn:hover { transform: translateY(-1px); }
        .btn-primary { background: var(--accent); color: #fff; box-shadow: 0 10px 24px rgba(79,70,229,.28); }
        .btn-primary:hover { background: var(--accent-strong); }
        .btn-ghost { background: transparent; border-color: var(--line); color: var(--ink); }
        .btn-ghost:hover { border-color: var(--accent); color: var(--accent); }
        .btn-peach { background: var(--peach); color: #402A06; box-shadow: 0 10px 24px rgba(245,158,11,.25); }
        .btn-peach:hover { filter: brightness(0.94); }

        /* ---------- Hero ---------- */
        .hero { background: var(--bg-alt); padding: 64px 0 0; }
        .hero-grid {
            display: grid; grid-template-columns: 1.05fr 1fr; gap: 48px; align-items: center;
            padding-bottom: 56px;
        }
        .eyebrow {
            display: inline-flex; align-items: center; gap: 8px;
            font-size: 0.78rem; font-weight: 600; letter-spacing: .08em; text-transform: uppercase;
            color: var(--accent-strong); background: var(--accent-tint);
            padding: 6px 14px; border-radius: 999px; margin-bottom: 18px;
        }
        .eyebrow::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: var(--accent); }
        .hero h1 { font-size: clamp(2.1rem, 4vw, 3.1rem); line-height: 1.12; letter-spacing: -0.01em; }
        .hero h1 em { font-style: normal; color: var(--accent); }
        .hero p.lede { font-size: 1.1rem; color: var(--ink-soft); max-width: 46ch; margin: 20px 0 30px; line-height: 1.6; }
        .hero-ctas { display: flex; gap: 14px; flex-wrap: wrap; }
        .hero-trust { margin-top: 30px; display: flex; gap: 22px; flex-wrap: wrap; font-size: .84rem; color: var(--ink-faint); }
        .hero-trust span { display: flex; align-items: center; gap: 7px; }
        .hero-trust svg { color: var(--accent); }

        .hero-shot { position: relative; }
        .hero-shot .frame {
            border-radius: 18px; overflow: hidden; box-shadow: var(--shadow);
            transform: rotate(1.2deg); border: 1px solid var(--line);
            background: var(--card);
        }
        .hero-shot .tag {
            position: absolute; bottom: -18px; left: -18px;
            background: var(--card); border: 1px solid var(--line); border-radius: 12px;
            padding: 12px 16px; box-shadow: var(--shadow);
            font-family: 'IBM Plex Mono', monospace; font-size: .82rem;
            transform: rotate(-3deg);
        }
        .hero-shot .tag b { color: var(--accent-strong); font-size: 1.05rem; }

        /* torn paper edge between hero and next section */
        .tear {
            height: 16px; background-color: var(--bg);
            background-image:
                linear-gradient(135deg, var(--bg-alt) 25%, transparent 25%),
                linear-gradient(225deg, var(--bg-alt) 25%, transparent 25%);
            background-position: 0 0;
            background-size: 16px 16px;
            background-repeat: repeat-x;
        }

        /* ---------- Sections generic ---------- */
        .section { padding: 84px 0; }
        .section-head { max-width: 640px; margin: 0 auto 44px; text-align: center; }
        .section-head .eyebrow { display: inline-flex; }
        .section-head h2 { font-size: clamp(1.7rem, 3vw, 2.3rem); margin-top: 6px; }
        .section-head p { color: var(--ink-soft); margin-top: 14px; line-height: 1.6; }

        /* ---------- Features ---------- */
        .features { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
        .feature {
            background: var(--card); border: 1px solid var(--line); border-radius: 16px;
            padding: 26px 24px; box-shadow: var(--shadow);
        }
        .feature .ico {
            width: 40px; height: 40px; border-radius: 10px; background: var(--accent-tint);
            display: flex; align-items: center; justify-content: center; color: var(--accent-strong);
            margin-bottom: 16px;
        }
        .feature h3 { font-size: 1.05rem; font-weight: 600; margin-bottom: 8px; }
        .feature p { color: var(--ink-soft); font-size: .92rem; line-height: 1.55; margin: 0; }
        .feature .ico.ico-peach { background: var(--peach-tint); color: var(--peach); }

        /* ---------- Por qué elegirnos ---------- */
        .benefits { margin-bottom: 56px; }

        /* ---------- Planes ---------- */
        .chip-row {
            display: flex; flex-wrap: wrap; justify-content: center; gap: 9px;
            max-width: 780px; margin: 0 auto 40px;
        }
        .chip-row span {
            font-size: .78rem; font-weight: 600; color: var(--accent-strong);
            background: var(--accent-tint); border-radius: 999px; padding: 6px 15px;
        }
        .plans-grid {
            display: grid; grid-template-columns: repeat(3, 1fr); gap: 22px;
            align-items: stretch; margin-bottom: 40px;
        }
        .plan-card {
            background: var(--card); border: 1px solid var(--line); border-radius: 20px;
            box-shadow: var(--shadow); padding: 32px 28px; position: relative; overflow: hidden;
            display: flex; flex-direction: column;
        }
        .plan-card::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0; height: 6px;
            background: linear-gradient(90deg, var(--accent), var(--peach));
        }
        .plan-card.is-featured {
            border-color: var(--accent); transform: translateY(-6px);
            box-shadow: 0 26px 54px rgba(79,70,229,.22);
        }
        .plan-badge {
            position: absolute; top: 18px; right: -34px; transform: rotate(40deg);
            background: var(--peach); color: #402A06; font-size: .68rem; font-weight: 700;
            letter-spacing: .05em; text-transform: uppercase; padding: 4px 40px;
        }
        .plan-card .duracion {
            font-size: .78rem; font-weight: 600; letter-spacing: .06em; text-transform: uppercase;
            color: var(--accent-strong); margin-bottom: 4px;
        }
        .plan-card h3 { font-size: 1.35rem; margin-bottom: 14px; }
        .plan-card .price {
            font-family: 'IBM Plex Mono', monospace; color: var(--ink); font-size: 1.7rem; font-weight: 600;
            margin-bottom: 2px;
        }
        .plan-card .price small { font-size: .6em; color: var(--ink-faint); font-weight: 500; }
        .plan-card .plan-meta { color: var(--ink-soft); font-size: .85rem; margin-bottom: 20px; }
        .plan-list { list-style: none; margin: 0 0 24px; padding: 0; display: grid; gap: 10px; flex-grow: 1; }
        .plan-list li { display: flex; gap: 9px; font-size: .88rem; color: var(--ink); }
        .plan-list svg { flex-shrink: 0; color: var(--accent); margin-top: 2px; }
        .plan-card .btn { width: 100%; justify-content: center; }

        /* ---------- Complementos y paquetes ---------- */
        .addons-grid {
            display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-top: 8px;
        }
        .addon-card {
            background: var(--card); border: 1px solid var(--line); border-radius: 16px;
            box-shadow: var(--shadow); padding: 26px 24px;
        }
        .addon-card h4 {
            font-family: 'Fraunces', serif; font-size: 1.1rem; font-weight: 600; margin: 0 0 14px;
        }
        .addon-list { list-style: none; margin: 0; padding: 0; display: grid; gap: 10px; }
        .addon-list li {
            display: flex; justify-content: space-between; gap: 12px; font-size: .88rem;
            color: var(--ink-soft); border-bottom: 1px dashed var(--line); padding-bottom: 10px;
        }
        .addon-list li:last-child { border-bottom: none; padding-bottom: 0; }
        .addon-list .valor { font-family: 'IBM Plex Mono', monospace; color: var(--accent-strong); font-weight: 600; white-space: nowrap; }

        /* ---------- Manual teaser ---------- */
        .manual-band {
            background: var(--bg-alt); border-top: 1px solid var(--line); border-bottom: 1px solid var(--line);
        }
        .manual-inner {
            display: flex; align-items: center; justify-content: space-between; gap: 24px;
            padding: 52px 0; flex-wrap: wrap;
        }
        .manual-inner h2 { font-size: 1.5rem; margin-bottom: 8px; }
        .manual-inner p { color: var(--ink-soft); margin: 0; max-width: 46ch; }

        /* ---------- Contacto (ticket) ---------- */
        .ticket {
            background: var(--card); border: 1px solid var(--line); border-radius: 4px;
            max-width: 420px; margin: 0 auto; box-shadow: var(--shadow); position: relative;
        }
        .ticket::before {
            content: ''; position: absolute; top: -8px; left: 0; right: 0; height: 8px;
            background-image:
                linear-gradient(135deg, var(--bg) 25%, transparent 25%),
                linear-gradient(225deg, var(--bg) 25%, transparent 25%);
            background-position: 0 0; background-size: 16px 16px; background-repeat: repeat-x;
        }
        .ticket-head { text-align: center; padding: 26px 24px 16px; border-bottom: 1px dashed var(--line); }
        .ticket-head .mono { font-size: .72rem; letter-spacing: .1em; color: var(--ink-faint); text-transform: uppercase; }
        .ticket-head h3 { font-size: 1.3rem; margin-top: 6px; }
        .ticket-rows { padding: 20px 24px; display: grid; gap: 18px; }
        .ticket-row { display: flex; align-items: flex-start; gap: 14px; text-decoration: none; color: var(--ink); }
        .ticket-row .ico { color: var(--accent); flex-shrink: 0; margin-top: 2px; }
        .ticket-row .label { font-size: .72rem; text-transform: uppercase; letter-spacing: .06em; color: var(--ink-faint); }
        .ticket-row .value { font-family: 'IBM Plex Mono', monospace; font-size: .98rem; }
        .ticket-row.link .value { color: var(--accent-strong); }
        .ticket-foot { text-align: center; padding: 4px 24px 26px; }
        .ticket-total {
            border-top: 1px dashed var(--line); margin-top: 4px; padding-top: 16px;
            display: flex; justify-content: space-between; font-family: 'IBM Plex Mono', monospace;
            font-size: .82rem; color: var(--ink-faint);
        }

        /* ---------- Footer ---------- */
        footer { padding: 40px 0; text-align: center; }
        footer p { color: var(--ink-faint); font-size: .82rem; margin: 4px 0; }
        footer a { text-decoration: underline; }

        /* ---------- WhatsApp flotante ---------- */
        .whatsapp-float {
            position: fixed; bottom: 22px; right: 22px; z-index: 50;
            display: inline-flex; align-items: center; gap: 9px;
            background: #25D366; color: #fff; text-decoration: none;
            padding: 14px 20px; border-radius: 999px; font-weight: 700; font-size: .9rem;
            box-shadow: 0 12px 28px rgba(37,211,102,.38);
            transition: transform .15s ease, box-shadow .15s ease;
        }
        .whatsapp-float:hover { transform: translateY(-2px); box-shadow: 0 16px 32px rgba(37,211,102,.45); }
        .whatsapp-float svg { flex-shrink: 0; }
        @media (max-width: 480px) {
            .whatsapp-float span { display: none; }
            .whatsapp-float { padding: 14px; }
        }

        @media (max-width: 860px) {
            .hero-grid { grid-template-columns: 1fr; }
            .hero-shot { order: -1; }
            .hero-shot .frame { transform: none; }
            .features { grid-template-columns: 1fr; }
            .nav-links { display: none; }
            .plans-grid { grid-template-columns: 1fr; }
            .plan-card.is-featured { transform: none; }
            .addons-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

    <header class="nav">
        <div class="wrap nav-row">
            <a href="/" class="brand">
                <svg width="30" height="30" viewBox="0 0 30 30" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect width="30" height="30" rx="8" fill="var(--accent)"/>
                    <rect x="7" y="9" width="2" height="12" fill="white"/>
                    <rect x="10.5" y="9" width="1" height="12" fill="white"/>
                    <rect x="13" y="9" width="3" height="12" fill="white"/>
                    <rect x="17.5" y="9" width="1.5" height="12" fill="white"/>
                    <rect x="20.5" y="9" width="2.5" height="12" fill="white"/>
                </svg>
                Sistema POS
            </a>
            <nav class="nav-links">
                <a href="#funciones">Funciones</a>
                <a href="#planes">Planes</a>
                <a href="/manual">Manual</a>
                <a href="#contacto">Contacto</a>
            </nav>
            <a href="/admin/login" class="btn btn-primary">Iniciar sesión</a>
        </div>
    </header>

    <section class="hero">
        <div class="wrap hero-grid">
            <div>
                <span class="eyebrow">Gestión de ventas y administración en la nube</span>
                <h1>Tu negocio no para. <em>Tu sistema tampoco.</em></h1>
                <p class="lede">Sistema de gestión de ventas y administrativo en la nube: ventas, inventario, compras y facturación electrónica, sincronizados en tiempo real y disponibles desde cualquier lugar.</p>
                <div class="hero-ctas">
                    <a href="/admin/login" class="btn btn-primary">
                        Iniciar sesión
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                    </a>
                    <a href="/manual" class="btn btn-ghost">Ver el manual</a>
                </div>
                <div class="hero-trust">
                    <span><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg> 100% en la nube, sin instalaciones</span>
                    <span><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg> Facturación electrónica DIAN</span>
                    <span><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg> Soporte en español</span>
                </div>
            </div>
            <div class="hero-shot">
                <div class="frame">
                    <img src="/images/login-pos.png" alt="Punto de venta del sistema corriendo en una caja registradora con impresora de tiquetes">
                </div>
                <div class="tag">Total del tiquete<br><b>$ 24.500</b></div>
            </div>
        </div>
        <div class="tear"></div>
    </section>

    <section class="section" id="funciones">
        <div class="wrap">
            <div class="section-head">
                <span class="eyebrow">Todo en un solo sistema</span>
                <h2>Lo que tu negocio necesita, sin módulos sueltos</h2>
                <p>Nada de pegar una app de ventas con un Excel de inventario. Un solo sistema para el mostrador, la bodega y las cuentas.</p>
            </div>
            <div class="features">
                <div class="feature">
                    <div class="ico"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3h2l2.6 13.4a2 2 0 002 1.6h9.7a2 2 0 002-1.6L23 8H6"/><circle cx="9" cy="21" r="1"/><circle cx="18" cy="21" r="1"/></svg></div>
                    <h3>Punto de venta</h3>
                    <p>Cobra en segundos, imprime el tiquete y sigue con la fila. Pensado para el ritmo del mostrador.</p>
                </div>
                <div class="feature">
                    <div class="ico"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/><path d="M3.27 6.96L12 12l8.73-5.04M12 22.08V12"/></svg></div>
                    <h3>Inventario y kardex</h3>
                    <p>Sabes cuánto tienes de cada producto en tiempo real, sin contar a ojo ni pelear con cuadernos.</p>
                </div>
                <div class="feature">
                    <div class="ico"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16v4H4zM4 10h16v10H4z"/><path d="M9 14h6"/></svg></div>
                    <h3>Compras con carga masiva</h3>
                    <p>Sube la factura del proveedor en Excel y el sistema crea o actualiza los productos solo.</p>
                </div>
                <div class="feature">
                    <div class="ico"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6M9 15l2 2 4-4"/></svg></div>
                    <h3>Facturación electrónica</h3>
                    <p>Factura ante la DIAN sin salir del sistema ni depender de otra plataforma aparte.</p>
                </div>
                <div class="feature">
                    <div class="ico"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg></div>
                    <h3>Multiempresa y roles</h3>
                    <p>Cada empleado ve solo lo que le corresponde: cajero, vendedor o administrador.</p>
                </div>
                <div class="feature">
                    <div class="ico"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg></div>
                    <h3>Catálogo público</h3>
                    <p>Comparte tus productos por un link de WhatsApp, sin que el cliente instale nada.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="section" id="planes" style="padding-top:0;">
        <div class="wrap">
            <div class="section-head">
                <span class="eyebrow">¿Por qué elegir Sistema POS?</span>
                <h2>Un sistema de gestión de ventas y administración, 100% en la nube</h2>
                <p>Nada instalado, nada que mantener: tu negocio corre en la nube y tú lo controlas desde donde estés.</p>
            </div>
            <div class="features benefits">
                <div class="feature">
                    <div class="ico"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.5 19H6a4 4 0 01-.4-7.98A5.5 5.5 0 0116.9 8.28 4.5 4.5 0 0117.5 19z"/></svg></div>
                    <h3>Tu negocio, siempre disponible</h3>
                    <p>Vende y consulta tus reportes desde el computador de la tienda, una tablet o tu celular — sin instalar nada ni depender de un solo equipo.</p>
                </div>
                <div class="feature">
                    <div class="ico ico-peach"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0114.13-3.36L23 10M1 14l5.36 5.36A9 9 0 0020.49 15"/></svg></div>
                    <h3>Todo conectado en tiempo real</h3>
                    <p>Cada venta, compra o ajuste de inventario se sincroniza al instante: sin duplicar información ni cuadrar cifras a mano.</p>
                </div>
                <div class="feature">
                    <div class="ico"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/></svg></div>
                    <h3>Clientes y proveedores en un solo lugar</h3>
                    <p>Cartera, contactos y compras organizados, listos para consultar en el momento que los necesites.</p>
                </div>
                <div class="feature">
                    <div class="ico ico-peach"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"/><rect x="7" y="13" width="3" height="5"/><rect x="12" y="9" width="3" height="9"/><rect x="17" y="5" width="3" height="13"/></svg></div>
                    <h3>Decisiones con datos, no con corazonadas</h3>
                    <p>Reportes en tiempo real para saber qué se vendió, qué falta y cuánto ganaste — sin esperar al cierre de mes.</p>
                </div>
                <div class="feature">
                    <div class="ico"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l8 4v6c0 5-3.5 8.5-8 10-4.5-1.5-8-5-8-10V6l8-4z"/></svg></div>
                    <h3>Tu información, siempre a salvo</h3>
                    <p>Copias de seguridad automáticas y actualizaciones constantes, sin que tengas que mover un dedo.</p>
                </div>
                <div class="feature">
                    <div class="ico ico-peach"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z"/></svg></div>
                    <h3>Nunca estás solo</h3>
                    <p>Soporte real por WhatsApp cuando algo no cuadra o simplemente tienes una duda.</p>
                </div>
            </div>

            <div class="section-head">
                <span class="eyebrow">Planes y tarifas</span>
                <h2>Un plan hecho a la medida de tu negocio</h2>
                <p>Cada negocio es distinto: no todos necesitan las mismas cajas, sucursales o usuarios. Elige el plan que más se ajuste al tuyo.</p>
            </div>

            <div class="chip-row">
                <span>Punto de Venta</span><span>Compras</span><span>Ventas</span><span>Inventario</span>
                <span>Clientes</span><span>Proveedores</span><span>Reportes</span><span>Copias de seguridad</span>
                <span>Actualizaciones</span><span>Soporte por WhatsApp</span>
            </div>

            <div class="plans-grid">
                <div class="plan-card">
                    <div class="duracion">Trimestral · 3 meses</div>
                    <h3>Plan Emprende</h3>
                    <div class="price">$330.000</div>
                    <div class="plan-meta">1 usuario incluido</div>
                    <ul class="plan-list">
                        <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg> Ideal para empezar y probar el sistema</li>
                        <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg> POS, inventario, compras y reportes</li>
                        <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg> Soporte por WhatsApp incluido</li>
                    </ul>
                    <a href="https://wa.me/573142627819?text=Hola%2C%20quiero%20cotizar%20el%20Plan%20Emprende%20(trimestral)" class="btn btn-ghost" target="_blank" rel="noopener">Cotizar por WhatsApp</a>
                </div>

                <div class="plan-card">
                    <div class="duracion">Semestral · 6 meses</div>
                    <h3>Plan Crece</h3>
                    <div class="price">$600.000</div>
                    <div class="plan-meta">2 usuarios incluidos</div>
                    <ul class="plan-list">
                        <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg> Para negocios que ya están vendiendo con regularidad</li>
                        <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg> Un usuario adicional para tu equipo</li>
                        <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg> Mismo soporte y actualizaciones constantes</li>
                    </ul>
                    <a href="https://wa.me/573142627819?text=Hola%2C%20quiero%20cotizar%20el%20Plan%20Crece%20(semestral)" class="btn btn-ghost" target="_blank" rel="noopener">Cotizar por WhatsApp</a>
                </div>

                <div class="plan-card is-featured">
                    <div class="plan-badge">Recomendado</div>
                    <div class="duracion">Anual · 12 meses</div>
                    <h3>Plan Empresarial</h3>
                    <div class="price">$1.000.000</div>
                    <div class="plan-meta">3 usuarios incluidos</div>
                    <ul class="plan-list">
                        <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg> El mejor precio por mes de todos los planes</li>
                        <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg> 3 usuarios para tu equipo completo</li>
                        <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg> Acompañamiento prioritario por WhatsApp</li>
                    </ul>
                    <a href="https://wa.me/573142627819?text=Hola%2C%20quiero%20cotizar%20el%20Plan%20Empresarial%20(anual)" class="btn btn-primary" target="_blank" rel="noopener">Cotizar por WhatsApp</a>
                </div>
            </div>

            <div class="addons-grid">
                <div class="addon-card">
                    <h4>Complementos</h4>
                    <ul class="addon-list">
                        <li><span>Facturación Electrónica (Activación)</span><span class="valor">$800.000</span></li>
                        <li><span>Renovación anual Facturación Electrónica</span><span class="valor">$800.000</span></li>
                        <li><span>POS Híbrido (por equipo)</span><span class="valor">$600.000</span></li>
                    </ul>
                </div>
                <div class="addon-card">
                    <h4>Paquetes de usuarios adicionales</h4>
                    <ul class="addon-list">
                        <li><span>1 usuario adicional</span><span class="valor">$160.000/año</span></li>
                        <li><span>Hasta 3 usuarios adicionales</span><span class="valor">$400.000/año</span></li>
                        <li><span>Hasta 5 usuarios adicionales</span><span class="valor">$600.000/año</span></li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <section class="manual-band">
        <div class="wrap manual-inner">
            <div>
                <h2>¿Ya usas el sistema?</h2>
                <p>El manual explica paso a paso cada módulo: ventas, inventario, compras, facturación y más, para todos los roles del negocio.</p>
            </div>
            <a href="/manual" class="btn btn-primary">
                Abrir el manual completo
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
            </a>
        </div>
    </section>

    <section class="section" id="contacto">
        <div class="wrap">
            <div class="section-head">
                <span class="eyebrow">Contacto</span>
                <h2>Hablemos de tu negocio</h2>
                <p>Escríbeme directo y te cuento cómo funciona el sistema en un negocio como el tuyo.</p>
            </div>
            <div class="ticket">
                <div class="ticket-head">
                    <div class="mono">Sistema POS · Contacto</div>
                    <h3>Deimer Villamizar</h3>
                </div>
                <div class="ticket-rows">
                    <a href="https://wa.me/573142627819" target="_blank" rel="noopener" class="ticket-row link">
                        <span class="ico"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21l1.65-4.95A9 9 0 1112 21a8.96 8.96 0 01-4.95-1.5L3 21z"/></svg></span>
                        <span><span class="label">WhatsApp</span><br><span class="value">+57 314 262 7819</span></span>
                    </a>
                    <a href="mailto:deimervillamizar@gmail.com" class="ticket-row link">
                        <span class="ico"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16v16H4z"/><path d="M22 6l-10 7L2 6"/></svg></span>
                        <span><span class="label">Correo</span><br><span class="value">deimervillamizar@gmail.com</span></span>
                    </a>
                    <div class="ticket-row">
                        <span class="ico"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg></span>
                        <span><span class="label">Ubicación</span><br><span class="value">Saravena, Arauca</span></span>
                    </div>
                </div>
                <div class="ticket-foot">
                    <div class="ticket-total">
                        <span>GRACIAS POR ESCRIBIR</span>
                        <span>*** ***</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <footer>
        <div class="wrap">
            <p>© {{ date('Y') }} Sistema POS — Hecho en Saravena, Arauca, Colombia.</p>
            <p><a href="/manual">Manual de usuario</a> · <a href="/admin/login">Iniciar sesión</a></p>
        </div>
    </footer>

    <a href="https://wa.me/573142627819?text=Hola%2C%20quiero%20m%C3%A1s%20informaci%C3%B3n%20sobre%20el%20Sistema%20POS" class="whatsapp-float" target="_blank" rel="noopener" aria-label="Escribir por WhatsApp">
        <svg width="22" height="22" viewBox="0 0 32 32" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M16.001 3C9.373 3 4 8.373 4 15c0 2.354.687 4.548 1.872 6.393L4 29l7.828-2.05A11.94 11.94 0 0016.001 27C22.628 27 28 21.627 28 15S22.628 3 16.001 3zm0 21.75c-1.93 0-3.727-.55-5.253-1.5l-.377-.223-4.65 1.219 1.242-4.53-.246-.39A9.71 9.71 0 016.25 15c0-5.383 4.368-9.75 9.751-9.75S25.75 9.617 25.75 15 21.384 24.75 16.001 24.75zm5.36-7.31c-.294-.147-1.74-.858-2.01-.956-.27-.098-.467-.147-.663.147-.196.294-.76.956-.932 1.152-.171.196-.343.22-.637.073-.294-.147-1.243-.458-2.368-1.462-.875-.78-1.466-1.744-1.638-2.038-.171-.294-.018-.453.129-.6.133-.132.294-.343.441-.514.147-.171.196-.294.294-.49.098-.196.049-.368-.024-.514-.073-.147-.663-1.597-.909-2.187-.24-.577-.483-.5-.663-.51l-.564-.01c-.196 0-.514.073-.784.368-.27.294-1.03 1.007-1.03 2.455s1.055 2.845 1.202 3.04c.147.196 2.077 3.172 5.033 4.448.703.303 1.252.484 1.68.62.706.225 1.348.193 1.855.117.566-.084 1.74-.712 1.985-1.4.245-.688.245-1.278.171-1.4-.073-.123-.27-.196-.564-.343z"/></svg>
        <span>Contáctame</span>
    </a>

</body>
</html>
