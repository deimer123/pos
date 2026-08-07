use std::fs;
use std::net::{TcpListener, TcpStream, ToSocketAddrs};
use std::path::PathBuf;
use std::process::{Child, Command, Stdio};
use std::sync::Mutex;
use std::time::{Duration, Instant};

use sha2::{Digest, Sha256};
use tauri::{Manager, WebviewUrl, WebviewWindowBuilder};

#[cfg(windows)]
use std::os::windows::process::CommandExt;

// Evita que se abra una ventana de consola visible al lanzar php.exe en Windows.
#[cfg(windows)]
const CREATE_NO_WINDOW: u32 = 0x08000000;

// Tauri/WRY corre la app dentro de un Job Object de Windows; sin esto, los
// hijos que genera (php.exe) heredan restricciones de ese job (visto en la
// practica como STATUS_ACCESS_VIOLATION al arrancar php.exe SOLO cuando se
// lanza desde dentro de la app empaquetada, nunca al probarlo suelto) y se
// caen. CREATE_BREAKAWAY_FROM_JOB saca al hijo de ese job.
#[cfg(windows)]
const CREATE_BREAKAWAY_FROM_JOB: u32 = 0x0100_0000;

/// Handle al proceso del servidor PHP local, guardado como estado de la app
/// para poder matarlo cuando la app se cierra.
///
/// A diferencia de src-tauri/src/lib.rs (edicion hibrida/Turion), esta
/// variante NO tiene SincronizacionEnCurso: la edicion Local nunca corre
/// "pos:push"/"pos:sync-catalog" en segundo plano (no sincroniza nunca),
/// asi que no hace falta rastrear un PID de sincronizacion para matarlo
/// antes de actualizar.
struct PhpServerHandle(Mutex<Option<Child>>);

#[derive(Clone)]
struct LocalEnv {
    php_exe: PathBuf,
    php_ini: PathBuf,
    cacert_path: PathBuf,
    laravel_dir: PathBuf,
    router_script: PathBuf,
    vars: Vec<(String, String)>,
    is_first_run: bool,
}

fn find_free_port() -> u16 {
    let listener = TcpListener::bind("127.0.0.1:0").expect("no se pudo reservar un puerto local");
    listener.local_addr().expect("puerto local invalido").port()
}

/// True si nada esta escuchando en ese puerto en 0.0.0.0 todavia (mismo
/// bind que usa lanzar_servidor_php). Se usa para decidir si el puerto
/// guardado en modo.json de un arranque anterior se puede reutilizar.
fn puerto_libre(port: u16) -> bool {
    TcpListener::bind(("0.0.0.0", port)).is_ok()
}

/// Puerto para este arranque del servidor: reutiliza el guardado en
/// modo.json de la vez anterior si sigue libre (asi los terminales ya
/// emparejados no pierden la conexion con solo reiniciar el servidor), o
/// elige uno nuevo y lo persiste para el proximo arranque.
fn resolver_puerto_servidor(app: &tauri::AppHandle) -> u16 {
    if let Some(ModoInstalacion::Servidor { puerto: Some(guardado) }) = leer_modo(app) {
        if puerto_libre(guardado) {
            return guardado;
        }
    }

    let nuevo = find_free_port();
    let _ = escribir_modo(app, &ModoInstalacion::Servidor { puerto: Some(nuevo) });
    nuevo
}

/// IPv4 de este equipo en la red local (para que otros equipos -- ver
/// "varios terminales contra un servidor local" -- puedan encontrar este
/// servidor). Sigue el mismo estilo que machine_guid_crudo(): correr un
/// comando de Windows y parsear texto, en vez de sumar una dependencia de
/// Rust nueva solo para esto. Busca "IPv4" en el texto (cubre tanto
/// "IPv4 Address" en Windows en ingles como "Direccion IPv4" en espanol) y
/// descarta loopback/APIPA. None si no hay ninguna interfaz de red real
/// (ej. sin cable/wifi conectado) -- en ese caso el equipo sigue
/// funcionando como POS local normal, solo no es alcanzable desde otros
/// equipos todavia.
fn direccion_lan() -> Option<String> {
    let mut cmd = Command::new("ipconfig");

    #[cfg(windows)]
    cmd.creation_flags(CREATE_NO_WINDOW);

    let output = cmd.output().ok()?;

    if !output.status.success() {
        return None;
    }

    let texto = String::from_utf8_lossy(&output.stdout).to_string();

    parsear_ipv4_lan(&texto)
}

/// Parte pura (sin invocar ningun comando) de direccion_lan(), separada
/// para poder probarla con texto de ejemplo en vez de depender de que el
/// entorno de pruebas tenga una red real.
fn parsear_ipv4_lan(texto: &str) -> Option<String> {
    texto
        .lines()
        .filter(|linea| linea.contains("IPv4"))
        .filter_map(|linea| linea.split(':').nth(1))
        .map(|valor| valor.trim().to_string())
        .find(|ip| !ip.is_empty() && !ip.starts_with("127.") && !ip.starts_with("169.254."))
}

fn wait_for_port(port: u16, timeout: Duration) -> bool {
    let deadline = Instant::now() + timeout;
    while Instant::now() < deadline {
        if TcpStream::connect(("127.0.0.1", port)).is_ok() {
            return true;
        }
        std::thread::sleep(Duration::from_millis(150));
    }
    false
}

