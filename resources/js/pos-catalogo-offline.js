/**
 * Catalogo de productos del POS guardado localmente (IndexedDB) para que
 * la busqueda no dependa de un viaje al servidor por cada tecla. Se
 * sincroniza en segundo plano contra /pos/catalogo.json.
 */

// Ojo: este numero solo puede SUBIR, nunca bajar. Si el navegador de
// alguien ya abrio esta misma base de datos ("pos_offline") en una version
// mas alta, pedir una version menor rompe la apertura con un VersionError
// que este codigo atrapa en silencio -- se queda pegado en "Sincronizando
// catalogo..." sin ningun error visible en consola. Por eso se deja en 11,
// bien por encima de cualquier version usada antes (distintas ramas de
// desarrollo llegaron a usar hasta la 10), para que siempre pueda abrir
// sin importar el historial de ese navegador.
const DB_NAME = 'pos_offline';
const DB_VERSION = 11;
const STORE_PRODUCTOS = 'productos';

let dbPromise = null;

function abrirDB() {
    if (dbPromise) return dbPromise;

    dbPromise = new Promise((resolve, reject) => {
        const request = indexedDB.open(DB_NAME, DB_VERSION);

        request.onupgradeneeded = () => {
            const db = request.result;

            if (!db.objectStoreNames.contains(STORE_PRODUCTOS)) {
                db.createObjectStore(STORE_PRODUCTOS, { keyPath: 'id_producto' });
            }
        };

        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });

    return dbPromise;
}

const SYNC_AT_KEY = 'pos_catalogo_sincronizado_en';
const EMPRESA_KEY = 'pos_catalogo_empresa_id';
const SYNC_STALE_MS = 30 * 60 * 1000; // 30 min

let catalogoEnMemoria = [];

async function leerTodoDeIndexedDB() {
    const db = await abrirDB();

    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE_PRODUCTOS, 'readonly');
        const store = tx.objectStore(STORE_PRODUCTOS);
        const request = store.getAll();

        request.onsuccess = () => resolve(request.result || []);
        request.onerror = () => reject(request.error);
    });
}

async function reemplazarEnIndexedDB(productos) {
    const db = await abrirDB();

    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE_PRODUCTOS, 'readwrite');
        const store = tx.objectStore(STORE_PRODUCTOS);

        store.clear();
        productos.forEach((producto) => store.put(producto));

        tx.oncomplete = () => resolve();
        tx.onerror = () => reject(tx.error);
    });
}

/**
 * Trae el catalogo del servidor y reemplaza lo guardado localmente.
 * Si falla (sin conexion, etc.) deja lo que ya habia en IndexedDB.
 */
async function sincronizarCatalogo() {
    try {
        const response = await fetch('/pos/catalogo.json', {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });

        if (!response.ok) return false;

        const data = await response.json();
        const productos = data.productos || [];

        await reemplazarEnIndexedDB(productos);
        catalogoEnMemoria = productos;
        localStorage.setItem(SYNC_AT_KEY, String(Date.now()));

        // Marca de que empresa es este catalogo guardado localmente (ver
        // catalogoLocalEsDeEmpresaActual en cargarCatalogoLocal).
        if (window.posEmpresaId !== undefined && window.posEmpresaId !== null) {
            localStorage.setItem(EMPRESA_KEY, String(window.posEmpresaId));
        }

        if (data.descuento_maximo_permitido === null || data.descuento_maximo_permitido === undefined) {
            localStorage.removeItem('pos_descuento_maximo_permitido');
        } else {
            localStorage.setItem('pos_descuento_maximo_permitido', String(data.descuento_maximo_permitido));
        }

        window.dispatchEvent(new CustomEvent('pos-catalogo-sincronizado', {
            detail: { total: productos.length },
        }));

        return true;
    } catch (e) {
        return false;
    }
}

