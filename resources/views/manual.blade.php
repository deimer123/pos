<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manual del Sistema POS</title>
    <meta name="description" content="Manual de uso del Sistema POS: punto de venta, inventario, compras, productos, facturación electrónica y más, módulo por módulo.">

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

        /* ---------- Nav (shared with landing) ---------- */
        header.nav {
            position: sticky; top: 0; z-index: 30;
            background: color-mix(in srgb, var(--bg) 88%, transparent);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--line);
        }
        .nav-row {
            display: flex; align-items: center; justify-content: space-between;
            padding: 16px 24px; gap: 20px; flex-wrap: wrap;
        }
        .brand { display: flex; align-items: center; gap: 10px; font-family: 'Fraunces', serif; font-weight: 600; font-size: 1.15rem; text-decoration: none; }
        .brand svg { flex-shrink: 0; }
        .nav-links { display: flex; align-items: center; gap: 24px; font-size: 0.92rem; font-weight: 500; }
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

        .eyebrow {
            display: inline-flex; align-items: center; gap: 8px;
            font-size: 0.78rem; font-weight: 600; letter-spacing: .08em; text-transform: uppercase;
            color: var(--accent-strong); background: var(--accent-tint);
            padding: 6px 14px; border-radius: 999px; margin-bottom: 18px;
        }
        .eyebrow::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: var(--accent); }

        /* ---------- Manual hero ---------- */
        .manual-hero { background: var(--bg-alt); border-bottom: 1px solid var(--line); padding: 56px 0 44px; }
        .manual-hero h1 { font-size: clamp(1.9rem, 3.6vw, 2.7rem); line-height: 1.15; letter-spacing: -0.01em; }
        .manual-hero p { color: var(--ink-soft); max-width: 68ch; margin: 16px 0 0; font-size: 1.05rem; line-height: 1.6; }

        /* ---------- Shell: TOC + content ---------- */
        .manual-shell {
            display: grid; grid-template-columns: 260px minmax(0, 1fr); gap: 40px;
            padding: 44px 0 100px;
            align-items: start;
        }
        .toc {
            position: sticky; top: 92px;
            max-height: calc(100vh - 120px); overflow-y: auto;
            background: var(--card); border: 1px solid var(--line); border-radius: 16px;
            box-shadow: var(--shadow); padding: 20px 18px;
        }
        .toc-group { margin-bottom: 4px; }
        .toc-group-label {
            font-family: 'IBM Plex Mono', monospace; font-size: 10.5px; letter-spacing: .1em;
            text-transform: uppercase; color: var(--ink-faint); padding: 14px 8px 6px;
        }
        .toc a {
            display: block; padding: 6px 8px; border-radius: 7px;
            color: var(--ink-soft); text-decoration: none; font-size: 13.6px;
        }
        .toc a:hover { background: var(--accent-tint); color: var(--accent-strong); }

        main.manual-content { min-width: 0; }

        section.mod { padding-top: 90px; margin-top: -90px; }

        .mod-head { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; margin-bottom: 6px; }
        .tag {
            font-family: 'IBM Plex Mono', monospace; font-size: 10.5px; letter-spacing: .06em;
            text-transform: uppercase; padding: 3px 10px; border-radius: 999px; font-weight: 600;
            white-space: nowrap; color: var(--accent-strong); background: var(--accent-tint);
        }
        .tag-gold { color: #92400E; background: var(--peach-tint); }

        .mod h2 { font-size: 1.7rem; margin: 6px 0 4px; }
        .mod .lede { color: var(--ink-soft); max-width: 68ch; margin: 0 0 18px; line-height: 1.6; }

        .mod-divider { border: none; border-top: 1px solid var(--line); margin: 48px 0 0; }

        h3.sub { font-family: 'IBM Plex Sans', sans-serif; font-size: 1rem; font-weight: 700; margin: 26px 0 10px; color: var(--ink); }

        .card {
            background: var(--card); border: 1px solid var(--line); border-radius: 16px;
            padding: 20px 22px; margin: 14px 0; box-shadow: var(--shadow);
        }

        ol.steps { margin: 0; padding-left: 0; list-style: none; counter-reset: step; }
        ol.steps li { counter-increment: step; position: relative; padding-left: 34px; margin-bottom: 12px; line-height: 1.55; }
        ol.steps li:last-child { margin-bottom: 0; }
        ol.steps li::before {
            content: counter(step); position: absolute; left: 0; top: 0;
            width: 22px; height: 22px; border-radius: 50%;
            background: var(--accent-tint); color: var(--accent-strong); border: 1px solid var(--line);
            font-family: 'IBM Plex Mono', monospace; font-size: 11.5px; font-weight: 700;
            display: flex; align-items: center; justify-content: center;
        }

        ul.plain { margin: 0; padding-left: 20px; }
        ul.plain li { margin-bottom: 7px; line-height: 1.55; }
        ul.plain li:last-child { margin-bottom: 0; }

        .kbd, code.ui {
            font-family: 'IBM Plex Mono', monospace; font-size: 12.5px;
            background: var(--bg-alt); border: 1px solid var(--line); border-radius: 5px;
            padding: 1.5px 6px; color: var(--ink); white-space: nowrap;
        }

        .grid-2 { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 14px; margin: 14px 0; }
        .mini-card { border: 1px solid var(--line); border-radius: 12px; padding: 16px 18px; background: var(--card); }
        .mini-card h4 { margin: 0 0 6px; font-size: 14px; font-family: 'IBM Plex Sans', sans-serif; font-weight: 700; }
        .mini-card p { margin: 0; color: var(--ink-soft); font-size: 13.6px; line-height: 1.55; }

        .note {
            display: flex; gap: 10px; background: var(--peach-tint); border: 1px solid var(--line);
            border-radius: 12px; padding: 14px 16px; margin: 16px 0; font-size: 13.8px; color: var(--ink); line-height: 1.55;
        }
        .note strong { color: var(--peach); }

        figure.shot { margin: 20px 0; padding: 0; }
        /* Las capturas originales incluyen la barra de pestañas del navegador
           y la barra de tareas de Windows. Se recortan visualmente con
           object-fit/object-position en vez de re-exportar los 12 PNG, dejando
           solo el contenido real de la pantalla. */
        .shot-crop {
            overflow: hidden; border-radius: 14px;
            border: 1px solid var(--line); box-shadow: var(--shadow);
        }
        .shot-crop img {
            display: block; width: 100%; height: 100%;
            object-fit: cover; object-position: 50% 72%;
        }
        figure.shot figcaption {
            margin-top: 8px; font-family: 'IBM Plex Mono', monospace; font-size: 11.5px;
            letter-spacing: .03em; color: var(--ink-faint); text-align: center;
        }

        table.field-table { width: 100%; border-collapse: collapse; font-size: 13.6px; margin: 14px 0; }
        table.field-table th, table.field-table td {
            text-align: left; padding: 10px 12px; border-bottom: 1px solid var(--line); vertical-align: top;
        }
        table.field-table th {
            font-family: 'IBM Plex Mono', monospace; font-size: 10.8px; letter-spacing: .06em;
            text-transform: uppercase; color: var(--ink-faint); font-weight: 600;
        }
        .table-wrap { overflow-x: auto; background: var(--card); border: 1px solid var(--line); border-radius: 16px; box-shadow: var(--shadow); padding: 4px 18px; margin: 14px 0; }
        .table-wrap table { margin: 10px 0; }

        .manual-mobile-toc { display: none; }

        /* ---------- Footer (shared with landing) ---------- */
        footer { padding: 40px 0; text-align: center; }
        footer p { color: var(--ink-faint); font-size: .82rem; margin: 4px 0; }
        footer a { text-decoration: underline; }

        @media (max-width: 860px) {
            .nav-links { display: none; }
            .manual-shell { grid-template-columns: 1fr; padding-top: 24px; }
            .toc { position: static; max-height: none; }
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
                <a href="/">← Volver al inicio</a>
            </nav>
            <a href="/admin/login" class="btn btn-primary">Iniciar sesión</a>
        </div>
    </header>

    <section class="manual-hero">
        <div class="wrap">
            <span class="eyebrow">Guía de referencia</span>
            <h1>Manual del sistema POS</h1>
            <p>
                Esta guía explica, módulo por módulo, qué hace cada parte del sistema y los pasos para usarla —
                desde vender en el mostrador hasta cerrar la caja, cargar productos por Excel o configurar
                facturación electrónica. Usa el índice para saltar directo a lo que necesitas.
            </p>
        </div>
    </section>

    <div class="wrap manual-shell">
        <aside class="toc">
            <div class="toc-group">
                <div class="toc-group-label">Empezar</div>
                <a href="#inicio">Iniciar sesión y elegir panel</a>
                <a href="#roles">Roles y qué ve cada uno</a>
            </div>

            <div class="toc-group">
                <div class="toc-group-label">Punto de venta</div>
                <a href="#vender">Vender un producto</a>
                <a href="#cliente-venta">Elegir o crear cliente</a>
                <a href="#prefacturas">Prefacturas</a>
                <a href="#facturar">Facturar (contado / crédito)</a>
                <a href="#caja">Caja</a>
            </div>

            <div class="toc-group">
                <div class="toc-group-label">Negocios especiales</div>
                <a href="#mesas">Restaurante · Mesas y domicilios</a>
                <a href="#cocina">Cocina</a>
                <a href="#taller">Taller mecánico</a>
                <a href="#hotel">Hotel</a>
                <a href="#catalogo">Catálogo público</a>
            </div>

            <div class="toc-group">
                <div class="toc-group-label">Administración</div>
                <a href="#productos">Productos</a>
                <a href="#compras">Compras</a>
                <a href="#terceros">Clientes y proveedores</a>
                <a href="#categorias">Departamentos y subfamilias</a>
                <a href="#inventario">Inventario y kardex</a>
                <a href="#combos-recetas">Combos, recetas y servicios</a>
                <a href="#notas-credito">Notas crédito y devoluciones</a>
                <a href="#caja-admin">Movimientos de caja</a>
                <a href="#empleados">Empleados y permisos</a>
                <a href="#configuracion">Configuración de la empresa</a>
                <a href="#facturacion-electronica">Facturación electrónica</a>
            </div>

            <div class="toc-group">
                <div class="toc-group-label">Reportes</div>
                <a href="#reportes">Qué reporte mirar según la pregunta</a>
                <a href="#contabilidad">Contabilidad</a>
            </div>
        </aside>

        <main class="manual-content">

            <!-- ============ EMPEZAR ============ -->
            <section class="mod" id="inicio">
                <div class="mod-head"><span class="tag">Empezar</span></div>
                <h2>Iniciar sesión y elegir panel</h2>
                <p class="lede">
                    Al iniciar sesión, el sistema te lleva a un lugar distinto según tu rol y lo que tu empresa tiene activado.
                </p>

                <div class="card">
                    <ol class="steps">
                        <li>Entra a la dirección del sistema y escribe tu usuario y contraseña.</li>
                        <li>
                            Si tienes varios destinos posibles (por ejemplo Punto de Venta y Taller), verás la pantalla
                            <strong>&ldquo;¿A dónde deseas ingresar?&rdquo;</strong> con una tarjeta por cada uno.
                            Si solo tienes uno, el sistema te lleva directo ahí.
                        </li>
                        <li>Desde el Punto de Venta o el panel administrativo, puedes volver a esta pantalla en cualquier momento.</li>
                    </ol>
                </div>

                <div class="note">
                    <span>&#8505;</span>
                    <span><strong>Nota:</strong> si tu sesión se abre en otro dispositivo, la sesión anterior se cierra automáticamente — el sistema no permite dos sesiones activas a la vez con el mismo usuario.</span>
                </div>
            </section>

            <hr class="mod-divider" />

            <section class="mod" id="roles">
                <div class="mod-head"><span class="tag">Empezar</span></div>
                <h2>Roles y qué ve cada uno</h2>
                <p class="lede">
                    El sistema no usa permisos fijos por rol: el dueño del negocio (<code class="ui">admin_empresa</code>)
                    decide, empleado por empleado, qué botones del POS y qué secciones del panel administrativo puede ver cada quien.
                </p>

                <div class="table-wrap">
                    <table class="field-table">
                        <thead>
                            <tr><th>Rol</th><th>Para qué es</th></tr>
                        </thead>
                        <tbody>
                            <tr><td><code class="ui">admin_empresa</code></td><td>Dueño o gerente del negocio. Ve todo: POS completo y todo el panel administrativo.</td></tr>
                            <tr><td><code class="ui">cajero</code> / <code class="ui">vendedor</code></td><td>Uso diario del Punto de Venta. No entran al panel administrativo.</td></tr>
                            <tr><td><code class="ui">digitador</code></td><td>Entra al panel administrativo (no al POS) con acceso a los módulos que el dueño le habilite: Productos, Compras, Clientes, etc.</td></tr>
                            <tr><td><code class="ui">taller</code></td><td>Solo el panel de Taller: órdenes de servicio, repuestos y facturación de la orden.</td></tr>
                            <tr><td><code class="ui">recepcion</code></td><td>Solo el panel de Hotel: habitaciones, reservas y check-in/out.</td></tr>
                            <tr><td><code class="ui">mesero</code></td><td>Panel de Mesas: tomar pedidos y enviarlos a cocina.</td></tr>
                            <tr><td><code class="ui">cocina</code></td><td>Solo la pantalla de Cocina: ver y marcar comandas.</td></tr>
                        </tbody>
                    </table>
                </div>

                <p>Cómo se configura qué ve cada empleado está explicado en <a href="#empleados">Empleados y permisos</a>.</p>
            </section>

            <!-- ============ POS ============ -->
            <section class="mod" id="vender">
                <div class="mod-head"><span class="tag">Punto de venta</span></div>
                <h2>Vender un producto</h2>
                <p class="lede">
                    La pantalla de ventas tiene la lista de productos a la izquierda y el carrito de la venta actual a la derecha.
                </p>

                <div class="card">
                    <ol class="steps">
                        <li>Escribe el nombre o código del producto en el buscador — la lista se filtra mientras escribes.</li>
                        <li>Dale clic a <span class="kbd">Agregar</span> en el producto, o escanéalo con lector de código de barras.</li>
                        <li>Si necesitas cambiar la cantidad o el precio de una línea, edítala directo en el carrito.</li>
                        <li>Para un ítem que no está en el catálogo (por ejemplo un reembolso a terceros), escribe <span class="kbd">10001</span> en el buscador para abrir el formulario de producto temporal.</li>
                    </ol>
                </div>

                <figure class="shot"><div class="shot-crop" style="aspect-ratio: 1919 / 885;"><img src="/images/manual/manual-01.png" alt="Punto de venta" loading="lazy" /></div><figcaption>Punto de venta</figcaption></figure>

                <div class="note">
                    <span>&#127991;&#65039;</span>
                    <span><strong>Verificar precio:</strong> el botón con el ícono de etiqueta, junto al buscador, abre una ventana para consultar el precio de un producto sin agregarlo al carrito — útil cuando un cliente solo pregunta.</span>
                </div>
            </section>

            <hr class="mod-divider" />

            <section class="mod" id="cliente-venta">
                <div class="mod-head"><span class="tag">Punto de venta</span></div>
                <h2>Elegir o crear cliente</h2>
                <p class="lede">Toda venta queda asociada a un cliente; si no eliges uno, queda a nombre de Consumidor Final.</p>

                <div class="card">
                    <ul class="plain">
                        <li><span class="kbd">Buscar Cliente</span> — busca por nombre o documento y lo deja seleccionado para la venta.</li>
                        <li><span class="kbd">+ Cliente</span> — crea un cliente nuevo sin salir del punto de venta.</li>
                        <li>Si tu perfil tiene el permiso habilitado, puedes editar el teléfono o correo del cliente directo desde el modal de selección.</li>
                        <li><span class="kbd">Cartera</span> — muestra las facturas pendientes de pago del cliente elegido, con su saldo.</li>
                    </ul>
                </div>
            </section>

            <hr class="mod-divider" />

            <section class="mod" id="prefacturas">
                <div class="mod-head"><span class="tag">Punto de venta</span></div>
                <h2>Prefacturas</h2>
                <p class="lede">
                    Una prefactura es una venta guardada sin facturar todavía — útil para apartar un carrito y retomarlo después,
                    o para clientes que quieren revisar antes de pagar.
                </p>

                <div class="grid-2">
                    <div class="mini-card">
                        <h4>Guardar</h4>
                        <p>Con productos en el carrito, usa la opción de guardar prefactura. Queda en estado &ldquo;borrador&rdquo; hasta que la cargues de nuevo.</p>
                    </div>
                    <div class="mini-card">
                        <h4>Gestión de Prefacturas</h4>
                        <p>Lista todas las prefacturas guardadas. Desde ahí: <span class="kbd">Cargar</span> (la trae de vuelta al carrito), <span class="kbd">Facturar</span> (la carga y abre el cobro de una vez), <span class="kbd">Imprimir</span> o <span class="kbd">Borrar</span>.</p>
                    </div>
                </div>

                <figure class="shot"><div class="shot-crop" style="aspect-ratio: 1919 / 885;"><img src="/images/manual/manual-02.png" alt="Gestion de Prefacturas" loading="lazy" /></div><figcaption>Gestion de Prefacturas</figcaption></figure>

                <div class="note">
                    <span>&#8505;</span>
                    <span>Solo puedes cargar una prefactura si el carrito está vacío — si tienes productos sueltos, límpialos primero.</span>
                </div>
            </section>

            <hr class="mod-divider" />

            <section class="mod" id="facturar">
                <div class="mod-head"><span class="tag">Punto de venta</span></div>
                <h2>Facturar (contado / crédito)</h2>

                <div class="card">
                    <ol class="steps">
                        <li>Con el carrito listo, dale clic a <span class="kbd">Facturar</span>.</li>
                        <li>Revisa cliente y total en la ventana de confirmación.</li>
                        <li>Elige tipo de venta, tipo de pago (Contado / Crédito) y medio de pago (Efectivo, Transferencia, etc.).</li>
                        <li>Si es contado, escribe el valor recibido — los botones <span class="kbd">+5.000</span>, <span class="kbd">+10.000</span>… ayudan a calcular rápido, y <span class="kbd">Exacto</span> deja el vuelto en $0.</li>
                        <li>Marca &ldquo;Imprimir comprobante&rdquo; si el cliente quiere el ticket impreso.</li>
                        <li>Dale <span class="kbd">Facturar</span> para confirmar. El carrito se limpia y las existencias del producto bajan de una vez.</li>
                    </ol>
                </div>

                <figure class="shot"><div class="shot-crop" style="aspect-ratio: 1919 / 883;"><img src="/images/manual/manual-03.png" alt="Confirmar factura" loading="lazy" /></div><figcaption>Confirmar factura</figcaption></figure>

                <div class="note">
                    <span>&#8505;</span>
                    <span>Crédito solo aparece disponible si el cliente tiene cupo habilitado y saldo suficiente — si no, el sistema no deja seleccionar esa opción.</span>
                </div>
            </section>

            <hr class="mod-divider" />

            <section class="mod" id="caja">
                <div class="mod-head"><span class="tag">Punto de venta</span></div>
                <h2>Caja</h2>
                <p class="lede">Cada turno de venta abre y cierra su propia caja, para cuadrar el efectivo al final del día.</p>

                <div class="grid-2">
                    <div class="mini-card">
                        <h4>Abrir caja</h4>
                        <p>Al entrar al POS por primera vez en el día, la caja se abre en $0 automáticamente.</p>
                    </div>
                    <div class="mini-card">
                        <h4>Entrada / Salida</h4>
                        <p>Registra movimientos de efectivo que no son ventas: un retiro, una consignación, un gasto menor.</p>
                    </div>
                    <div class="mini-card">
                        <h4>Cerrar caja</h4>
                        <p>Al final del turno, muestra el resumen de ventas por medio de pago para cuadrar contra el efectivo físico.</p>
                    </div>
                </div>

                <figure class="shot"><div class="shot-crop" style="aspect-ratio: 1919 / 880;"><img src="/images/manual/manual-04.png" alt="Cerrar caja" loading="lazy" /></div><figcaption>Cerrar caja</figcaption></figure>
            </section>

            <!-- ============ VERTICALES ============ -->
            <section class="mod" id="mesas">
                <div class="mod-head"><span class="tag tag-gold">Restaurante</span></div>
                <h2>Mesas y domicilios</h2>
                <p class="lede">
                    Si tu negocio tiene el módulo de Mesas activo, el Punto de Venta arranca en un mapa visual de mesas
                    en vez de la lista de productos directa.
                </p>

                <div class="card">
                    <ol class="steps">
                        <li>En el mapa, dale clic a una mesa libre para abrir su cuenta.</li>
                        <li>Agrega productos igual que en el POS normal — quedan asociados a esa mesa.</li>
                        <li><span class="kbd">Enviar cocina</span> manda los ítems nuevos a la pantalla de Cocina.</li>
                        <li>Puedes ir y volver entre mesas sin perder lo que llevaba cada una.</li>
                        <li>Cuando el cliente pide la cuenta, factura igual que una venta normal — la mesa queda libre otra vez.</li>
                    </ol>
                </div>

                <p>
                    <strong>Domicilios</strong> funciona igual, pero en vez de una mesa física registras nombre, teléfono y
                    dirección del pedido, y opcionalmente un costo de domicilio o de empaque.
                </p>
            </section>

            <hr class="mod-divider" />

            <section class="mod" id="cocina">
                <div class="mod-head"><span class="tag tag-gold">Restaurante</span></div>
                <h2>Cocina</h2>
                <p class="lede">Pantalla pensada para dejarla abierta en cocina, sin necesidad de tocar el mouse.</p>
                <div class="card">
                    <ul class="plain">
                        <li>Muestra las comandas enviadas desde las mesas, agrupadas por pedido.</li>
                        <li>Cada ítem se marca como &ldquo;en preparación&rdquo; y después &ldquo;listo&rdquo; según va avanzando.</li>
                        <li>El mesero ve ese estado reflejado en su propio panel, sin tener que preguntar en cocina.</li>
                    </ul>
                </div>
            </section>

            <hr class="mod-divider" />

            <section class="mod" id="taller">
                <div class="mod-head"><span class="tag tag-gold">Taller</span></div>
                <h2>Taller mecánico</h2>
                <p class="lede">Gestiona órdenes de servicio por vehículo, con sus repuestos, mano de obra y mecánico asignado.</p>

                <div class="card">
                    <ol class="steps">
                        <li>Crea una orden nueva con placa, marca, kilometraje y el diagnóstico o motivo de ingreso.</li>
                        <li>Agrega los repuestos usados (descuentan del inventario) y los servicios de mano de obra.</li>
                        <li>Asigna un mecánico — su comisión se calcula según el % configurado para ese servicio.</li>
                        <li>Cuando el vehículo está listo, factura la orden desde su propia pantalla de detalle.</li>
                    </ol>
                </div>

                <figure class="shot"><div class="shot-crop" style="aspect-ratio: 1919 / 884;"><img src="/images/manual/manual-05.png" alt="Panel de Taller" loading="lazy" /></div><figcaption>Panel de Taller</figcaption></figure>
                <figure class="shot"><div class="shot-crop" style="aspect-ratio: 1919 / 885;"><img src="/images/manual/manual-06.png" alt="Detalle de una orden de Taller" loading="lazy" /></div><figcaption>Detalle de una orden de Taller</figcaption></figure>

                <p>
                    La liquidación de comisiones de cada mecánico se revisa en
                    <a href="#reportes">Reportes → Mecánicos</a>.
                </p>
            </section>

            <hr class="mod-divider" />

            <section class="mod" id="hotel">
                <div class="mod-head"><span class="tag tag-gold">Hotel</span></div>
                <h2>Hotel</h2>
                <p class="lede">Administra habitaciones y reservas con un calendario de ocupación.</p>
                <div class="card">
                    <ul class="plain">
                        <li><strong>Habitaciones</strong> — número, zona y tarifa de cada una, y su estado actual (libre, ocupada, en aseo).</li>
                        <li><strong>Reservas</strong> — vincula un huésped a una habitación por un rango de fechas; el check-in y check-out se marcan desde ahí.</li>
                        <li>La hora de corte del día hotelero (a qué hora empieza &ldquo;el día siguiente&rdquo; para efectos de cobro) se configura una sola vez para todo el negocio.</li>
                    </ul>
                </div>
            </section>

            <hr class="mod-divider" />

            <section class="mod" id="catalogo">
                <div class="mod-head"><span class="tag tag-gold">Catálogo</span></div>
                <h2>Catálogo público</h2>
                <p class="lede">Una vitrina web de tus productos, sin necesidad de que el cliente inicie sesión — para compartir por WhatsApp o redes.</p>
                <div class="card">
                    <ol class="steps">
                        <li>Crea un catálogo y elige qué productos incluir.</li>
                        <li>Decide si se muestra el precio y si se muestra la disponibilidad/stock.</li>
                        <li>Comparte el link público que genera el sistema — cualquiera puede verlo sin cuenta.</li>
                    </ol>
                </div>
            </section>

            <!-- ============ ADMINISTRACIÓN ============ -->
            <section class="mod" id="productos">
                <div class="mod-head"><span class="tag">Administración</span></div>
                <h2>Productos</h2>
                <p class="lede">El catálogo completo de lo que vendes: nombre, proveedor, categoría, precios, impuestos y existencias.</p>

                <figure class="shot"><div class="shot-crop" style="aspect-ratio: 1919 / 885;"><img src="/images/manual/manual-07.png" alt="Listado de Productos" loading="lazy" /></div><figcaption>Listado de Productos</figcaption></figure>

                <h3 class="sub">Crear un producto</h3>
                <div class="card">
                    <p>El código se asigna solo. Completa nombre, proveedor, departamento y subfamilia, unidad de medida, costo, descuento comercial, IVA de compra y venta, y precio de venta (o la utilidad, y el sistema calcula el precio).</p>
                </div>

                <figure class="shot"><div class="shot-crop" style="aspect-ratio: 1919 / 864;"><img src="/images/manual/manual-08.png" alt="Crear Producto" loading="lazy" /></div><figcaption>Crear Producto</figcaption></figure>

                <h3 class="sub">Carga masiva por Excel</h3>
                <div class="card">
                    <ol class="steps">
                        <li>Desde el listado de Productos, botón <span class="kbd">Carga masiva</span>.</li>
                        <li>Descarga la plantilla — trae ejemplo, instrucciones y una hoja con tus departamentos/subfamilias existentes.</li>
                        <li>Llena una fila por producto: nombre, proveedor, departamento, subfamilia, costo y precio de venta son lo mínimo.</li>
                        <li>Si el proveedor/departamento/subfamilia que escribes no existe, se crea solo; si lo dejas vacío, usa un genérico editable después.</li>
                        <li>Sube el archivo lleno y confirma.</li>
                    </ol>
                </div>
            </section>

            <hr class="mod-divider" />

            <section class="mod" id="compras">
                <div class="mod-head"><span class="tag">Administración</span></div>
                <h2>Compras</h2>
                <p class="lede">Registrar lo que le compras a un proveedor: sube las existencias y actualiza el costo del producto automáticamente.</p>

                <h3 class="sub">Registrar una compra</h3>
                <div class="card">
                    <p>Elige proveedor, número de factura, tipo de pago y fecha; agrega los productos comprados con cantidad y costo. El buscador de producto solo muestra los de ese proveedor.</p>
                </div>

                <h3 class="sub">Carga masiva por Excel</h3>
                <div class="card">
                    <ol class="steps">
                        <li>Desde el listado de Compras, botón <span class="kbd">Carga masiva</span>.</li>
                        <li>Elige el Proveedor primero (obligatorio) y llena N° de factura, tipo de pago y fecha en la pantalla.</li>
                        <li>Descarga la plantilla de ítems — trae el desplegable de productos de ese proveedor y una hoja de consulta con sus datos actuales.</li>
                        <li>Escribe producto, cantidad y costo por fila; costo e IVA se sugieren solos si el producto ya existe.</li>
                        <li>Sube el archivo. Si alguna fila tiene error, no se crea nada — corrige y vuelve a subir el mismo archivo.</li>
                    </ol>
                </div>

                <figure class="shot"><div class="shot-crop" style="aspect-ratio: 1919 / 885;"><img src="/images/manual/manual-09.png" alt="Carga masiva de Compras" loading="lazy" /></div><figcaption>Carga masiva de Compras</figcaption></figure>

                <div class="note">
                    <span>&#8505;</span>
                    <span>Si un producto ya existe pero con otro proveedor, la carga masiva lo reasigna al proveedor de esta factura en vez de fallar.</span>
                </div>
            </section>

            <hr class="mod-divider" />

            <section class="mod" id="terceros">
                <div class="mod-head"><span class="tag">Administración</span></div>
                <h2>Clientes y proveedores</h2>
                <p class="lede">Un mismo listado de &ldquo;terceros&rdquo;: personas o empresas con las que compras o a las que vendes.</p>
                <div class="card">
                    <ul class="plain">
                        <li>Documento, nombre/razón social, contacto, ciudad.</li>
                        <li>Para clientes: cupo de crédito, días de plazo y deuda actual.</li>
                        <li>El mismo tercero puede ser cliente y proveedor a la vez.</li>
                    </ul>
                </div>
            </section>

            <hr class="mod-divider" />

            <section class="mod" id="categorias">
                <div class="mod-head"><span class="tag">Administración</span></div>
                <h2>Departamentos y subfamilias</h2>
                <p class="lede">La forma de organizar el catálogo en categorías y subcategorías.</p>
                <div class="card">
                    <ul class="plain">
                        <li><strong>Departamentos</strong> (familias) — la categoría general, ej. &ldquo;Repuestos&rdquo;, &ldquo;Lubricantes&rdquo;.</li>
                        <li><strong>Subfamilias</strong> — subcategoría dentro de un departamento, ej. &ldquo;Pastillas de freno&rdquo; dentro de &ldquo;Repuestos&rdquo;.</li>
                        <li>Todo producto pertenece a un departamento y una subfamilia; se usan para filtrar el catálogo y los reportes.</li>
                    </ul>
                </div>
            </section>

            <hr class="mod-divider" />

            <section class="mod" id="inventario">
                <div class="mod-head"><span class="tag">Administración</span></div>
                <h2>Inventario y kardex</h2>
                <div class="card">
                    <ul class="plain">
                        <li><strong>Ajuste de Inventario</strong> — corrige existencias a mano (sobrante, faltante, dañado), con observación del motivo.</li>
                        <li><strong>Kardex</strong> — historial de movimientos de un producto: cada compra, venta y ajuste que subió o bajó su stock.</li>
                        <li><strong>Inventario General</strong> (reporte) — foto de las existencias actuales con su costo y valor total.</li>
                    </ul>
                </div>
            </section>

            <hr class="mod-divider" />

            <section class="mod" id="combos-recetas">
                <div class="mod-head"><span class="tag">Administración</span></div>
                <h2>Combos, recetas y servicios</h2>
                <div class="grid-2">
                    <div class="mini-card">
                        <h4>Combos</h4>
                        <p>Varios productos vendidos juntos como un solo ítem, con su propio precio.</p>
                    </div>
                    <div class="mini-card">
                        <h4>Recetas</h4>
                        <p>Para negocios de cocina/preparación: define qué insumos (y cuánto de cada uno) se descuentan al vender un producto.</p>
                    </div>
                    <div class="mini-card">
                        <h4>Servicios</h4>
                        <p>Mano de obra facturable, con o sin mecánico asociado — no descuenta inventario.</p>
                    </div>
                </div>
            </section>

            <hr class="mod-divider" />

            <section class="mod" id="notas-credito">
                <div class="mod-head"><span class="tag">Administración</span></div>
                <h2>Notas crédito y devoluciones</h2>
                <div class="card">
                    <p>Cuando un cliente devuelve un producto o hay que anular parte de una factura, se genera una nota crédito sobre esa factura — parcial o total. Las devoluciones quedan también en su propio reporte para revisarlas después.</p>
                </div>
            </section>

            <hr class="mod-divider" />

            <section class="mod" id="caja-admin">
                <div class="mod-head"><span class="tag">Administración</span></div>
                <h2>Movimientos de caja</h2>
                <div class="card">
                    <p>Registro de entradas y salidas de efectivo que no vienen de una venta: gastos, retiros del dueño, consignaciones al banco. Sirve para que el cierre de caja del día cuadre exacto.</p>
                </div>
            </section>

            <hr class="mod-divider" />

            <section class="mod" id="empleados">
                <div class="mod-head"><span class="tag">Administración</span></div>
                <h2>Empleados y permisos</h2>
                <p class="lede">Solo <code class="ui">admin_empresa</code> puede crear empleados y decidir qué ve cada uno.</p>

                <div class="card">
                    <ol class="steps">
                        <li>Crea el empleado con su usuario, contraseña y rol (cajero, vendedor, digitador, taller, etc.).</li>
                        <li>En <strong>&ldquo;Botones visibles en el POS&rdquo;</strong>, marca los que quieres OCULTARLE — por defecto ve todos, esta lista es de exclusión (ej. ocultarle &ldquo;Cartera&rdquo; o &ldquo;Editar cliente&rdquo;).</li>
                        <li>Si el rol es <code class="ui">digitador</code>, en <strong>&ldquo;Recursos visibles en el Admin&rdquo;</strong> eliges a cuáles módulos del panel administrativo tiene acceso (Productos, Compras, Clientes, etc.).</li>
                    </ol>
                </div>

                <figure class="shot"><div class="shot-crop" style="aspect-ratio: 1919 / 863;"><img src="/images/manual/manual-10.png" alt="Crear Empleado - permisos" loading="lazy" /></div><figcaption>Crear Empleado - permisos</figcaption></figure>

                <div class="note">
                    <span>&#8505;</span>
                    <span>Los módulos contables, Reportes, Empleados y Configuración de la Empresa quedan siempre exclusivos de <code class="ui">admin_empresa</code>; no se le pueden dar a un digitador.</span>
                </div>
            </section>

            <hr class="mod-divider" />

            <section class="mod" id="configuracion">
                <div class="mod-head"><span class="tag">Administración</span></div>
                <h2>Configuración de la empresa</h2>
                <p class="lede">El interruptor general de qué módulos tiene activos tu negocio.</p>
                <div class="card">
                    <ul class="plain">
                        <li><strong>Tipo de negocio</strong> — al elegirlo, activa el paquete de módulos típico (tienda, restaurante, taller, hotel).</li>
                        <li>Cada módulo (Mesas, Cocina, Domicilios, Taller, Hotel, Recetas, Servicios, Variantes, Venta por peso) se puede prender o apagar por separado.</li>
                        <li>Datos de la resolución de facturación, y las opciones del <a href="#catalogo">catálogo público</a>.</li>
                        <li>Pestaña de <a href="#facturacion-electronica">Facturación Electrónica</a>, si tu plan la incluye.</li>
                    </ul>
                </div>

                <figure class="shot"><div class="shot-crop" style="aspect-ratio: 1919 / 885;"><img src="/images/manual/manual-11.png" alt="Configuracion de la Empresa" loading="lazy" /></div><figcaption>Configuracion de la Empresa</figcaption></figure>
            </section>

            <hr class="mod-divider" />

            <section class="mod" id="facturacion-electronica">
                <div class="mod-head"><span class="tag">Administración</span></div>
                <h2>Facturación electrónica</h2>
                <p class="lede">Integración con Factus para emitir facturas electrónicas válidas ante la DIAN.</p>
                <div class="card">
                    <ul class="plain">
                        <li>Se activa y configura desde Configuración de la Empresa → pestaña &ldquo;Facturación Electrónica&rdquo;.</li>
                        <li>Necesitas las credenciales que te da Factus y el rango de numeración autorizado por la DIAN.</li>
                        <li>Puedes probar la conexión antes de facturar en modo real (ambiente de pruebas / producción).</li>
                        <li>Cada factura electrónica enviada queda consultable en <a href="#contabilidad">Contabilidad → Facturación Electrónica</a>.</li>
                    </ul>
                </div>
            </section>

            <!-- ============ REPORTES ============ -->
            <section class="mod" id="reportes">
                <div class="mod-head"><span class="tag">Reportes</span></div>
                <h2>Qué reporte mirar según la pregunta</h2>
                <p class="lede">Todos los reportes filtran por fecha y se pueden exportar.</p>

                <figure class="shot"><div class="shot-crop" style="aspect-ratio: 1919 / 885;"><img src="/images/manual/manual-12.png" alt="Reporte de Ventas" loading="lazy" /></div><figcaption>Reporte de Ventas</figcaption></figure>

                <div class="table-wrap">
                    <table class="field-table">
                        <thead><tr><th>Si quieres saber…</th><th>Ve a</th></tr></thead>
                        <tbody>
                            <tr><td>Cuánto vendí y cómo me pagaron</td><td>Ventas</td></tr>
                            <tr><td>Quién vendió más</td><td>Ventas por vendedor</td></tr>
                            <tr><td>Si la caja de un turno cuadró</td><td>Cierres de caja</td></tr>
                            <tr><td>Qué se devolvió y por qué</td><td>Devoluciones</td></tr>
                            <tr><td>Cuánto descuento estoy regalando</td><td>Descuentos</td></tr>
                            <tr><td>Qué servicios se facturaron</td><td>Servicios</td></tr>
                            <tr><td>Cuánto le debo liquidar a cada mecánico</td><td>Mecánicos</td></tr>
                            <tr><td>Cuánto vale el inventario ahora</td><td>Inventario general</td></tr>
                            <tr><td>Quién me debe y cuánto</td><td>Cartera</td></tr>
                            <tr><td>Ocupación de habitaciones</td><td>Reporte de Hotel</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <hr class="mod-divider" />

            <section class="mod" id="contabilidad">
                <div class="mod-head"><span class="tag">Reportes</span></div>
                <h2>Contabilidad</h2>
                <p class="lede">Vistas pensadas para que el contador del negocio consulte o exporte, no para la operación diaria.</p>
                <div class="card">
                    <p>Incluye plan de cuentas, cartera y cuentas por pagar en formato contable, terceros, inventario, salidas de mercancía, ventas y el libro de inventarios y balance — además del detalle de <a href="#facturacion-electronica">facturación electrónica</a> enviada a la DIAN.</p>
                </div>
            </section>

        </main>
    </div>

    <footer>
        <div class="wrap">
            <p>&copy; {{ date('Y') }} Sistema POS — Hecho en Saravena, Arauca, Colombia.</p>
            <p><a href="/">Volver al inicio</a> &middot; <a href="/admin/login">Iniciar sesión</a></p>
        </div>
    </footer>

</body>
</html>