fn nueva_llave_app(php_exe: &PathBuf) -> String {
    let mut cmd = Command::new(php_exe);
    cmd.arg("-r")
        .arg("echo 'base64:' . base64_encode(random_bytes(32));");

    #[cfg(windows)]
    cmd.creation_flags(CREATE_BREAKAWAY_FROM_JOB);

    let output = cmd
        .output()
        .expect("no se pudo generar la llave de la aplicacion (APP_KEY)");

    String::from_utf8_lossy(&output.stdout).trim().to_string()
}

/// Lee el MachineGuid del registro de Windows (estable por instalacion de
/// Windows -- sobrevive a reinstalar la app, solo cambia si se reinstala
/// el SO). Es la base del fingerprint de maquina que exige esta edicion
/// para activarse (ver machine_id() mas abajo y App\Services\LocalLicense
/// del lado PHP). None si por algun motivo no se pudo leer (permisos,
/// versiones raras de Windows, etc.) -- machine_id() cae a un valor fijo
/// en ese caso en vez de fallar el arranque de la app.
fn machine_guid_crudo() -> Option<String> {
    let mut cmd = Command::new("reg");
    cmd.args([
        "query",
        r"HKLM\SOFTWARE\Microsoft\Cryptography",
        "/v",
        "MachineGuid",
    ]);

    #[cfg(windows)]
    cmd.creation_flags(CREATE_NO_WINDOW);

    let output = cmd.output().ok()?;

    if !output.status.success() {
        return None;
    }

    let texto = String::from_utf8_lossy(&output.stdout).to_string();

    texto
        .lines()
        .find(|linea| linea.contains("MachineGuid"))
        .and_then(|linea| linea.split_whitespace().last())
        .map(|s| s.to_string())
}

/// Fingerprint corto y legible de esta maquina -- el cliente lo dicta/
/// copia al pedir su codigo de activacion (ver App\Filament\Pages\
/// EmitirLicenciaLocal.php, y App\Services\LocalLicense\ValidadorLicencia
/// que lo compara tal cual, sin reinterpretar el hash, asi que el formato
/// tiene que ser estable entre corridas). No se guarda a disco: se
/// recalcula en cada arranque para que el valor siempre refleje la
/// maquina real, incluso si alguien copia app_data_dir a otro equipo por
/// error.
fn machine_id() -> String {
    let crudo = machine_guid_crudo().unwrap_or_else(|| "SIN-MACHINEGUID".to_string());

    let mut hasher = Sha256::new();
    hasher.update(crudo.as_bytes());
    let hash = hasher.finalize();

    let hex: String = hash.iter().map(|b| format!("{:02X}", b)).collect();
    let corto = &hex[..16];

    format!(
        "MID-{}-{}-{}-{}",
        &corto[0..4],
        &corto[4..8],
        &corto[8..12],
        &corto[12..16]
    )
}

/// Rol elegido por este equipo la primera vez que se abre la app (ver
/// splash/modo.html) -- 'servidor' arranca PHP+SQLite igual que siempre;
/// 'cliente' es un terminal sin base propia, solo abre un webview contra
/// el servidor de la red local (ver App\Http\Controllers\
/// EmparejarTerminalLocalController del lado PHP). Se guarda en
/// modo.json, en app_data_dir, y se relee en cada arranque.
///
/// 'servidor' guarda ademas el puerto en el que quedo escuchando php -S la
/// ultima vez (ver resolver_puerto_servidor) -- antes se elegia un puerto
/// libre al azar en CADA arranque y no se guardaba, asi que con solo
/// reiniciar el equipo servidor (IP fija o no) la direccion guardada en
/// todos los terminales quedaba invalida, ademas de por el cambio de IP
/// que ya cubre servidor_alcanzable. None cuando todavia no se sabe (recien
/// elegido "Servidor" en modo.html, o instalaciones viejas migradas en
/// leer_modo) -- resolver_puerto_servidor elige uno y lo persiste.
#[derive(serde::Serialize, serde::Deserialize)]
#[serde(tag = "rol", rename_all = "lowercase")]
enum ModoInstalacion {
    Servidor {
        #[serde(default, skip_serializing_if = "Option::is_none")]
        puerto: Option<u16>,
    },
    Cliente { servidor_url: String },
}

fn ruta_modo(app: &tauri::AppHandle) -> PathBuf {
    dunce::simplified(
        &app.path()
            .app_data_dir()
            .expect("no se pudo resolver el directorio de datos local del usuario"),
    )
    .join("modo.json")
}

fn leer_modo(app: &tauri::AppHandle) -> Option<ModoInstalacion> {
    if let Ok(contenido) = fs::read_to_string(ruta_modo(app)) {
        if let Ok(modo) = serde_json::from_str(&contenido) {
            return Some(modo);
        }
    }

    // Instalaciones de antes de que existiera "varios terminales" (antes
    // de que modo.json existiera) no tienen este archivo -- pero YA
    // tienen su base de datos armada. Si se le mostrara la pantalla de
    // "Servidor o Terminal" a un servidor de toda la vida que se esta
    // actualizando, quedaria pidiendo elegir de nuevo (y de paso, un
    // "Servidor" mal elegido ahi terminaba arrancando una segunda vez
    // sobre datos ya en uso, con el navegador cacheando la sesion vieja
    // -- eso fue el "419 Page Expired" que se vio al probarlo). Si la
    // base de datos ya existe, esto es sin dudas un servidor existente:
    // se asume Servidor una sola vez y se deja guardado.
    let data_dir = dunce::simplified(&app.path().app_data_dir().ok()?).to_path_buf();

    if data_dir.join("database.sqlite").exists() {
        let modo = ModoInstalacion::Servidor { puerto: None };
        let _ = escribir_modo(app, &modo);
        return Some(modo);
    }

    None
}