/**
 * Este dispositivo/navegador puede haberse usado antes con OTRA empresa
 * (ej. un tablet compartido, o probando con varias cuentas). El catalogo
 * guardado en IndexedDB no lleva el empresa_id producto por producto, asi
 * que se compara contra una marca aparte guardada la ultima vez que se
 * sincronizo de verdad (ver sincronizarCatalogo). Si no coincide con la
 * empresa de la sesion actual, el catalogo viejo NO se muestra -- se
 * descarta y se espera la sincronizacion real, para no mezclar productos
 * de un negocio distinto.
 */
function catalogoLocalEsDeEmpresaActual() {
    if (window.posEmpresaId === undefined || window.posEmpresaId === null) return true;

    // Sin marca guardada (primera vez con esta version del codigo, o un
    // catalogo que quedo de antes de que existiera esta marca) no se da
    // por buena: es preferible resincronizar una vez de mas que arriesgarse
    // a mostrar productos de otro negocio sin saberlo.
    const empresaGuardada = localStorage.getItem(EMPRESA_KEY);
    if (empresaGuardada === null) return false;

    return empresaGuardada === String(window.posEmpresaId);
}

/**
 * Hidrata el array en memoria desde IndexedDB al cargar la pagina, y
 * sincroniza en segundo plano si no hay datos, estan viejos, o son de
 * otra empresa.
 */
async function cargarCatalogoLocal() {
    if (!catalogoLocalEsDeEmpresaActual()) {
        // Lo que hay en IndexedDB es de otro negocio: se descarta (se
        // veria como productos/precios que no existen aqui) y se espera
        // la sincronizacion real de esta empresa antes de mostrar nada.
        await reemplazarEnIndexedDB([]);
        catalogoEnMemoria = [];
        localStorage.removeItem(SYNC_AT_KEY);
        localStorage.removeItem(EMPRESA_KEY);
        await sincronizarCatalogo();
        return catalogoEnMemoria;
    }

    try {
        catalogoEnMemoria = await leerTodoDeIndexedDB();
    } catch (e) {
        catalogoEnMemoria = [];
    }

    const sincronizadoEn = Number(localStorage.getItem(SYNC_AT_KEY) || 0);
    const desactualizado = (Date.now() - sincronizadoEn) > SYNC_STALE_MS;

    if (catalogoEnMemoria.length === 0 || desactualizado) {
        sincronizarCatalogo();
    }

    return catalogoEnMemoria;
}

function quitarAcentos(texto) {
    return (texto || '')
        .toString()
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase();
}

/**
 * Replica el LIKE '%palabra1%palabra2%' que hacia el servidor: todas las
 * palabras de la busqueda deben aparecer en el nombre (o coincidir con el
 * codigo / un codigo alterno).
 */
function coincideProducto(producto, palabras) {
    if (palabras.length === 0) return true;

    const nombre = quitarAcentos(producto.descripcion_larga);
    const codigo = quitarAcentos(String(producto.id_producto));
    const alternos = (producto.alternate_codes || []).map(quitarAcentos);

    return palabras.every((palabra) => (
        nombre.includes(palabra)
        || codigo.includes(palabra)
        || alternos.some((c) => c.includes(palabra))
    ));
}

function coincideFiltroTipo(producto, filtroTipo) {
    if (!filtroTipo) return true;
    if (filtroTipo === 'combo') return !!producto.tiene_combo;
    if (filtroTipo === 'receta') return !!producto.tiene_receta;
    return producto.tipo_producto === filtroTipo;
}

/**
 * Busqueda local: mismo orden y limite que usaba el servidor (primero con
 * stock, luego por existencias desc, maximo 40).
 */
function buscarLocal(query, filtroTipo) {
    const palabras = quitarAcentos(query).split(/\s+/).filter(Boolean);

    return catalogoEnMemoria
        .filter((p) => coincideFiltroTipo(p, filtroTipo) && coincideProducto(p, palabras))
        .sort((a, b) => {
            const aConStock = a.existencias > 0 ? 1 : 0;
            const bConStock = b.existencias > 0 ? 1 : 0;
            if (aConStock !== bConStock) return bConStock - aConStock;
            return b.existencias - a.existencias;
        })
        .slice(0, 40);
}

