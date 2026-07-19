<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">

<style>
body {
    font-family: Arial;
    background: #f5f6fa;
    padding: 20px;
}

.card {
    background: white;
    padding: 20px;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

h2 {
    margin: 0;
}

.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.btn {
    padding: 8px 12px;
    border-radius: 5px;
    text-decoration: none;
    color: white;
}

.btn-back {
    background: #6c757d;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th {
    background: #2f3542;
    color: white;
    padding: 8px;
}

td {
    padding: 8px;
    text-align: center;
}

tr:nth-child(even) {
    background: #f1f2f6;
}

/* 🔥 TIPOS DE MOVIMIENTO */
.tipo {
    padding: 5px 10px;
    border-radius: 5px;
    color: white;
    font-size: 12px;
}

.compra { background: #2ed573; }
.venta { background: #ff4757; }
.devolucion { background: #1e90ff; }
.ajuste { background: #ffa502; }

/* 🔥 BLOQUES */
.entrada {
    background: #d4edda;
    color: #155724;
    font-weight: bold;
}

.salida {
    background: #f8d7da;
    color: #721c24;
    font-weight: bold;
}

</style>
</head>

<body>

<div class="card">

    <div class="header">
        <div>
            <h2>{{ $empresa->name }}</h2>
            
        </div>

        <h4 style="text-align:center; margin:0;">
    KARDEX DE PRODUCTOS
</h4>

        <a href="/admin" class="btn btn-back">← Volver</a>
    </div>

    <form method="GET" style="margin-bottom:15px;">
        <input type="text" id="codigo" name="codigo" placeholder="Código" value="{{ request('codigo') }}">

    <input type="date" name="desde" value="{{ request('desde') }}">
    <input type="date" name="hasta" value="{{ request('hasta') }}">

        <button style="background:#ef4444;color:white;padding:5px 10px;border:none;border-radius:5px;">Buscar </button>

        <!-- 🔥 BOTÓN LIMPIAR -->
    <button type="button" onclick="limpiarFiltros()" style="background:#025c19;color:white;padding:5px 10px;border:none;border-radius:5px;">
        Limpiar
    </button>

    <!-- 🔥 BOTÓN BUSCAR PRODUCTO -->
    <button type="button" onclick="abrirModal()" style="background:#2563eb;color:white;padding:5px 10px;border:none;border-radius:5px;">
        Buscar Producto
    </button>
    </form>
    @if(count($movimientos) > 0)
    <h4 style="margin-top:10px;">
        Producto: {{ $movimientos[0]->producto_nombre }}
    </h4>
    @endif

    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Tipo</th>
                <th>Ref</th>
                <th>Entrada</th>
                <th>Salida</th>
                <th>Stock Ant</th>
                <th>Stock Act</th>
                
            </tr>
        </thead>

        <tbody>

        @if(count($movimientos) == 0)

<tr>
    <td colspan="8" style="text-align:center; padding:25px;">
    <div style="color:#9ca3af; font-size:14px;">
        📭 Sin movimientos
        <br>
        <small>Este producto no tiene registros en el rango seleccionado</small>
    </div>
</td>
</tr>

@else

@foreach($movimientos as $m)

        <tr>

            <td>{{ $m->created_at }}</td>

            <td>
                @php
                    $clase = 'ajuste';

                    if(str_contains($m->tipo,'compra')) $clase = 'compra';
                    elseif(str_contains($m->tipo,'venta')) $clase = 'venta';
                    elseif(str_contains($m->tipo,'devolucion')) $clase = 'devolucion';
                @endphp

                <span class="tipo {{ $clase }}">
                  {{ ucwords(str_replace('_', ' ', $m->tipo)) }}
                </span>
            </td>

            <td>
    @php
        $referenciaVisual = $m->referencia;
        if (str_contains($m->tipo, 'venta') && is_numeric($m->referencia)) {
            $referenciaVisual = 'SAL-' . str_pad((string) $m->referencia, 6, '0', STR_PAD_LEFT);
        }
    @endphp
    <a href="javascript:void(0)"
       onclick="verDocumento('{{ $m->tipo }}', '{{ $m->referencia }}')"
       style="color:#2563eb; font-weight:bold;">
        {{ $referenciaVisual }}
    </a>
</td>

            <td class="entrada">{{ $m->entrada }}</td>
            <td class="salida">{{ $m->salida }}</td>

            <td>{{ $m->stock_anterior }}</td>
            <td>{{ $m->stock_actual }}</td>
            

        </tr>

        @endforeach
        @endif
        </tbody>
    </table>

    <div id="modalProductos" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5);">

    <div style="background:white; width:600px; margin:5% auto; padding:20px; border-radius:10px;">

        <h3>Buscar Producto</h3>

        <input type="text" id="buscarProducto" placeholder="Buscar por nombre..." style="width:100%; padding:5px; margin-bottom:10px;">

        <div id="listaProductos" style="max-height:300px; overflow:auto;"></div>

        <button onclick="cerrarModal()">Cerrar</button>

    </div>

</div>
<div id="modalDoc" style="
    position:fixed;
    top:0;
    left:0;
    width:100%;
    height:100%;
    background:rgba(0,0,0,0.5);
    display:none;
">

    <div style="
        background:#fff;
        width:80%;
        max-height:85vh;
        margin:40px auto;
        padding:20px;
        border-radius:10px;
        overflow-y:auto;
    ">

        <div id="contenidoDoc"></div>

       

    </div>

</div>
</div>

<script>
function escapeHtml(texto) {
    return String(texto ?? '').replace(/[&<>"']/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    })[c]);
}

async function verDocumento(tipo, referencia) {

    try {

        let res = await fetch(`/kardex/documento?tipo=${tipo}&ref=${referencia}`);

        // 🔐 Validar sesión
        if (res.status === 401 || res.redirected) {
            window.location.href = '/admin/login';
            return;
        }

        let text = await res.text();

        if (text.startsWith('<')) {
            alert('Sesión expirada o error del servidor');
            window.location.href = '/admin/login';
            return;
        }

        let data = JSON.parse(text);

        // 🔥 SIN DATOS
        if (!data.length) {
            document.getElementById('contenidoDoc').innerHTML =
                `<p style="text-align:center; padding:20px;">⚠️ Sin datos</p>`;

            document.getElementById('modalDoc').style.display = 'block';
            return;
        }

        let total = 0;

        // 🔥 ENCABEZADO
        let html = `
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0;">Detalle del Documento ${tipo.includes('venta') ? 'SAL-' + String(referencia).padStart(6, '0') : referencia}</h3>
            <button onclick="cerrarDoc()" style="
                background:#dc3545;
                color:#fff;
                border:none;
                padding:8px 15px;
                border-radius:8px;
                cursor:pointer;
                font-weight:bold;
            ">
                ✖ Cerrar
            </button>
        </div>
        <hr>

        <div style="overflow-x:auto;">
        <table style="
            width:100%;
            border-collapse:collapse;
            font-size:13px;
            text-align:center;
        ">
            <thead>
                <tr style="background:#2c3e50; color:white;">
                    <th style="padding:8px;">Código</th>
                    <th>Producto</th>
                    <th>Cant</th>
                    <th>Costo</th>
                    <th>Venta</th>
                    <th>IVA</th>
                    <th>Utilidad</th>
                </tr>
            </thead>
            <tbody>
        `;

        // 🔥 RECORRER DATA
        data.forEach(d => {

            let costo = parseFloat(d.costo || 0);
            let precio = parseFloat(d.precio || 0);
            let iva = parseFloat(d.iva || 0);

            let costoFormat = costo.toLocaleString('es-CO', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });

            let precioFormat = precio.toLocaleString('es-CO', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });

            // 🔥 CALCULAR TOTAL SEGÚN TIPO
            if (tipo === 'compra' || tipo === 'devolucion_compra') {

                let costoConIva = costo * (1 + iva / 100);
                total += (d.cantidad * costoConIva);

            } else if (
                tipo !== 'inventario_nuevo' &&
                tipo !== 'ajuste_entrada' &&
                tipo !== 'ajuste_salida'
            ) {

                total += (d.cantidad * precio);
            }

            html += `
                <tr>
                    <td>${escapeHtml(d.producto_id)}</td>
                    <td style="text-align:left;">${escapeHtml(d.nombre)}</td>
                    <td>${escapeHtml(d.cantidad)}</td>

                    <td style="white-space:nowrap;">
                        $ ${costoFormat}
                    </td>

                    <td style="white-space:nowrap;">
                        $ ${precioFormat}
                    </td>

                    <td>${iva}%</td>
                    <td>${d.utilidad}%</td>
                </tr>
            `;
        });

        // 🔥 LABEL DINÁMICO
        let labelTotal = 'TOTAL';

        if (tipo === 'compra') labelTotal = 'TOTAL COMPRA';
        if (tipo === 'venta') labelTotal = 'TOTAL VENTA';
        if (tipo === 'devolucion_compra') labelTotal = 'TOTAL DEVOLUCIÓN';
        if (tipo === 'devolucion_venta') labelTotal = 'TOTAL DEVOLUCIÓN VENTA';

        // 🔥 MOSTRAR TOTAL
        if (
            tipo !== 'inventario_nuevo' &&
            tipo !== 'ajuste_entrada' &&
            tipo !== 'ajuste_salida'
        ) {

            html += `
                <tr style="background:#f1f1f1; font-weight:bold;">
                    <td colspan="4"></td>
                    <td colspan="3" style="text-align:right; padding:10px;">
                        ${labelTotal}: $ ${total.toLocaleString('es-CO', {
                            minimumFractionDigits: 2
                        })}
                    </td>
                </tr>
            `;
        }

        html += `
            </tbody>
        </table>
        </div>
        `;

        document.getElementById('contenidoDoc').innerHTML = html;
        document.getElementById('modalDoc').style.display = 'block';

    } catch (error) {
        console.error('ERROR:', error);
        alert('Error cargando el documento');
    }
}

// 🔥 CERRAR MODAL
function cerrarDoc() {
    document.getElementById('modalDoc').style.display = 'none';
}
</script>
<script>
function limpiarFiltros() {
    window.location.href = '/kardex';
}
</script>

<script>
function abrirModal() {
    document.getElementById('modalProductos').style.display = 'block';
    cargarProductos();
}

function cerrarModal() {
    document.getElementById('modalProductos').style.display = 'none';
}

async function cargarProductos(busqueda = '') {

    let res = await fetch('/productos/buscar?texto=' + busqueda);
    let data = await res.json();

    let html = '';

    data.forEach(p => {
        html += `
            <div style="padding:5px; border-bottom:1px solid #ccc; cursor:pointer;"
                 onclick="seleccionarProducto('${p.id_producto}')">

                <strong>${escapeHtml(p.id_producto)}</strong> - ${escapeHtml(p.descripcion_larga)}

            </div>
        `;
    });

    document.getElementById('listaProductos').innerHTML = html;
}

let timeoutBusqueda;

document.addEventListener('DOMContentLoaded', function () {

    let input = document.getElementById('buscarProducto');

    if (!input) return;

    input.addEventListener('keyup', function() {

        clearTimeout(timeoutBusqueda);

        let texto = this.value;

        // 🔥 evita buscar con 1 letra
        if (texto.length < 2) {
            document.getElementById('listaProductos').innerHTML = '';
            return;
        }

        timeoutBusqueda = setTimeout(() => {
            cargarProductos(texto);
        }, 300); // puedes poner 200 si quieres más rápido
    });

});

function seleccionarProducto(codigo) {
    document.getElementById('codigo').value = codigo;
    cerrarModal();
}
</script>

<script>
document.querySelector('input[name="desde"]').addEventListener('change', function() {

    let desde = new Date(this.value);

    if (!this.value) return;

    let maxFecha = new Date(desde);
    maxFecha.setMonth(maxFecha.getMonth() + 3);

    // formato YYYY-MM-DD
    let max = maxFecha.toISOString().split('T')[0];

    let hastaInput = document.querySelector('input[name="hasta"]');

    hastaInput.max = max;

    // si ya tiene una fecha mayor, la corrige
    if (hastaInput.value && hastaInput.value > max) {
        hastaInput.value = max;
    }

});
</script>
<script>

// 🔥 CERRAR SESIÓN
async function cerrarSesion() {

    try {

        await fetch('/logout', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        });

        // 🔥 limpiar cache
        localStorage.removeItem('inventario_data');

        // 🔥 redirigir
        window.location.href = '/admin';

    } catch (e) {

        console.error(e);

        alert('Error al cerrar sesión');
    }
}

// 🔥 INTERCEPTOR GLOBAL FETCH
const originalFetch = window.fetch;

window.fetch = async (...args) => {

    let response = await originalFetch(...args);

    // 🔥 SESIÓN EXPIRADA
    if (response.status === 401 || response.status === 419) {

        document.getElementById('modalSesion').style.display = 'flex';

        return Promise.reject('Sesión expirada');
    }

    return response;
};

// 🔥 CONTROL INACTIVIDAD
let tiempoInactividad = 30 * 60 * 1000;

let temporizador;

// 🔥 MOSTRAR MODAL
function mostrarModalSesion() {

    document.getElementById('modalSesion').style.display = 'flex';
}

// 🔥 IR LOGIN
function irLogin() {

    localStorage.removeItem('inventario_data');

    window.location.href = '/admin/login';
}

// 🔥 REINICIAR TEMPORIZADOR
function reiniciarTemporizador() {

    clearTimeout(temporizador);

    temporizador = setTimeout(() => {

        mostrarModalSesion();

    }, tiempoInactividad);
}

// 🔥 DETECTAR ACTIVIDAD
[
    'click',
    'mousemove',
    'keydown',
    'scroll'
].forEach(event => {

    document.addEventListener(event, reiniciarTemporizador);
});

// 🔥 INICIAR
reiniciarTemporizador();

</script>
<div id="modalSesion" class="modal-sesion">

    <div class="modal-sesion-box">

        <h2>⚠️ Sesión expirada</h2>

        <p>
            Tu sesión finalizó por inactividad.
        </p>

        <button onclick="irLogin()" class="btn-sesion">
            Iniciar sesión
        </button>

    </div>

</div>

<style>

.modal-sesion {

    display: none;
    position: fixed;
    z-index: 99999;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;

    background: rgba(0,0,0,0.65);

    justify-content: center;
    align-items: center;
}

.modal-sesion-box {

    background: #fff;

    width: 380px;

    padding: 30px;

    border-radius: 14px;

    text-align: center;

    box-shadow: 0 15px 40px rgba(0,0,0,0.3);

    animation: aparecerSesion .25s ease;
}

.modal-sesion-box h2 {

    margin: 0 0 15px;

    color: #dc2626;

    font-size: 26px;
}

.modal-sesion-box p {

    color: #555;

    margin-bottom: 25px;

    line-height: 1.5;
}

.btn-sesion {

    background: #dc2626;

    color: white;

    border: none;

    padding: 12px 25px;

    border-radius: 10px;

    cursor: pointer;

    font-weight: bold;

    font-size: 15px;

    transition: .2s;
}

.btn-sesion:hover {

    background: #b91c1c;

    transform: scale(1.03);
}

@keyframes aparecerSesion {

    from {

        opacity: 0;
        transform: scale(.9);
    }

    to {

        opacity: 1;
        transform: scale(1);
    }
}

</style>
</body>
</html>