fn escribir_modo(app: &tauri::AppHandle, modo: &ModoInstalacion) -> Result<(), String> {
    let ruta = ruta_modo(app);

    if let Some(padre) = ruta.parent() {
        fs::create_dir_all(padre).map_err(|e| format!("no se pudo crear el directorio de datos: {e}"))?;
    }

    let json = serde_json::to_string(modo).map_err(|e| format!("no se pudo guardar el modo de instalacion: {e}"))?;

    fs::write(&ruta, json).map_err(|e| format!("no se pudo guardar el modo de instalacion: {e}"))
}

/// Deja constancia de que version del bundle corrio por ultima vez en esta
/// instalacion (app_data_dir, no el directorio de recursos reemplazable).
/// Confirma ademas que la base de datos/storage locales sobreviven a una
/// reinstalacion, ya que viven fuera del directorio que el instalador
/// reemplaza.
fn registrar_version_bundle(app: &tauri::AppHandle, data_dir: &std::path::Path) {
    let version_actual = app.package_info().version.to_string();
    let version_path = data_dir.join("version.txt");

    if let Ok(anterior) = fs::read_to_string(&version_path) {
        if anterior.trim() != version_actual {
            log::info!("Sistema POS Local actualizado: {} -> {}", anterior.trim(), version_actual);
        }
    }

    let _ = fs::write(&version_path, &version_actual);
}

fn preparar_entorno_local(app: &tauri::AppHandle, port: u16) -> LocalEnv {
    let resource_dir = dunce::simplified(
        &app.path()
            .resource_dir()
            .expect("no se pudo resolver el directorio de recursos empacados"),
    )
    .to_path_buf();
    let data_dir = dunce::simplified(
        &app.path()
            .app_data_dir()
            .expect("no se pudo resolver el directorio de datos local del usuario"),
    )
    .to_path_buf();

    fs::create_dir_all(&data_dir).expect("no se pudo crear el directorio de datos local");
    registrar_version_bundle(app, &data_dir);

    let php_dir = resource_dir.join("php");
    let php_exe = php_dir.join("php.exe");
    let php_ini = php_dir.join("php.ini");
    let cacert_path = php_dir.join("cacert.pem");

    let laravel_dir = resource_dir.join("laravel");
    let router_script = laravel_dir
        .join("vendor")
        .join("laravel")
        .join("framework")
        .join("src")
        .join("Illuminate")
        .join("Foundation")
        .join("resources")
        .join("server.php");

    // El directorio instalado (resource_dir) se trata como de solo lectura y
    // reemplazable en cada actualizacion -- toda la parte que cambia entre
    // corridas (base de datos, storage de Laravel, la llave de la app) vive
    // en app_data_dir, que persiste entre actualizaciones del bundle.
    let storage_dir = data_dir.join("storage");
    for sub in [
        "app/public",
        "framework/cache/data",
        "framework/sessions",
        "framework/testing",
        "framework/views",
        "logs",
    ] {
        fs::create_dir_all(storage_dir.join(sub)).expect("no se pudo preparar storage/ local");
    }

    let db_path = data_dir.join("database.sqlite");
    let is_first_run = !db_path.exists();
    if is_first_run {
        fs::File::create(&db_path).expect("no se pudo crear la base de datos local (SQLite)");
    }

    let key_path = data_dir.join("app_key.txt");
    let app_key = match fs::read_to_string(&key_path) {
        Ok(contenido) if !contenido.trim().is_empty() => contenido.trim().to_string(),
        _ => {
            let key = nueva_llave_app(&php_exe);
            fs::write(&key_path, &key).expect("no se pudo guardar la llave de la aplicacion");
            key
        }
    };

    let vars = vec![
        ("APP_NAME".into(), "Sistema POS Local".into()),
        // Mismo nombre de env var que la edicion hibrida (historico, ver
        // src-tauri/src/lib.rs) -- config/pos.php lo lee igual en ambas.
        ("TURION_VERSION".into(), app.package_info().version.to_string()),
        ("APP_ENV".into(), "production".into()),
        ("APP_DEBUG".into(), "false".into()),
        ("APP_KEY".into(), app_key),
        ("LARAVEL_STORAGE_PATH".into(), storage_dir.to_string_lossy().into_owned()),
        ("DB_CONNECTION".into(), "sqlite".into()),
        ("DB_DATABASE".into(), db_path.to_string_lossy().into_owned()),
        ("SESSION_DRIVER".into(), "database".into()),
        ("CACHE_DRIVER".into(), "file".into()),
        ("QUEUE_CONNECTION".into(), "sync".into()),
        ("FILESYSTEM_DISK".into(), "local".into()),
        ("LOG_CHANNEL".into(), "single".into()),
        ("LOG_LEVEL".into(), "error".into()),
        // Edicion Local: nunca sincroniza, /admin completo disponible,
        // Facturar nunca bloqueado por conectividad -- ver
        // App\Support\PosEdition del lado PHP.
        ("POS_EDITION".into(), "local".into()),
        ("POS_MACHINE_ID".into(), machine_id()),
        // Direccion:puerto donde otros equipos de la red local pueden
        // encontrar este servidor (ver "Conectar Terminales" del lado
        // PHP) -- vacio si no se pudo detectar una IP de LAN real.
        (
            "POS_LAN_ADDRESS".into(),
            direccion_lan()
                .map(|ip| format!("{ip}:{port}"))
                .unwrap_or_default(),
        ),
    ];

    LocalEnv {
        php_exe,
        php_ini,
        cacert_path,
        laravel_dir,
        router_script,
        vars,
        is_first_run,
    }
}

