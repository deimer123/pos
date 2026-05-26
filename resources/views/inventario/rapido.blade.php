<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Inventario De Productos</title>

<style>

#modal-confirmacion {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 10000;

    background: rgba(0,0,0,0.5);

    display: flex;
    justify-content: center;
    align-items: center;
}

#modal-confirmacion .modal-box {
    position: relative;
    z-index: 10001;
    background: #fff;
    padding: 25px;
    border-radius: 12px;
    width: 400px;
    text-align: center; /* ðŸ”¥ CLAVE */
    box-shadow: 0 10px 25px rgba(0,0,0,0.3);
}




/* ðŸ”¥ FONDO OSCURO */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 10000;
    background: rgba(0,0,0,0.5);

    display: flex;
    justify-content: center;
    align-items: center;
}

/* ðŸ”¥ CAJA */
.modal-box {
    position: relative;
    z-index: 10001;
    background: white;
    padding: 25px;
    border-radius: 12px;
    width: 380px;

    text-align: center;

    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
}

.modal-box {
    animation: aparecer 0.25s ease;
}

@keyframes aparecer {
    from {
        opacity: 0;
        transform: scale(0.9);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

/* ðŸ”¥ TITULO */
.modal-box h3 {
    margin-bottom: 10px;
}

/* ðŸ”¥ TEXTO */
.modal-box p {
    margin-bottom: 20px;
    color: #555;
}

/* ðŸ”¥ BOTONES */
.modal-buttons {
    display: flex;
    justify-content: center;
    gap: 10px;
}

/* ðŸ”¥ BOTON BASE */
.btn {
    border: none;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.2s ease;
    box-shadow: 0 3px 6px rgba(0,0,0,0.15);
}

/* ðŸ”µ BOTÃ“N CARGAR */
.btn-cargar {
    background: #2563eb;
    color: white;
}

table tr:hover {
    background: #f3f4f6;
}

.btn-cargar:hover {
    background: #1d4ed8;
    transform: scale(1.05);
}

/* ðŸ”´ BOTÃ“N CERRAR */
.btn-cerrar {
    background: #dc3545;
    color: white;
}

.btn-cerrar:hover {
    background: #b02a37;
    transform: scale(1.05);
}

/* âœ” ACEPTAR */
.btn-ok {
    background: #28a745;
    color: white;
}

.btn-ok:hover {
    background: #218838;
}

/* âŒ CANCELAR */
.btn-cancel {
    background: #6c757d;
    color: white;
}

.btn-cancel:hover {
    background: #5a6268;
}

/* ðŸ”¥ ANIMACIÃ“N */
@keyframes aparecer {
    from {
        opacity: 0;
        transform: scale(0.9);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}
/* ðŸ”¥ CAJA MODAL */
.modal-box-pro {
    background: #fff;
    padding: 25px 30px;
    border-radius: 12px;
    width: 400px;
    max-width: 90%;
    box-shadow: 0 10px 25px rgba(0,0,0,0.3);
    animation: fadeIn 0.25s ease;
    text-align: center;
}

/* ðŸ”¥ TITULO */
.modal-box-pro h3 {
    margin-bottom: 15px;
    font-size: 20px;
}

/* ðŸ”¥ TEXTO */
.modal-box-pro p {
    margin-bottom: 20px;
    color: #555;
}

/* ðŸ”¥ BOTONES */
.modal-buttons {
    display: flex;
    justify-content: center;
    gap: 10px;
}

/* ðŸ”¥ BOTÃ“N BASE */
.btn-modal {
    border: none;
    padding: 8px 18px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: bold;
}

/* ðŸ”¥ COLORES */
.btn-aceptar {
    background: #28a745;
    color: white;
}

.btn-cancelar {
    background: #6c757d;
    color: white;
}

.btn-peligro {
    background: #dc3545;
    color: white;
}

/* ðŸ”¥ ANIMACIÃ“N */
@keyframes fadeIn {
    from {opacity:0; transform:scale(0.9);}
    to {opacity:1; transform:scale(1);}
}

#tabla td:nth-child(1),
#tabla td:nth-child(2),
#tabla td:nth-child(3),
#tabla td:nth-child(4),
#tabla td:nth-child(5) {
    text-align: center;
}

#tabla td {
    padding: 8px;
}

#tabla tr:nth-child(even) {
    background: #f8f9fa;
}