/**
 * Soporte de lector de codigo de barras: coincidencia exacta de
 * id_producto o de un codigo alterno.
 */
function buscarCoincidenciaExacta(codigo) {
    const valor = String(codigo).trim();
    if (!valor) return null;

    return catalogoEnMemoria.find((p) => (
        String(p.id_producto) === valor
        || (p.alternate_codes || []).some((c) => String(c) === valor)
    )) || null;
}

function getCatalogo() {
    return catalogoEnMemoria;
}

/**
 * Descuenta visualmente (solo en memoria, no en IndexedDB ni en el
 * servidor) el stock de un producto al agregarlo a cualquier carrito
 * offline, para que el mismo dispositivo no siga ofreciendo stock que el
 * ya vendio tentativamente. El numero real se corrige solo al sincronizar.
 */
function descontarStockVisual(idProducto, cantidad) {
    const producto = catalogoEnMemoria.find((p) => String(p.id_producto) === String(idProducto));
    if (!producto) return;

    if (producto.receta) {
        producto.receta.porciones_disponibles = Math.max(0, Number(producto.receta.porciones_disponibles || 0) - cantidad);
    } else {
        producto.existencias = Math.max(0, Number(producto.existencias || 0) - cantidad);
    }

    window.dispatchEvent(new CustomEvent('pos-catalogo-stock-cambio'));
}

// ---------------------------------------------------------------------
// Render de tarjetas (replica el markup de pos-productos.blade.php)
// ---------------------------------------------------------------------