fn correr_artisan(env: &LocalEnv, args: &[&str], app_url: &str) {
    let mut cmd = Command::new(&env.php_exe);
    cmd.current_dir(&env.laravel_dir)
        .arg("-c")
        .arg(&env.php_ini)
        .arg("-d")
        .arg(format!("curl.cainfo={}", env.cacert_path.display()))
        .arg("-d")
        .arg(format!("openssl.cafile={}", env.cacert_path.display()))
        .arg("artisan")
        .args(args)
        .envs(env.vars.iter().map(|(k, v)| (k.as_str(), v.as_str())))
        .env("APP_URL", app_url)
        .stdout(Stdio::piped())
        .stderr(Stdio::piped());

    #[cfg(windows)]
    cmd.creation_flags(CREATE_BREAKAWAY_FROM_JOB);

    let salida = cmd
        .output()
        .expect("no se pudo ejecutar artisan localmente");

    if !salida.status.success() {
        let salida_std = String::from_utf8_lossy(&salida.stdout);
        let error = String::from_utf8_lossy(&salida.stderr);
        panic!(
            "artisan {:?} fallo en el arranque local (status={:?}, code={:?}): stdout=[{salida_std}] stderr=[{error}]",
            args, salida.status, salida.status.code()
        );
    }
}

fn lanzar_servidor_php(env: &LocalEnv, port: u16, app_url: &str) -> Child {
    let mut cmd = Command::new(&env.php_exe);
    cmd.current_dir(env.laravel_dir.join("public"))
        .arg("-c")
        .arg(&env.php_ini)
        .arg("-d")
        .arg(format!("curl.cainfo={}", env.cacert_path.display()))
        .arg("-d")
        .arg(format!("openssl.cafile={}", env.cacert_path.display()))
        .arg("-S")
        // 0.0.0.0 (no 127.0.0.1): para que otros equipos de la red local
        // puedan llegar a este servidor -- ver direccion_lan(). La propia
        // ventana de este equipo sigue navegando por 127.0.0.1 (mas abajo
        // en run()), eso no cambia.
        .arg(format!("0.0.0.0:{port}"))
        .arg(&env.router_script)
        .envs(env.vars.iter().map(|(k, v)| (k.as_str(), v.as_str())))
        .env("APP_URL", app_url)
        .stdout(Stdio::null())
        .stderr(Stdio::null());

    #[cfg(windows)]
    cmd.creation_flags(CREATE_NO_WINDOW | CREATE_BREAKAWAY_FROM_JOB);

    cmd.spawn().expect("no se pudo iniciar el servidor local del POS (php -S)")
}

/// Apaga el servidor PHP local (el "hijo" que arranca esta app para que
/// funcione el POS) justo antes de instalar una actualizacion o restaurar
/// un respaldo. Sin esto, cuando el instalador de la version nueva (o
/// preparar_restauracion) cierra la ventana para reemplazar archivos, mata
/// "app.exe" pero NO a este proceso hijo (que solo se cierra solo cuando
/// la ventana se cierra de forma normal, via el evento ExitRequested mas
/// abajo) -- queda huerfano bloqueando sus propios .dll y el instalador/la
/// restauracion falla con "Error opening file for writing".
#[tauri::command]
fn detener_servidor_local(servidor: tauri::State<PhpServerHandle>) {
    if let Some(mut child) = servidor.0.lock().unwrap().take() {
        let _ = child.kill();
        log::info!("Servidor PHP local detenido.");
    }
}

/// Copia recursiva simple (sin dependencias nuevas): crea los directorios
/// que hagan falta y sobreescribe archivos existentes. Usada por
/// preparar_restauracion() para reemplazar storage/app/public completo.
fn copiar_directorio_recursivo(origen: &std::path::Path, destino: &std::path::Path) -> std::io::Result<()> {
    fs::create_dir_all(destino)?;

    for entrada in fs::read_dir(origen)? {
        let entrada = entrada?;
        let ruta_origen = entrada.path();
        let ruta_destino = destino.join(entrada.file_name());

        if entrada.file_type()?.is_dir() {
            copiar_directorio_recursivo(&ruta_origen, &ruta_destino)?;
        } else {
            fs::copy(&ruta_origen, &ruta_destino)?;
        }
    }

    Ok(())
}