html,
body {
    height: 100%;
    overflow: hidden;
}

body {
    font-family: 'Segoe UI', sans-serif;
    background: #f4f6f9;
    margin: 0;
    padding: 12px 18px;
    box-sizing: border-box;
}

/* CONTENEDOR GENERAL */
.main {
    background: white;
    padding: 16px 20px;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    height: calc(100vh - 108px);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

/* HEADER */
.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.header button {
    background: #dc3545;
    color: white;
    border: none;
    padding: 8px 15px;
    border-radius: 8px;
    cursor: pointer;
}

/* BOTONES */
.botones {
    display: flex;
    gap: 8px;
    margin-bottom: 15px;
}

.botones button {
    border: none;
    padding: 8px 14px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: bold;
    font-size: 13px;
    transition: 0.2s;
}

.botones button:hover {
    transform: scale(1.05);
}

/* INPUTS */
.inputs {
    display: grid;
    grid-template-columns: auto 120px auto 1fr auto 80px auto 100px;
    align-items: center;
    gap: 10px;
}

.inputs label {
    font-weight: bold;
    font-size: 13px;
}

.inputs input {
    padding: 6px;
    border-radius: 6px;
    border: 1px solid #ccc;
    font-size: 13px;
}

.captura-inventario {
    position: sticky;
    top: 0;
    z-index: 20;
    background: white;
    padding-bottom: 12px;
}

.tabla-inventario-scroll {
    flex: 1 1 auto;
    min-height: 160px;
    overflow-y: auto;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    margin-top: 14px;
}

.tabla-inventario-scroll table {
    margin-top: 0;
}

.tabla-inventario-scroll thead {
    position: sticky;
    top: 0;
    z-index: 10;
}

.observacion-inventario {
    margin-top: 14px;
    flex: 0 0 auto;
}

/* TABLA MODERNA */
table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
    margin-top: 10px;
}

thead {
    background: #2c3e50;
    color: white;
}

th {
    padding: 10px;
}

td {
    padding: 8px;
    border-bottom: 1px solid #ddd;
}

tbody tr:nth-child(even) {
    background: #f2f2f2;
}

tbody tr:hover {
    background: #e6f0ff;
}

tbody tr.highlight {
    background: #fff3cd !important;
    outline: 2px solid #f59e0b;
}

#resultados tr.selected,
#resultados tr:hover {
    background: #dbeafe;
    cursor: pointer;
}

/* ELIMINAR */
.btn-eliminar {
    background: red;
    color: white;
    border: none;
    padding: 4px 8px;
    border-radius: 6px;
    cursor: pointer;
}


.input-cantidad {
    width: 70px;
    text-align: center;
    padding: 4px;
}

/* TEXTAREA */
textarea {
    border-radius: 8px;
    border: 1px solid #ccc;
    padding: 10px;
}

/* MODAL */
#modal {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 10000;
    background: rgba(0,0,0,0.5);
}

#modal-box {
    position: relative;
    z-index: 10001;
    background: white;
    width: 600px;
    margin: 50px auto;
    padding: 20px;
    border-radius: 10px;
}

#tabla-contenedor {
    max-height: 300px;
    overflow-y: auto;
}

/* MENSAJES */
#mensaje {
    border-radius: 8px;
    font-weight: bold;
}

.error {
    background:#f8d7da;
}

.ok {
    background:#d4edda;
}

#nombre {
    width: 500px;   /* ðŸ”¥ mÃ¡s grande */
    font-size: 15px;
    font-weight: bold;
    background: #eef4ff;
}

#stock {
    width: 50px;
    text-align: center;
    background: #f9f9f9;
}

#cantidad {
    width: 50px;
    text-align: center;
    background: #f9f9f9;
}

</style>
<div class="header">
    <div>
        <strong>
            @if(auth()->check())
                {{ auth()->user()->name }}
            @endif
        </strong>
    </div>

    <button onclick="volverAlPanel()">Volver</button>
</div>
    
</head>
<div id="indicador-tipo" style="
    margin:6px 0;
    font-weight:bold;
"></div>
<body>
  <div class="main">  
<div class="captura-inventario">
<div class="botones">
        <button onclick="abrirModalGuardar()" style="background:red; color:white;">Guardar</button>