function escapeHtml(texto) {
    return String(texto ?? '').replace(/[&<>"']/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    })[c]);
}

function formatoMoneda(valor) {
    return '$' + Math.round(Number(valor) || 0).toLocaleString('es-CO');
}

function calcularStock(producto) {
    let cantidad = Number(producto.existencias) || 0;
    let unidad = producto.stock_unidad || 'und';
    let decimales = producto.stock_decimales ?? 0;

    if (producto.receta) {
        cantidad = Number(producto.receta.porciones_disponibles) || 0;
        unidad = producto.receta.unidad_rendimiento || unidad;
        decimales = 2;
    }

    const texto = cantidad.toLocaleString('es-CO', {
        minimumFractionDigits: decimales,
        maximumFractionDigits: decimales,
    }) + ' ' + unidad;

    return { cantidad, unidad, texto, tieneStock: cantidad > 0 };
}

function stockBadgeEstilo(cantidad) {
    if (cantidad <= 0) return 'border-color:#fecaca;background:#fef2f2;color:#b91c1c;';
    if (cantidad <= 5) return 'border-color:#fde68a;background:#fef3c7;color:#92400e;';
    return 'border-color:#a7f3d0;background:#d1fae5;color:#065f46;';
}

function etiquetaServicio(producto) {
    if (producto.tipo_producto !== 'servicio') return '';
    if (producto.mecanico_nombre) {
        return '<div style="font-size:10px; color:#7c3aed; font-weight:700; margin-top:3px;">🔧 ' + escapeHtml(producto.mecanico_nombre) + '</div>';
    }
    if (producto.tercero_nombre) {
        return '<div style="font-size:10px; color:#d97706; font-weight:700; margin-top:3px;">🤝 ' + escapeHtml(producto.tercero_nombre) + '</div>';
    }
    return '';
}

function tarjetaHTML(producto, empresaContexto) {
    const stock = calcularStock(producto);
    const puedeVerStock = empresaContexto?.puede_ver_stock ?? true;
    const bordeColor = stock.tieneStock ? 'border-green-300' : 'border-red-200';
    const nombre = escapeHtml(producto.descripcion_larga);
    const foto = escapeHtml(producto.foto_url);
    const badgeEstilo = stockBadgeEstilo(stock.cantidad);
    const idAttr = escapeHtml(String(producto.id_producto));

    const stockBadgeDesktop = puedeVerStock
        ? '<div class="inline-flex items-center justify-center rounded-full border shadow-sm" style="width:120px; padding:4px 8px; font-size:10px; font-weight:700; text-align:center;' + badgeEstilo + '">Stock: ' + escapeHtml(stock.texto) + '</div>'
        : '';

    const stockBadgeMobile = puedeVerStock
        ? '<div class="inline-flex items-center justify-center rounded-full border shadow-sm" style="width:86px; padding:3px 6px; font-size:8px; font-weight:700; text-align:center;' + badgeEstilo + '">Stock: ' + escapeHtml(stock.texto) + '</div>'
        : '';

    return `
    <div>
        <div class="pos-product-card-desktop bg-white rounded-lg shadow border ${bordeColor}" style="height: 110px; display: flex; align-items: stretch;">
            <div style="display:flex; flex-direction:column; align-items:center; justify-content:center; gap:6px; padding:10px 8px 10px 14px; flex-shrink:0;">
                <div class="inline-flex items-center justify-center rounded-full border px-2 text-[11px] font-bold leading-none shadow-sm" style="border-color:#312e81;background:#4338ca;color:#fefefe; min-width:54px; height:22px;">${idAttr}</div>
                <img data-ver-imagen="${foto}" src="${foto}" style="width:56px; height:56px; object-fit:cover; border-radius:4px; border:1px solid #e2e8f0; cursor:pointer;" alt="Foto del producto" />
            </div>
            <div style="flex:1; min-width:0; display:flex; align-items:center; padding:10px 12px 10px 8px;">
                <div>
                    <div title="${nombre}" style="font-size:11px; font-weight:600; line-height:1.3; color:#334155; word-break:break-word; display:-webkit-box; -webkit-line-clamp:3; -webkit-box-orient:vertical; overflow:hidden;">${nombre}</div>
                    ${etiquetaServicio(producto)}
                </div>
            </div>
            <div style="display:flex; flex-direction:row; align-items:flex-end; gap:8px; padding:0 14px 10px 0; flex-shrink:0;">
                <div style="display:flex; flex-direction:column; align-items:flex-end; gap:6px;">
                    <div class="inline-flex items-center justify-center gap-1 rounded-full border border-indigo-200 bg-indigo-50 shadow-sm" style="width:120px; padding:5px 8px; font-size:12px; font-weight:800; color:#4338ca; white-space:nowrap;">
                        <span>${formatoMoneda(producto.precio_venta1)}</span>
                        <span style="font-size:9px; font-weight:600; color:#6366f1;">${escapeHtml(producto.sufijo_venta)}</span>
                    </div>
                    ${stockBadgeDesktop}
                </div>
                <button type="button" data-agregar="${idAttr}" style="width:88px; flex-shrink:0; background:#4f46e5; color:white; border:none; border-radius:9999px; padding:8px 6px; font-size:12px; font-weight:600; cursor:pointer; box-shadow:0 1px 3px rgba(0,0,0,.2);">Agregar</button>
            </div>
        </div>

        <div class="pos-product-card-mobile bg-white rounded-lg shadow border ${bordeColor}" style="height: 96px; display:flex; align-items:stretch;">
            <div style="display:flex; flex-direction:column; align-items:center; justify-content:center; gap:5px; padding:8px 6px 8px 10px; flex-shrink:0;">
                <div class="inline-flex items-center justify-center rounded-full border px-2 text-[10px] font-bold leading-none shadow-sm" style="border-color:#312e81;background:#4338ca;color:#fefefe; min-width:46px; height:20px;">${idAttr}</div>
                <img data-ver-imagen="${foto}" src="${foto}" style="width:46px; height:46px; object-fit:cover; border-radius:4px; border:1px solid #e2e8f0;" alt="Foto del producto" />
            </div>
            <div style="flex:1; min-width:0; display:flex; align-items:center; padding:8px 8px 8px 6px;">
                <div style="width:100%;">
                    <button type="button" data-ver-nombre-mobile="${nombre}" data-stock-mobile="${puedeVerStock ? escapeHtml(stock.texto) : ''}" title="${nombre}" style="width:100%; text-align:left; font-size:9px; font-weight:600; line-height:1.2; color:#334155; word-break:break-word; display:-webkit-box; -webkit-line-clamp:3; -webkit-box-orient:vertical; overflow:hidden; background:none; border:none; padding:0; cursor:pointer;">${nombre}</button>
                    ${etiquetaServicio(producto)}
                </div>
            </div>
            <div style="display:flex; flex-direction:row; align-items:flex-end; gap:6px; padding:0 10px 8px 0; flex-shrink:0;">
                <div style="display:flex; flex-direction:column; align-items:flex-end; gap:5px;">
                    <div class="inline-flex items-center justify-center gap-1 rounded-full border border-indigo-200 bg-indigo-50 shadow-sm" style="width:86px; padding:4px 6px; font-size:10px; font-weight:800; color:#4338ca; white-space:nowrap;">
                        <span>${formatoMoneda(producto.precio_venta1)}</span>
                        <span style="font-size:7px; font-weight:600; color:#6366f1;">${escapeHtml(producto.sufijo_venta)}</span>
                    </div>
                    ${stockBadgeMobile}
                </div>
                <button type="button" data-agregar="${idAttr}" style="width:68px; flex-shrink:0; background:#4f46e5; color:white; border:none; border-radius:9999px; padding:6px 4px; font-size:10px; font-weight:600; cursor:pointer; box-shadow:0 1px 3px rgba(0,0,0,.2);">Agregar</button>
            </div>
        </div>
    </div>`;
}

function renderizarProductos(lista, empresaContexto, contenedorEl) {
    if (!contenedorEl) return;

    if (lista.length === 0) {
        contenedorEl.innerHTML = '<div class="p-4 space-y-4 min-h-[700px]">No se encontraron productos.</div>';
        return;
    }

    contenedorEl.innerHTML = lista.map((p) => tarjetaHTML(p, empresaContexto)).join('');
}

/**
 * Delegacion de eventos sobre el contenedor de la grilla: click en
 * "Agregar" llama al callback (que a su vez llama al metodo Livewire),
 * click en la foto o en el nombre (mobile) dispara los mismos eventos
 * que ya escuchan los modales Alpine existentes en el blade.
 */
function inicializarEventosGrid(contenedorEl, onAgregar) {
    if (!contenedorEl) return;

    contenedorEl.addEventListener('click', (event) => {
        const botonAgregar = event.target.closest('[data-agregar]');
        if (botonAgregar) {
            onAgregar(botonAgregar.getAttribute('data-agregar'));
            return;
        }

        const imagen = event.target.closest('[data-ver-imagen]');
        if (imagen) {
            window.dispatchEvent(new CustomEvent('ver-imagen', {
                detail: { url: imagen.getAttribute('data-ver-imagen') },
            }));
            return;
        }

        const nombreMobile = event.target.closest('[data-ver-nombre-mobile]');
        if (nombreMobile) {
            window.dispatchEvent(new CustomEvent('ver-nombre-producto-mobile', {
                detail: {
                    nombre: nombreMobile.getAttribute('data-ver-nombre-mobile'),
                    stock: nombreMobile.getAttribute('data-stock-mobile') || null,
                },
            }));
        }
    });
}

function descuentoMaximoPermitidoLocal() {
    const valor = localStorage.getItem('pos_descuento_maximo_permitido');
    return valor === null ? null : Number(valor);
}

window.PosCatalogoOffline = {
    cargarCatalogoLocal,
    sincronizarCatalogo,
    buscarLocal,
    buscarCoincidenciaExacta,
    getCatalogo,
    renderizarProductos,
    inicializarEventosGrid,
    descuentoMaximoPermitidoLocal,
    descontarStockVisual,
};