/// Completa una restauracion de respaldo que el lado PHP ya dejo lista
/// (ver App\Filament\Pages\RespaldoLocal::prepararRestauracion(), que
/// valida el ZIP subido y extrae su contenido en storage/app/pending-
/// restore/ + un marcador storage/app/pending-restore.json). Apaga el
/// servidor PHP local primero (no es seguro reemplazar database.sqlite
/// con una conexion abierta contra ese archivo), copia lo nuevo encima de
/// lo viejo, y borra los -wal/-shm sueltos que hayan quedado del sqlite
/// ANTERIOR (si no, podrian aplicarse por error sobre el sqlite nuevo en
/// el proximo arranque). El JS que invoca este comando (ver resources/
/// views/filament/pages/respaldo-local.blade.php) relanza la app con
/// @tauri-apps/plugin-process DESPUES de que esto termine.
#[tauri::command]
fn preparar_restauracion(app: tauri::AppHandle, servidor: tauri::State<PhpServerHandle>) -> Result<(), String> {
    detener_servidor_local(servidor);

    let data_dir = dunce::simplified(
        &app.path()
            .app_data_dir()
            .map_err(|e| format!("no se pudo resolver el directorio de datos: {e}"))?,
    )
    .to_path_buf();

    let pendiente_dir = data_dir.join("storage").join("app").join("pending-restore");
    let marcador = data_dir.join("storage").join("app").join("pending-restore.json");

    if !marcador.exists() {
        return Err("No hay ninguna restauracion preparada.".into());
    }

    let db_nueva = pendiente_dir.join("database.sqlite");
    let db_destino = data_dir.join("database.sqlite");

    if db_nueva.exists() {
        fs::copy(&db_nueva, &db_destino).map_err(|e| format!("no se pudo reemplazar la base de datos: {e}"))?;

        for sufijo in ["-wal", "-shm"] {
            let residuo = data_dir.join(format!("database.sqlite{sufijo}"));
            let _ = fs::remove_file(residuo);
        }
    }

    let public_nuevo = pendiente_dir.join("storage_public");
    let public_destino = data_dir.join("storage").join("app").join("public");

    if public_nuevo.exists() {
        let _ = fs::remove_dir_all(&public_destino);
        copiar_directorio_recursivo(&public_nuevo, &public_destino)
            .map_err(|e| format!("no se pudieron restaurar los archivos: {e}"))?;
    }

    let _ = fs::remove_dir_all(&pendiente_dir);
    let _ = fs::remove_file(&marcador);

    log::info!("Restauracion de respaldo completada.");

    Ok(())
}

/// Arranca el servidor PHP+SQLite local (migraciones, seed si es la
/// primera vez, "php -S") y devuelve la URL local ("http://127.0.0.1:puerto")
/// para navegar la ventana. Extraido de run() para poder llamarlo tanto al
/// arrancar normalmente (modo servidor ya elegido en corridas anteriores)
/// como recien elegido "Servidor" en splash/modo.html (guardar_modo_servidor).
fn arrancar_servidor(app: &tauri::AppHandle) -> String {
    let port = resolver_puerto_servidor(app);
    let env = preparar_entorno_local(app, port);
    let app_url = format!("http://127.0.0.1:{port}");

    correr_artisan(&env, &["migrate", "--force"], &app_url);

    if env.is_first_run {
        correr_artisan(
            &env,
            &["db:seed", "--class=Database\\Seeders\\TurionLocalSeeder", "--force"],
            &app_url,
        );
    }

    let child = lanzar_servidor_php(&env, port, &app_url);

    if !wait_for_port(port, Duration::from_secs(20)) {
        log::error!("El servidor local de PHP no respondio a tiempo en el puerto {port}");
    }

    app.manage(PhpServerHandle(Mutex::new(Some(child))));

    app_url
}

/// Comando invocado desde splash/modo.html cuando se elige "Servidor" en
/// el primer arranque: guarda la eleccion, arranca PHP+SQLite (igual que
/// hoy hace run() directo) y navega la ventana ya abierta hacia el login.
#[tauri::command]
fn guardar_modo_servidor(app: tauri::AppHandle) -> Result<(), String> {
    escribir_modo(&app, &ModoInstalacion::Servidor { puerto: None })?;

    let app_url = arrancar_servidor(&app);
    let destino = format!("{app_url}/login");

    if let Some(ventana) = app.get_webview_window("main") {
        ventana
            .navigate(destino.parse().map_err(|e| format!("URL invalida: {e}"))?)
            .map_err(|e| format!("no se pudo abrir la app: {e}"))?;
    }

    Ok(())
}