<button onclick="abrirModalAplicar()" style="background:#2563eb; color:white;">Actualizar</button>
<button onclick="verBorradores()" style="background:#6c757d; color:white;">Borradores</button>
        <button onclick="abrirModal()" style="background:#10b981; color:white;">VER (F2)</button>
        <button onclick="limpiarCache()" style="background:#f59e0b; color:white;">Limpiar</button>
    </div>
<h2>Inventario Productos </h2>

<div id="mensaje"></div>
<div class="barra-superior">

    <div class="inputs">
        <label>C&oacute;digo</label>
        <input type="text" id="codigo">

        <label>Producto</label>
        <input type="text" id="nombre">

        <label>Stock</label>
        <input type="text" id="stock">

        <label>Can Nueva</label>
        <input type="number" id="cantidad">
    </div>

    

    

</div>
</div>

<div class="tabla-inventario-scroll">
<table>
    <thead>
        <tr>
            <th>C&oacute;digo</th>
            <th>Producto</th>
            <th>Stock</th>
            <th>Cantidad</th>
             <th>Eliminar</th>
        </tr>
    </thead>
    <tbody id="tabla"></tbody>
</table>
</div>

<div class="observacion-inventario">
<textarea id="observacion" placeholder="Observaci&oacute;n" required 
style="width:100%; min-height:80px; resize:vertical;"></textarea>
</div>

<!-- ðŸ”¥ MODAL PRO -->
<div id="modal">
    <div id="modal-box">

        <h3>Buscador de productos</h3>

        <input type="text" id="buscar" placeholder="Buscar...">

        <br>

        <!-- ðŸ”¥ CONTENEDOR CON SCROLL -->
        <div id="tabla-contenedor">
            <table border="1" width="100%">
                <tbody id="resultados"></tbody>
            </table>
        </div>

        <br>
        <button class="btn btn-cargar" onclick="cerrarModalBuscador()">Cerrar</button>

    </div>
</div>

<div id="modal-confirm" class="modal-overlay" style="display:none;">
    <div class="modal-box-pro">
        <h3>Limpiar lista</h3>
        <p>Estas seguro que quieres limpiar la lista?</p>

        <div class="modal-buttons">
            <button class="btn-modal btn-aceptar" onclick="confirmarLimpiar()">Aceptar</button>
            <button class="btn-modal btn-cancelar" onclick="cerrarConfirm()">Cancelar</button>
        </div>
    </div>
</div>
<div id="modal-tipo" class="modal-overlay" style="display:none;">
    <div class="modal-box-pro">
        <h3>Seleccionar tipo</h3>

        <div class="modal-buttons" style="flex-direction:column;">
        <button class="btn-modal btn-aceptar" onclick="seleccionarTipo('inventario_nuevo')">Inventario nuevo</button>
       
        <button class="btn-modal btn-modal" style="background:#f59e0b; color:white;" onclick="seleccionarTipo('ajuste')">Ajuste</button>
    </div>
</div> 
 </div>

<div id="modal-confirmacion" class="modal-overlay" style="display:none;">
    
    <div class="modal-box">

        <h3 id="titulo-modal"></h3>

        <p id="mensaje-modal"></p>

        <div class="modal-buttons">
            <button onclick="confirmarAccion()" class="btn btn-ok">
                Aceptar
            </button>

            <button onclick="cerrarModal()" class="btn btn-cancel">
                Cancelar
            </button>
        </div>

    </div>

</div>




<div id="modal-borradores" style="
    display:none;
    position:fixed;
    top:0; left:0;
    width:100%; height:100%;
    background:rgba(0,0,0,0.5);
    z-index:9999;
">
    <div style="
        background:white;
        width:600px;
        margin:80px auto;
        padding:20px;
        max-height:80vh;
        overflow:auto;
    ">

        <h3>Borradores guardados</h3>

        <table border="1" width="100%">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Tipo</th>
                    <th>Observaci&oacute;n</th>
                    <th>Acci&oacute;n</th>
                </tr>
            </thead>
            <tbody id="tabla-borradores"></tbody>
        </table>

        <br>
        <button class="btn btn-cerrar" onclick="cerrarModalBorradores()">Cerrar</button>

    </div>
</div>