/// Comando invocado desde splash/modo.html cuando se elige "Terminal /
/// Caja adicional": este equipo NUNCA arranca PHP ni SQLite propios --
/// solo guarda la direccion del servidor y navega la ventana directo a
/// /emparejar-terminal (ver App\Http\Controllers\
/// EmparejarTerminalLocalController), que resuelve sola si hay que pedir
/// el codigo o si ya se puede pasar al login.
/// Normaliza lo que haya escrito/pegado la persona en el campo "Dirección
/// del servidor" de splash/modo.html a una URL base limpia (sin barra
/// final, sin /emparejar-terminal). Tolera dos formatos validos: solo
/// "ip:puerto" (lo que se le pide que escriba) o la URL completa que
/// muestra la pagina "Conectar Terminales" del servidor (pensada para
/// pegarla en un navegador cualquiera, con /emparejar-terminal incluido)
/// -- sin esto ultimo, pegar tal cual esa direccion duplicaba la ruta
/// (.../emparejar-terminal/emparejar-terminal) y el terminal nunca
/// llegaba a conectar. None si quedo vacio.
fn normalizar_url_servidor(input: &str) -> Option<String> {
    let recortado = input.trim().trim_end_matches('/');

    if recortado.is_empty() {
        return None;
    }

    let con_esquema = if recortado.starts_with("http://") || recortado.starts_with("https://") {
        recortado.to_string()
    } else {
        format!("http://{recortado}")
    };

    Some(
        con_esquema
            .trim_end_matches('/')
            .strip_suffix("/emparejar-terminal")
            .unwrap_or(&con_esquema)
            .to_string(),
    )
}

/// Prueba una conexion TCP corta contra "host:puerto" (extraido de una URL
/// tipo "http://192.168.1.5:8734") -- se usa antes de confiar en la
/// direccion guardada de un terminal, porque la IP del servidor puede
/// cambiar sola (DHCP) cada vez que se reinicia. Sin este chequeo, un
/// terminal con la IP vieja se quedaba pegado contra la pantalla de error
/// cruda del navegador ("tardo demasiado en responder"), sin ninguna
/// forma de volver a configurar la direccion salvo borrando modo.json a
/// mano.
fn servidor_alcanzable(url: &str) -> bool {
    let sin_esquema = url.trim_start_matches("https://").trim_start_matches("http://");
    let host_puerto = sin_esquema.split('/').next().unwrap_or(sin_esquema);

    let Some(direccion) = host_puerto.to_socket_addrs().ok().and_then(|mut it| it.next()) else {
        return false;
    };

    TcpStream::connect_timeout(&direccion, Duration::from_secs(3)).is_ok()
}

#[tauri::command]
fn guardar_modo_cliente(app: tauri::AppHandle, servidor_url: String) -> Result<(), String> {
    let url_completa = normalizar_url_servidor(&servidor_url)
        .ok_or_else(|| "Escribí la dirección del servidor.".to_string())?;

    if !servidor_alcanzable(&url_completa) {
        return Err(
            "No se pudo conectar a esa dirección. Verificá que esté bien escrita y que el equipo servidor esté encendido.".to_string(),
        );
    }

    escribir_modo(
        &app,
        &ModoInstalacion::Cliente { servidor_url: url_completa.clone() },
    )?;

    let destino = format!("{url_completa}/emparejar-terminal?machine_id={}", machine_id());

    if let Some(ventana) = app.get_webview_window("main") {
        ventana
            .navigate(destino.parse().map_err(|e| format!("Dirección de servidor inválida: {e}"))?)
            .map_err(|e| format!("no se pudo conectar al servidor: {e}"))?;
    }

    Ok(())
}

#[cfg_attr(mobile, tauri::mobile_entry_point)]
pub fn run() {
    tauri::Builder::default()
        .invoke_handler(tauri::generate_handler![
            detener_servidor_local,
            preparar_restauracion,
            guardar_modo_servidor,
            guardar_modo_cliente,
        ])
        .setup(|app| {
            if cfg!(debug_assertions) {
                app.handle().plugin(
                    tauri_plugin_log::Builder::default()
                        .level(log::LevelFilter::Info)
                        .build(),
                )?;
            }

            // A proposito SIN tauri-plugin-updater: la edicion Local no
            // tiene actualizaciones automaticas -- cada actualizacion se
            // cobra aparte y el super_admin la entrega a mano (ver
            // App\Support\PosEdition::esLocal() y el gate del render hook
            // en AdminPanelProvider). tauri-plugin-process se mantiene solo
            // porque relaunch() lo sigue usando la restauracion de
            // respaldos (ver resources/views/filament/pages/
            // respaldo-local.blade.php).
            app.handle().plugin(tauri_plugin_process::init())?;

            let handle = app.handle().clone();
            let titulo_base = format!("Sistema POS Local v{}", app.package_info().version);

            // "Varios terminales contra un servidor local": el primer
            // arranque de cualquier instalacion (servidor O terminal)
            // pregunta el rol antes de hacer nada mas -- un terminal NUNCA
            // arranca PHP/SQLite propios, asi que esta decision tiene que
            // pasar ANTES de preparar_entorno_local(), no despues.
            match leer_modo(&handle) {
                None => {
                    WebviewWindowBuilder::new(app, "main", WebviewUrl::App("modo.html".into()))
                        .title(format!("{titulo_base} — Configuración inicial"))
                        .inner_size(520.0, 560.0)
                        .resizable(false)
                        .center()
                        .build()?;
                }
                Some(ModoInstalacion::Servidor { .. }) => {
                    let app_url = arrancar_servidor(&handle);

                    // "/" es la pagina de aterrizaje (marketing) del sitio --
                    // no tiene sentido aca, se entra directo al login. Si la
                    // instalacion todavia no se activo con un codigo,
                    // EnsureLicenciaLocalActiva (lado PHP) redirige sola a
                    // /activar.
                    let ventana_url = format!("{app_url}/login");

                    WebviewWindowBuilder::new(app, "main", WebviewUrl::External(ventana_url.parse().unwrap()))
                        .title(titulo_base)
                        .inner_size(1280.0, 800.0)
                        .min_inner_size(1024.0, 640.0)
                        .maximized(true)
                        .build()?;
                }
                Some(ModoInstalacion::Cliente { servidor_url }) => {
                    if servidor_alcanzable(&servidor_url) {
                        // Sin PHP, sin SQLite: solo un webview contra el
                        // servidor de la red local. /emparejar-terminal
                        // resuelve sola si hace falta pedir el codigo o si
                        // ya se puede pasar directo al login (ver
                        // EmparejarTerminalLocalController del lado PHP).
                        let ventana_url = format!("{servidor_url}/emparejar-terminal?machine_id={}", machine_id());

                        WebviewWindowBuilder::new(app, "main", WebviewUrl::External(ventana_url.parse().unwrap()))
                            .title(format!("{titulo_base} (Terminal)"))
                            .inner_size(1280.0, 800.0)
                            .min_inner_size(1024.0, 640.0)
                            .maximized(true)
                            .build()?;
                    } else {
                        // La direccion guardada ya no responde -- tipico
                        // cuando el router le asigna otra IP al servidor
                        // al reiniciar (DHCP). En vez de la pantalla de
                        // error cruda del navegador, se vuelve a
                        // splash/modo.html para que puedan escribir la
                        // direccion nueva sin tener que borrar archivos a
                        // mano (ver guardar_modo_cliente, que vuelve a
                        // probar la conexion antes de guardar).
                        WebviewWindowBuilder::new(app, "main", WebviewUrl::App("modo.html".into()))
                            .title(format!("{titulo_base} — No se pudo conectar al servidor"))
                            .inner_size(520.0, 560.0)
                            .resizable(false)
                            .center()
                            .build()?;
                    }
                }
            }

            Ok(())
        })
        .build(tauri::generate_context!())
        .expect("error al construir la aplicacion de Tauri")
        .run(|app_handle, event| {
            if let tauri::RunEvent::ExitRequested { .. } = event {
                if let Some(state) = app_handle.try_state::<PhpServerHandle>() {
                    if let Some(mut child) = state.0.lock().unwrap().take() {
                        let _ = child.kill();
                    }
                }
            }
        });
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn machine_id_es_estable_entre_llamadas() {
        assert_eq!(machine_id(), machine_id());
    }

    #[test]
    fn servidor_alcanzable_detecta_un_puerto_que_si_escucha() {
        let listener = TcpListener::bind("127.0.0.1:0").unwrap();
        let puerto = listener.local_addr().unwrap().port();

        assert!(servidor_alcanzable(&format!("http://127.0.0.1:{puerto}")));
    }

    #[test]
    fn servidor_alcanzable_detecta_un_puerto_que_no_responde() {
        let listener = TcpListener::bind("127.0.0.1:0").unwrap();
        let puerto = listener.local_addr().unwrap().port();
        drop(listener);

        assert!(!servidor_alcanzable(&format!("http://127.0.0.1:{puerto}")));
    }

    #[test]
    fn servidor_alcanzable_con_direccion_invalida_no_revienta() {
        assert!(!servidor_alcanzable("no-es-una-direccion-valida"));
    }

    #[test]
    fn normalizar_url_servidor_agrega_esquema_a_ip_puerto_solo() {
        assert_eq!(
            normalizar_url_servidor("192.168.1.5:8734"),
            Some("http://192.168.1.5:8734".to_string())
        );
    }

    #[test]
    fn normalizar_url_servidor_quita_emparejar_terminal_si_lo_pegaron_completo() {
        assert_eq!(
            normalizar_url_servidor("http://192.168.18.35:62543/emparejar-terminal"),
            Some("http://192.168.18.35:62543".to_string())
        );
    }

    #[test]
    fn normalizar_url_servidor_tolera_espacios_y_barra_final() {
        assert_eq!(
            normalizar_url_servidor("  192.168.1.5:8734/  "),
            Some("http://192.168.1.5:8734".to_string())
        );
    }

    #[test]
    fn normalizar_url_servidor_respeta_https_si_lo_escriben() {
        assert_eq!(
            normalizar_url_servidor("https://192.168.1.5:8734"),
            Some("https://192.168.1.5:8734".to_string())
        );
    }

    #[test]
    fn normalizar_url_servidor_vacio_devuelve_none() {
        assert_eq!(normalizar_url_servidor("   "), None);
    }

    #[test]
    fn modo_instalacion_servidor_serializa_como_espera_splash_modo_html() {
        let json = serde_json::to_string(&ModoInstalacion::Servidor { puerto: None }).unwrap();
        assert_eq!(json, r#"{"rol":"servidor"}"#);

        let de: ModoInstalacion = serde_json::from_str(&json).unwrap();
        assert!(matches!(de, ModoInstalacion::Servidor { puerto: None }));
    }

    #[test]
    fn modo_instalacion_servidor_con_puerto_serializa_y_deserializa() {
        let json = serde_json::to_string(&ModoInstalacion::Servidor { puerto: Some(8734) }).unwrap();
        assert_eq!(json, r#"{"rol":"servidor","puerto":8734}"#);

        let de: ModoInstalacion = serde_json::from_str(&json).unwrap();
        assert!(matches!(de, ModoInstalacion::Servidor { puerto: Some(8734) }));
    }

    #[test]
    fn modo_instalacion_servidor_de_una_instalacion_vieja_sin_puerto_no_revienta() {
        let de: ModoInstalacion = serde_json::from_str(r#"{"rol":"servidor"}"#).unwrap();
        assert!(matches!(de, ModoInstalacion::Servidor { puerto: None }));
    }

    #[test]
    fn puerto_libre_detecta_un_puerto_que_si_esta_libre() {
        let listener = TcpListener::bind("127.0.0.1:0").unwrap();
        let puerto = listener.local_addr().unwrap().port();
        drop(listener);

        assert!(puerto_libre(puerto));
    }

    #[test]
    fn puerto_libre_detecta_un_puerto_ocupado() {
        // Mismo bind que usa puerto_libre (0.0.0.0): en Windows, bindear
        // 0.0.0.0:puerto mientras solo 127.0.0.1:puerto esta ocupado NO
        // siempre choca, asi que hay que ocupar exactamente esa direccion
        // para que el test sea confiable.
        let listener = TcpListener::bind(("0.0.0.0", 0)).unwrap();
        let puerto = listener.local_addr().unwrap().port();

        assert!(!puerto_libre(puerto));
    }

    #[test]
    fn modo_instalacion_cliente_serializa_con_servidor_url() {
        let modo = ModoInstalacion::Cliente {
            servidor_url: "http://192.168.1.5:8734".to_string(),
        };
        let json = serde_json::to_string(&modo).unwrap();
        assert_eq!(json, r#"{"rol":"cliente","servidor_url":"http://192.168.1.5:8734"}"#);

        let de: ModoInstalacion = serde_json::from_str(&json).unwrap();
        match de {
            ModoInstalacion::Cliente { servidor_url } => assert_eq!(servidor_url, "http://192.168.1.5:8734"),
            ModoInstalacion::Servidor { .. } => panic!("se esperaba Cliente"),
        }
    }

    #[test]
    fn parsear_ipv4_lan_toma_la_primera_ip_real_ingles() {
        let texto = "\
Ethernet adapter Ethernet:

   Connection-specific DNS Suffix  . :
   Link-local IPv6 Address . . . . . : fe80::1234
   IPv4 Address. . . . . . . . . . . : 192.168.1.5
   Subnet Mask . . . . . . . . . . . : 255.255.255.0
";

        assert_eq!(parsear_ipv4_lan(texto), Some("192.168.1.5".to_string()));
    }

    #[test]
    fn parsear_ipv4_lan_toma_la_primera_ip_real_espanol() {
        let texto = "\
Adaptador de Ethernet Ethernet:

   Direccion IPv4. . . . . . . . . . : 10.0.0.42
   Mascara de subred . . . . . . . . : 255.255.255.0
";

        assert_eq!(parsear_ipv4_lan(texto), Some("10.0.0.42".to_string()));
    }

    #[test]
    fn parsear_ipv4_lan_descarta_loopback_y_apipa() {
        let texto = "\
Adaptador de loopback:
   Direccion IPv4. . . . . . . . . . : 127.0.0.1

Adaptador desconectado:
   Direccion IPv4. . . . . . . . . . : 169.254.10.20

Ethernet:
   Direccion IPv4. . . . . . . . . . : 192.168.0.9
";

        assert_eq!(parsear_ipv4_lan(texto), Some("192.168.0.9".to_string()));
    }

    #[test]
    fn parsear_ipv4_lan_sin_ninguna_ip_real_devuelve_none() {
        let texto = "\
Adaptador de loopback:
   Direccion IPv4. . . . . . . . . . : 127.0.0.1
";

        assert_eq!(parsear_ipv4_lan(texto), None);
    }

    #[test]
    fn machine_id_tiene_el_formato_esperado() {
        let id = machine_id();

        let partes: Vec<&str> = id.split('-').collect();
        assert_eq!(partes.len(), 5);
        assert_eq!(partes[0], "MID");
        for parte in &partes[1..] {
            assert_eq!(parte.len(), 4);
            assert!(parte.chars().all(|c| c.is_ascii_hexdigit() && (c.is_ascii_digit() || c.is_uppercase())));
        }
    }

    #[test]
    fn copiar_directorio_recursivo_copia_subcarpetas_y_sobreescribe() {
        let base = std::env::temp_dir().join(format!("pos-local-test-copiar-{}", std::process::id()));
        let origen = base.join("origen");
        let destino = base.join("destino");

        let _ = fs::remove_dir_all(&base);
        fs::create_dir_all(origen.join("sub")).unwrap();
        fs::write(origen.join("archivo.txt"), b"nuevo").unwrap();
        fs::write(origen.join("sub").join("otro.txt"), b"anidado").unwrap();

        fs::create_dir_all(&destino).unwrap();
        fs::write(destino.join("archivo.txt"), b"viejo").unwrap();

        copiar_directorio_recursivo(&origen, &destino).unwrap();

        assert_eq!(fs::read_to_string(destino.join("archivo.txt")).unwrap(), "nuevo");
        assert_eq!(fs::read_to_string(destino.join("sub").join("otro.txt")).unwrap(), "anidado");

        let _ = fs::remove_dir_all(&base);
    }
}