<script>
let items = [];
let productoActual = null;
let lista = [];
let listaFiltrada = [];
let index = 0;
let tipoInventario = null;
let borradorId = null;

const buscar = document.getElementById('buscar');
const resultados = document.getElementById('resultados');

const codigo = document.getElementById('codigo');
const nombre = document.getElementById('nombre');
const stock = document.getElementById('stock');
const cantidad = document.getElementById('cantidad');
const observacion = document.getElementById('observacion');
const tabla = document.getElementById('tabla');


const modal = document.getElementById('modal');

function msg(texto, tipo = 'ok') {
    const mensaje = document.getElementById('mensaje');

    if (!mensaje) return;

    mensaje.className = tipo;
    mensaje.innerText = texto;
    mensaje.style.padding = '10px';
    mensaje.style.margin = '10px 0';

    setTimeout(() => {
        mensaje.innerText = '';
        mensaje.className = '';
        mensaje.style.padding = '0';
        mensaje.style.margin = '0';
    }, 3500);
}

function guardarCacheInventario() {
    localStorage.setItem('inventario_data', JSON.stringify({
        items: items,
        tipo: tipoInventario,
        observacion: document.getElementById('observacion').value,
        borradorId: borradorId
    }));
}

observacion.addEventListener('input', guardarCacheInventario);

function renderTabla() {
    tabla.innerHTML = '';

    items.forEach((item, index) => {
        let row = `
        <tr>
            <td>${item.codigo}</td>
            <td>${item.nombre}</td>
            <td>${item.stock}</td>
            <td>
                <input type="number" value="${item.cantidad}"
                    onchange="editarCantidad(${index}, this.value)"
                    class="input-cantidad">
            </td>
            <td>
                <button onclick="eliminarItem(${index})" class="btn-eliminar">x</button>
            </td>
        </tr>
        `;
        tabla.innerHTML += row;
    });

    guardarCacheInventario();
}

function editarCantidad(index, nuevaCantidad) {
    items[index].cantidad = parseFloat(nuevaCantidad) || 0;
    guardarCacheInventario();
}

function eliminarItem(index) {
    items.splice(index, 1);
    renderTabla();
}

function cerrarModalBuscador() {
    document.getElementById('modal').style.display = 'none';
}

async function abrirModal() {
    modal.style.display = 'block';
    buscar.value = '';
    index = 0;
    await cargar();
    buscar.focus();
}

// ðŸ”¥ CARGAR PRODUCTOS
async function cargar() {
   let res = await fetch(`{{ url('/inventario/buscar-lista') }}`);
    lista = await res.json();

    listaFiltrada = [...lista]; // ðŸ”¥ CLON REAL

    pintar(listaFiltrada);
}

function resaltarFila(index) {

    // esperar a que estÃ© renderizada
    setTimeout(() => {
        let filas = document.querySelectorAll('#tabla tr');

        if (filas[index]) {
            filas[index].scrollIntoView({ behavior: 'smooth', block: 'center' });
            filas[index].classList.add('highlight');

            setTimeout(() => {
                filas[index].classList.remove('highlight');
            }, 1500);
        }

    }, 50);
}


// ðŸ”¥ PINTAR
function pintar(data) {
    resultados.innerHTML='';
    data.forEach((p,i)=>{

        let tr = document.createElement('tr');
        if(i===index) tr.classList.add('selected');

        tr.innerHTML = `
            <td>${p.id_producto}</td>
            <td>${p.descripcion_larga}</td>
        `;

        tr.onclick = ()=> seleccionar(p);

        resultados.appendChild(tr);
    });
}

 let timer = null;
    let cargando = false;

    buscar.addEventListener('input', function () {

        let texto = this.value.trim();

        clearTimeout(timer);

        resultados.innerHTML = `<tr><td colspan="2">Buscando...</td></tr>`;

        timer = setTimeout(async () => {

            try {

                let res = await fetch(`/inventario/buscar-lista?q=${encodeURIComponent(texto)}`);
                let data = await res.json();

                console.log('DATA:', data); // ðŸ”¥ DEBUG

                if (!data.length) {
                    resultados.innerHTML = `<tr><td colspan="2">Sin resultados</td></tr>`;
                    return;
                }

                listaFiltrada = data;
                index = 0;

                pintar(listaFiltrada);

            } catch (e) {
                console.error(e);
            }

        }, 300);

    });

    buscar.addEventListener('keydown', e => {

        if (e.key === 'ArrowDown') {
            index = Math.min(index+1, listaFiltrada.length-1);
        }

        if (e.key === 'ArrowUp') {
            index = Math.max(index-1, 0);
        }

        if (e.key === 'Enter') {
            seleccionar(listaFiltrada[index]);
        }

        pintar(listaFiltrada);
    });

document.addEventListener('DOMContentLoaded', function () {

    

   

});

// âœ… SELECCIONAR
function seleccionar(p) {

    cerrarModalBuscador(); // âœ… CORRECTO

    let existente = items.findIndex(item => String(item.codigo) === String(p.id_producto));

    if (existente >= 0) {
        msg(`El producto ${p.id_producto} ya esta en la lista`, 'error');
        resaltarFila(existente);
        codigo.value = '';
        nombre.value = '';
        stock.value = '';
        cantidad.value = '';
        productoActual = null;
        codigo.focus();
        return;
    }

    codigo.value = p.id_producto;

    productoActual = {
        id: p.id_producto,
        nombre: p.descripcion_larga,
        stock: p.existencias
    };

    nombre.value = p.descripcion_larga;
    stock.value = p.existencias;

    cantidad.focus();
}

async function buscarProductoPorCodigo() {
    let valor = codigo.value.trim();

    if (!valor) {
        return;
    }

    try {
        let res = await fetch(`/producto/${encodeURIComponent(valor)}`);

        if (!res.ok) {
            productoActual = null;
            nombre.value = '';
            stock.value = '';
            cantidad.value = '';
            msg('Producto no encontrado', 'error');
            codigo.focus();
            codigo.select();
            return;
        }

        let p = await res.json();

        let existente = items.findIndex(item => String(item.codigo) === String(p.id));

        if (existente >= 0) {
            productoActual = null;
            codigo.value = '';
            nombre.value = '';
            stock.value = '';
            cantidad.value = '';
            msg(`El producto ${p.id} ya esta en la lista`, 'error');
            resaltarFila(existente);
            codigo.focus();
            return;
        }

        productoActual = {
            id: p.id,
            nombre: p.nombre,
            stock: p.stock
        };

        codigo.value = p.id;
        nombre.value = p.nombre;
        stock.value = p.stock;
        cantidad.value = '';
        cantidad.focus();
    } catch (e) {
        console.error(e);
        msg('Error buscando producto', 'error');
    }
}

function agregarProductoActual() {
    if (!productoActual) {
        msg('Primero busca un producto', 'error');
        codigo.focus();
        return;
    }

    let cantidadNueva = parseFloat(cantidad.value);

    if (!cantidadNueva && cantidadNueva !== 0) {
        msg('Ingrese la cantidad nueva', 'error');
        cantidad.focus();
        return;
    }

    items.push({
        codigo: productoActual.id,
        nombre: productoActual.nombre,
        stock: productoActual.stock,
        cantidad: cantidadNueva
    });

    renderTabla();
    resaltarFila(items.length - 1);

    productoActual = null;
    codigo.value = '';
    nombre.value = '';
    stock.value = '';
    cantidad.value = '';
    codigo.focus();
}

codigo.addEventListener('keydown', function (e) {
    if (e.key === 'Tab' || e.key === 'Enter') {
        e.preventDefault();
        buscarProductoPorCodigo();
    }
});

cantidad.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        agregarProductoActual();
    }
});

document.addEventListener('keydown', function (e) {
    if (e.key === 'F2') {
        e.preventDefault();
        abrirModal();
    }
});

window.onload = function () {

    let data = localStorage.getItem('inventario_data');

    if (data) {
        let parsed = JSON.parse(data);

        items = parsed.items || [];
        tipoInventario = parsed.tipo || null;
        document.getElementById('observacion').value = parsed.observacion || '';
        borradorId = parsed.borradorId || null;

        renderTabla();

        if (tipoInventario) {
            // ðŸ”¥ RESTAURAR INDICADOR
            actualizarIndicador(tipoInventario);

            // ðŸ”¥ HABILITAR INPUT
            document.getElementById('codigo').disabled = false;

        } else {
            abrirSeleccionTipo();
            document.getElementById('codigo').disabled = true;
        }

    } else {
        abrirSeleccionTipo();
        document.getElementById('codigo').disabled = true;
    }
}
function actualizarIndicador(tipo) {

    let indicador = document.getElementById('indicador-tipo');

    if (!indicador) return;

    if (tipo === 'inventario_nuevo') {
        indicador.innerHTML = 'INVENTARIO NUEVO';
        indicador.style.color = 'red';
    } else {
        indicador.innerHTML = 'AJUSTE DE INVENTARIO';
        indicador.style.color = 'green';
    }
}

function limpiarCache() {
    document.getElementById('modal-confirm').style.display = 'flex';
}

function cerrarConfirm() {
    document.getElementById('modal-confirm').style.display = 'none';
}

async function confirmarLimpiar() {

    // ðŸ”¥ SI HAY BORRADOR â†’ ELIMINARLO
    if (borradorId) {
        try {
            await fetch(`/inventario/borrador/${borradorId}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            });

            borradorId = null;

        } catch (e) {
            console.error(e);
        }
    }

    items = [];
    tipoInventario = null;

    renderTabla();

    document.getElementById('codigo').disabled = true;
    document.getElementById('indicador-tipo').innerHTML = '';
    document.getElementById('observacion').value = '';
    localStorage.removeItem('inventario_data');

    cerrarConfirm();
    abrirSeleccionTipo();

    msg('Lista limpiada correctamente', 'ok');
}

window.addEventListener('beforeunload', function (e) {
    if (items.length > 0) {
        e.preventDefault();
        e.returnValue = '';
    }
})

function abrirSeleccionTipo() {
    document.getElementById('modal-tipo').style.display = 'flex';
}

function seleccionarTipo(tipo) {

    // ðŸ”¥ VALIDAR SI YA HAY PRODUCTOS
    if (items.length > 0 && tipoInventario && tipoInventario !== tipo) {
        msg('No puedes cambiar el tipo con productos cargados', 'error');
        return;
    }

    // ðŸ”¥ ASIGNAR TIPO
    tipoInventario = tipo;

    // ðŸ”¥ CERRAR MODAL
    document.getElementById('modal-tipo').style.display = 'none';

    // ðŸ”¥ HABILITAR CAMPO CÃ“DIGO
    let inputCodigo = document.getElementById('codigo');
    inputCodigo.disabled = false;
    inputCodigo.focus();

    // ðŸ”¥ ACTUALIZAR INDICADOR VISUAL
    actualizarIndicador(tipo);

    // ðŸ”¥ GUARDAR EN CACHE (IMPORTANTE)
    guardarCacheInventario();

    // ðŸ”¥ MENSAJE
    if (tipo === 'inventario_nuevo') {
        msg('Modo: Inventario nuevo', 'ok');
    } else {
        msg('Modo: Ajuste de inventario', 'ok');
    }
}
window.addEventListener('load', function () {

    let data = localStorage.getItem('inventario_data');

    if (data) {
        let parsed = JSON.parse(data);

        items = parsed.items || [];
        tipoInventario = parsed.tipo || null;
        document.getElementById('observacion').value = parsed.observacion || '';
        borradorId = parsed.borradorId || null;

        renderTabla();

        if (tipoInventario) {
            actualizarIndicador(tipoInventario);
            document.getElementById('codigo').disabled = false;
        } else {
            abrirSeleccionTipo();
            document.getElementById('codigo').disabled = true;
        }

    } else {
        abrirSeleccionTipo();
        document.getElementById('codigo').disabled = true;
    }

})

function abrirGuardar() {
    document.getElementById('modal-guardar').style.display = 'block';
}

function abrirAplicar() {

    let texto = '';

    if (tipoInventario === 'inventario_nuevo') {
        texto = 'Se eliminara TODO el inventario actual y se reemplazara.';
    } else {
        texto = 'Se ajustaran unicamente los productos ingresados.';
    }

    document.getElementById('texto-aplicar').innerText = texto;

    document.getElementById('modal-aplicar').style.display = 'block';
}

function cerrarModal(id) {
    document.getElementById(id).style.display = 'none';
}

let accionActual = null;

function abrirModalGuardar() {

    accionActual = 'guardar';

    document.getElementById('titulo-modal').innerText = 'Guardar borrador';
    document.getElementById('mensaje-modal').innerText = 'Deseas guardar este inventario como borrador?';

    document.getElementById('modal-confirmacion').style.display = 'flex';
}

function abrirModalAplicar() {

    if (!tipoInventario) {
        msg('Debe seleccionar tipo de inventario', 'error');
        return;
    }

    if (items.length === 0) {
        msg('No hay productos para aplicar', 'error');
        return;
    }

    accionActual = 'aplicar';

    let mensaje = '';

    if (tipoInventario === 'inventario_nuevo') {
        mensaje = 'Se eliminara TODO el inventario actual y se reemplazara con las nuevas cantidades ingresadas.';
    } else {
        mensaje = 'Se actualizaran UNICAMENTE las cantidades de los productos ingresados.';
    }

    document.getElementById('titulo-modal').innerText = 'Confirmar actualizacion';
    document.getElementById('mensaje-modal').innerText = mensaje;

    document.getElementById('modal-confirmacion').style.display = 'flex';
}

function cerrarModal() {
   document.getElementById('modal-confirmacion').style.display = 'none';
}


async function confirmarAccion() {

    if (accionActual === 'guardar') {
        await guardarBorrador();
    }

    if (accionActual === 'aplicar') {
        await aplicarInventario();
    }

    cerrarModal();
}

async function guardarBorrador() {

    try {

        if (items.length === 0) {
            msg('No hay productos para guardar', 'error');
            return;
        }

        let obs = document.getElementById('observacion').value.trim();

        if (!obs) {
            msg('La observación es obligatoria', 'error');
            return;
        }

        let res = await fetch('/inventario/guardar', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                id: borradorId || null, // ðŸ”¥ CLAVE
                tipo: tipoInventario,
                items: items,
                observacion: obs
            })
        });

        if (!res.ok) {
            let errorText = await res.text();
            console.error(errorText);
           msg(errorText, 'error');
            return;
        }

        let data = await res.json();

        msg('Borrador guardado correctamente', 'ok');

        limpiarTodoDespuesDeGuardar();

    } catch (e) {
        console.error(e);
        msg('Error al guardar', 'error');
    }
}

function limpiarTodoDespuesDeGuardar() {

    items = [];
    tipoInventario = null;
    borradorId = null;
    productoActual = null;

    codigo.value = '';
    nombre.value = '';
    stock.value = '';
    cantidad.value = '';
    observacion.value = '';

    document.getElementById('indicador-tipo').innerHTML = '';
    codigo.disabled = true;
    tabla.innerHTML = '';
    localStorage.removeItem('inventario_data');

    abrirSeleccionTipo();
}

async function aplicarInventario() {

    let observacionInput = document.getElementById('observacion');
    let observacion = observacionInput?.value || '';

    // ðŸ”´ VALIDACIÃ“N OBLIGATORIA
    if (!observacion || observacion.trim() === '') {
        msg('Debe ingresar una observacion', 'error');
        observacionInput.focus();
        return;
    }

    // ðŸ”´ VALIDAR ITEMS
    if (!items || items.length === 0) {
        msg('No hay productos para aplicar', 'error');
        return;
    }

    // ðŸ”´ VALIDAR TIPO
    if (!tipoInventario) {
        msg('Debe seleccionar tipo de inventario', 'error');
        return;
    }

    try {

        let res = await fetch('/inventario/aplicar', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                tipo: tipoInventario,
                items: items,
                observacion: observacion // âœ… ya validado
            })
        });

        // ðŸ”¥ MANEJO DE ERROR DEL SERVIDOR
        if (!res.ok) {
            let errorText = await res.text();
            console.error(errorText);
            msg(errorText, 'error');
            return;
        }

        let data = await res.json();

        // âœ… Ã‰XITO
        msg('Inventario actualizado correctamente', 'ok');

        limpiarTodoDespuesDeGuardar();

    } catch (e) {
        console.error(e);
        msg('Error al aplicar el inventario', 'error');
    }
}

async function verBorradores() {

    try {

        let res = await fetch('/inventario/borradores');
        if (!res.ok) {
    let errorText = await res.text();
    console.error(errorText);
    throw new Error('Error cargando borradores');
}

let data = await res.json();

        let tbody = document.getElementById('tabla-borradores');
        tbody.innerHTML = '';

        data.forEach(b => {

            let fila = `
            <tr>
                <td>${b.id}</td>
                <td>${formatearTipo(b.tipo)}</td>
                <td>${b.observacion ?? ''}</td>
                <td>
                    <button class="btn btn-cerrar" onclick="cargarBorrador(${b.id})">Cargar</button>
                </td>
            </tr>
            `;

            tbody.innerHTML += fila;
        });

        document.getElementById('modal-borradores').style.display = 'block';

    } catch (e) {
        console.error(e);
        msg('Error al cargar borradores', 'error');
    }
}

function formatearTipo(tipo) {

    if (!tipo) return '';

    return tipo
        .replace('_', ' ')                 // quitar _
        .replace(/\b\w/g, l => l.toUpperCase()); // MayÃºscula inicial
}

function cerrarModalBorradores() {
    document.getElementById('modal-borradores').style.display = 'none';
}

async function cargarBorrador(id) {

    let res = await fetch(`/inventario/borrador/${id}`);
    let data = await res.json();

    borradorId = id;

    items = [];

    tipoInventario = data.tipo;
    actualizarIndicador(tipoInventario);

    document.getElementById('codigo').disabled = false;

    // ðŸ”¥ ESTA LÃNEA ES LA CLAVE
    document.getElementById('observacion').value = data.observacion || '';

    data.detalles.forEach(d => {
        items.push({
            codigo: d.producto_id,
            nombre: d.producto.descripcion_larga,
            stock: d.cantidad_anterior,
            cantidad: d.cantidad_nueva
        });
    });

    renderTabla();
    cerrarModalBorradores();

    msg('Borrador cargado correctamente', 'ok');
}


async function cerrarSesion() {
    try {
        await fetch('/logout', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        });

        // ðŸ”¥ redirigir a Filament
        window.location.href = 'http://127.0.0.1:8000/admin';

    } catch (e) {
        console.error(e);
        alert('Error al cerrar sesiÃ³n');
    }
}

function volverAlPanel() {
    window.location.href = "{{ route('filament.admin.pages.dashboard') }}";
}


// ðŸ”¥ INTERCEPTOR GLOBAL DE FETCH
const originalFetch = window.fetch;

window.fetch = async (...args) => {

    let response = await originalFetch(...args);

    if (response.status === 401 || response.status === 419) {

        alert('SesiÃ³n expirada. Debes iniciar sesiÃ³n nuevamente.');

        window.location.href = '/admin/login';

        return Promise.reject('SesiÃ³n expirada');
    }

    return response;
};


// ðŸ”¥ CONTROL DE INACTIVIDAD
let tiempoInactividad = 30 * 60 * 1000;
let temporizador;

function mostrarModalSesion() {
    document.getElementById('modalSesion').style.display = 'flex';
}

function irLogin() {
    window.location.href = '/admin/login';
}

function reiniciarTemporizador() {

    clearTimeout(temporizador);

    temporizador = setTimeout(() => {

       mostrarModalSesion();

    }, tiempoInactividad);
}

// Detectar actividad del usuario
['click', 'mousemove', 'keydown', 'scroll'].forEach(event => {
    document.addEventListener(event, reiniciarTemporizador);
});

// Iniciar contador
reiniciarTemporizador();

</script>
<div id="modalSesion" class="modal-sesion">
    <div class="modal-contenido">
        <h3>âš ï¸ SesiÃ³n expirada</h3>
        <p>Tu sesiÃ³n ha finalizado por inactividad.</p>
        <button onclick="irLogin()">Iniciar sesiÃ³n</button>
    </div>
</div>

<style>
.modal-sesion {
    display: none;
    position: fixed;
    z-index: 9999;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.6);
    justify-content: center;
    align-items: center;
}

.modal-contenido {
    background: #fff;
    padding: 25px;
    border-radius: 10px;
    text-align: center;
    width: 300px;
    box-shadow: 0px 5px 20px rgba(0,0,0,0.3);
}

.modal-contenido h3 {
    margin-bottom: 10px;
    color: #e74c3c;
}

.modal-contenido button {
    margin-top: 15px;
    padding: 10px 20px;
    border: none;
    background: #e74c3c;
    color: white;
    border-radius: 5px;
    cursor: pointer;
}
</style>
</div>


</body>
</html>

