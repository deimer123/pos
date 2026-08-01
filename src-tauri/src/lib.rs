use std::fs;
use std::net::{TcpListener, TcpStream};
use std::path::PathBuf;
use std::process::{Child, Command, Stdio};
use std::sync::{Arc, Mutex};
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
/// para poder matarlo cuando Turion se cierra.
struct PhpServerHandle(Mutex<Option<Child>>);

/// PID del proceso "php artisan ..." que este momento este corriendo en
/// segundo plano (la sincronizacion automatica), si hay alguno. Se usa para
/// poder matarlo tambien antes de instalar una actualizacion -- sin esto,
/// si el chequeo de actualizacion coincide con una sincronizacion en curso
/// (pasa justo al abrir la app: ambas arrancan casi al mismo tiempo), ese
/// php.exe sobrevive al cierre forzado de app.exe (por CREATE_BREAKAWAY_FROM_JOB,
/// necesario para que php.exe ni siquiera pueda arrancar empaquetado) y
/// queda huerfano bloqueando sus propios .dll para el siguiente intento de
/// instalar.
struct SincronizacionEnCurso(Mutex<Option<u32>>);

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
/// el SO). Es la base del fingerprint de maquina que exige la edicion
/// Local para activarse (ver machine_id() mas abajo y App\Services\
/// LocalLicense del lado PHP). None si por algun motivo no se pudo leer
/// (permisos, versiones raras de Windows, etc.) -- machine_id() cae a un
/// valor fijo en ese caso en vez de fallar el arranque de la app.
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

/// Fingerprint corto y legible de esta maquina, para que el cliente lo
/// pueda dictar/copiar al pedir una licencia de la edicion Local (ver
/// app/Filament/Pages/EmitirLicenciaLocal.php) y para que el codigo de
/// activacion quede atado a este equipo especifico (App\Services\
/// LocalLicense\ValidadorLicencia lo compara tal cual, sin reinterpretar
/// el hash -- por eso el mismo formato tiene que ser estable entre
/// corridas). No se guarda a disco: se recalcula en cada arranque para
/// que el valor siempre refleje la maquina real, incluso si alguien copia
/// app_data_dir a otro equipo por error.
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

/// Deja constancia de que version del bundle corrio por ultima vez en esta
/// instalacion (app_data_dir, no el directorio de recursos reemplazable).
/// Sin firma de codigo todavia no hay updater automatico de Tauri (lo
/// exige) -- las actualizaciones son manuales (reinstalar el .exe nuevo),
/// pero esto deja un rastro util para diagnosticar soporte y confirma que
/// la base de datos/storage locales sobreviven a una reinstalacion, ya que
/// viven fuera del directorio que el instalador reemplaza.
fn registrar_version_bundle(app: &tauri::App, data_dir: &std::path::Path) {
    let version_actual = app.package_info().version.to_string();
    let version_path = data_dir.join("version.txt");

    if let Ok(anterior) = fs::read_to_string(&version_path) {
        if anterior.trim() != version_actual {
            log::info!("Turion actualizado: {} -> {}", anterior.trim(), version_actual);
        }
    }

    let _ = fs::write(&version_path, &version_actual);
}

fn preparar_entorno_local(app: &tauri::App) -> LocalEnv {
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
        ("APP_NAME".into(), "Sistema POS Offline".into()),
        // Version del bundle actual (la de tauri.conf.json en el momento
        // de compilar) -- TurionSyncBar la muestra junto a Sincronizar/
        // Subir para confirmar a simple vista que version quedo instalada
        // despues de una actualizacion, sin tener que abrir DevTools.
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
        // Distingue esta edicion (hibrida, sincroniza con el droplet) de
        // la edicion Local (standalone, nunca sincroniza -- ver
        // App\Support\PosEdition del lado PHP). Sin esto, todo el codigo
        // gateado por edicion tomaria el default 'online' de config/pos.php
        // y dejaria de comportarse como Turion.
        ("POS_EDITION".into(), "hibrida".into()),
        // Fingerprint de esta maquina -- Turion no lo usa hoy (no exige
        // activacion), pero se deja seteado porque preparar_entorno_local()
        // es codigo compartido con la edicion Local (ver src-tauri-local),
        // donde si es obligatorio.
        ("POS_MACHINE_ID".into(), machine_id()),
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

fn correr_artisan(env: &LocalEnv, args: &[&str], app_url: &str, en_curso: &SincronizacionEnCurso) {
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

    let child = cmd.spawn().expect("no se pudo ejecutar artisan localmente");
    *en_curso.0.lock().unwrap() = Some(child.id());

    let salida = child.wait_with_output().expect("no se pudo esperar a artisan localmente");
    *en_curso.0.lock().unwrap() = None;

    if !salida.status.success() {
        let salida_std = String::from_utf8_lossy(&salida.stdout);
        let error = String::from_utf8_lossy(&salida.stderr);
        panic!(
            "artisan {:?} fallo en el arranque local (status={:?}, code={:?}): stdout=[{salida_std}] stderr=[{error}]",
            args, salida.status, salida.status.code()
        );
    }
}

// Chequeo periodico en segundo plano: "Sincronizar" (bajar catalogo/precios/
// stock/mesas) corre solo, sin que el cajero tenga que acordarse de pulsar
// el boton -- pos:sync-catalog decide internamente si ya toca (--if-due) o
// si es muy pronto desde la ultima vez, asi que llamarlo seguido es barato.
// "Subir" (mandar ventas/mesas/ordenes al droplet) sigue siendo SOLO manual
// a proposito: subir datos hacia afuera no deberia pasar sin que alguien lo
// decida.
fn iniciar_sincronizacion_automatica(env: LocalEnv, app_url: String, en_curso: Arc<SincronizacionEnCurso>) {
    std::thread::spawn(move || {
        // Al abrir la app: intento FORZADO (sin --if-due), sin importar
        // cuanto paso desde la ultima vez -- si hay internet, se
        // sincroniza ya mismo con lo mas reciente del droplet; si no hay
        // internet, esto falla en silencio (correr_artisan entra en
        // panic, catch_unwind lo atrapa aqui abajo) y la app sigue
        // funcionando con los datos que ya tenia (la ultima sincronizacion
        // programada mientras estaba cerrada, o la ultima manual).
        sincronizar_con_log(&env, &app_url, &["pos:sync-catalog"], &en_curso);

        loop {
            std::thread::sleep(Duration::from_secs(60 * 60));

            sincronizar_con_log(&env, &app_url, &["pos:sync-catalog", "--if-due=12"], &en_curso);
        }
    });
}

fn sincronizar_con_log(env: &LocalEnv, app_url: &str, args: &[&str], en_curso: &SincronizacionEnCurso) {
    let intento = std::panic::catch_unwind(|| {
        correr_artisan(env, args, app_url, en_curso);
    });

    if let Err(e) = intento {
        let mensaje = e
            .downcast_ref::<String>()
            .cloned()
            .or_else(|| e.downcast_ref::<&str>().map(|s| s.to_string()))
            .unwrap_or_else(|| "error desconocido".into());
        log::warn!("Sincronizacion automatica en segundo plano fallo (se reintenta en la proxima revision): {mensaje}");
    }
}

/// Genera (o refresca) un .bat que corre "Subir" + "Sincronizar" sin abrir
/// la ventana de Turion -- hace falta porque el Programador de tareas de
/// Windows no puede invocar el runtime de Laravel directamente: necesita
/// las mismas variables de entorno (APP_KEY, ruta de la base de datos...)
/// que hoy solo arma este mismo proceso al arrancar. Se regenera en cada
/// arranque para que, si una actualizacion cambio de ruta de instalacion
/// o de llave, el .bat programado siga apuntando a lo correcto.
fn generar_script_sincronizacion(env: &LocalEnv, data_dir: &std::path::Path) -> PathBuf {
    let bat_path = data_dir.join("sincronizar-en-segundo-plano.bat");

    let mut set_vars = String::new();
    for (k, v) in &env.vars {
        set_vars.push_str(&format!("set \"{k}={v}\"\r\n"));
    }

    let php = env.php_exe.display();
    let ini = env.php_ini.display();
    let cacert = env.cacert_path.display();
    let laravel = env.laravel_dir.display();

    let contenido = format!(
        "@echo off\r\n\
         cd /d \"{laravel}\"\r\n\
         {set_vars}\
         \"{php}\" -c \"{ini}\" -d \"curl.cainfo={cacert}\" -d \"openssl.cafile={cacert}\" artisan pos:push\r\n\
         \"{php}\" -c \"{ini}\" -d \"curl.cainfo={cacert}\" -d \"openssl.cafile={cacert}\" artisan pos:sync-catalog\r\n"
    );

    fs::write(&bat_path, contenido)
        .expect("no se pudo escribir el script de sincronizacion en segundo plano");

    bat_path
}

/// Registra (o refresca) la sincronizacion diaria a las 6pm en el
/// Programador de tareas de Windows -- para que un corte de internet de
/// varios dias no deje la informacion mas de un dia desactualizada. El
/// disparador "al iniciar sesion" (onlogon) se maneja aparte, en
/// registrar_inicio_con_windows(): crear una tarea con ese disparador
/// via schtasks pide permisos de administrador en equipos con ciertas
/// politicas (probado: "Acceso denegado" con una cuenta normal), mientras
/// que "daily" no los pide.
fn registrar_tareas_programadas(bat_path: &std::path::Path) {
    let mut cmd = Command::new("schtasks");
    cmd.arg("/create")
        .arg("/tn")
        .arg("SistemaPOSOfflineSync_6PM")
        .arg("/tr")
        .arg(format!("\"{}\"", bat_path.display()))
        .args(["/sc", "daily", "/st", "18:00"])
        .arg("/f")
        .stdout(Stdio::null())
        .stderr(Stdio::null());

    #[cfg(windows)]
    cmd.creation_flags(CREATE_NO_WINDOW);

    match cmd.status() {
        Ok(status) if status.success() => {
            log::info!("Tarea programada de sincronizacion diaria (6pm) registrada.");
        }
        Ok(status) => {
            log::warn!(
                "No se pudo registrar la tarea programada de las 6pm (codigo {:?}).",
                status.code()
            );
        }
        Err(e) => {
            log::warn!("No se pudo ejecutar schtasks para la tarea de las 6pm: {e}");
        }
    }
}

/// Hace que Windows corra el script de sincronizacion, sin ventana
/// visible, cada vez que este usuario inicia sesion -- escribiendo un
/// .vbs en la carpeta de Inicio (%APPDATA%\...\Startup), que Windows ya
/// ejecuta solo al iniciar sesion, sin necesitar el Programador de
/// tareas ni permisos de administrador (a diferencia del intento con
/// schtasks /sc onlogon, que en la practica pidio elevacion).
fn registrar_inicio_con_windows(bat_path: &std::path::Path) {
    let Ok(appdata) = std::env::var("APPDATA") else {
        log::warn!("No se pudo resolver %APPDATA% para registrar el inicio con Windows.");
        return;
    };

    let carpeta_inicio = PathBuf::from(appdata).join("Microsoft\\Windows\\Start Menu\\Programs\\Startup");
    let vbs_path = carpeta_inicio.join("SistemaPOSOfflineSync.vbs");

    // El .vbs solo reenvia al .bat con la ventana oculta (el 0 en Run) --
    // sin esto, Windows abriria brevemente una consola negra al iniciar
    // sesion cada vez.
    let contenido = format!(
        "Set objShell = CreateObject(\"WScript.Shell\")\r\nobjShell.Run \"\"\"{}\"\"\", 0, False\r\n",
        bat_path.display()
    );

    if let Err(e) = fs::write(&vbs_path, contenido) {
        log::warn!("No se pudo escribir el script de inicio con Windows: {e}");
    } else {
        log::info!("Sincronizacion al iniciar sesion registrada en la carpeta de Inicio.");
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
        .arg(format!("127.0.0.1:{port}"))
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
/// funcione el POS) justo antes de instalar una actualizacion. Sin esto,
/// cuando el instalador de la version nueva cierra la ventana para
/// reemplazar los archivos, mata "app.exe" pero NO a este proceso hijo
/// (que solo se cierra solo cuando la ventana se cierra de forma normal,
/// via el evento ExitRequested mas abajo) -- queda huerfano bloqueando
/// sus propios .dll y el instalador falla con "Error opening file for
/// writing". Se llama desde turion-updater.js justo antes de
/// downloadAndInstall().
#[tauri::command]
fn detener_servidor_local(
    servidor: tauri::State<PhpServerHandle>,
    sincronizacion: tauri::State<Arc<SincronizacionEnCurso>>,
) {
    if let Some(mut child) = servidor.0.lock().unwrap().take() {
        let _ = child.kill();
        log::info!("Servidor PHP local detenido antes de instalar la actualizacion.");
    }

    if let Some(pid) = sincronizacion.0.lock().unwrap().take() {
        let mut cmd = Command::new("taskkill");
        cmd.args(["/PID", &pid.to_string(), "/F"]);

        #[cfg(windows)]
        cmd.creation_flags(CREATE_NO_WINDOW);

        let _ = cmd.output();
        log::info!("Sincronizacion en segundo plano (pid {pid}) detenida antes de instalar la actualizacion.");
    }
}

#[cfg_attr(mobile, tauri::mobile_entry_point)]
pub fn run() {
    tauri::Builder::default()
        .invoke_handler(tauri::generate_handler![detener_servidor_local])
        .setup(|app| {
            if cfg!(debug_assertions) {
                app.handle().plugin(
                    tauri_plugin_log::Builder::default()
                        .level(log::LevelFilter::Info)
                        .build(),
                )?;
            }

            // Actualizaciones automaticas: revisa el manifiesto configurado en
            // tauri.conf.json (plugins.updater.endpoints) y, si hay una
            // version nueva firmada correctamente, la descarga e instala.
            // tauri-plugin-process habilita relaunch() para reiniciar la app
            // ya con la version nueva instalada.
            app.handle().plugin(tauri_plugin_updater::Builder::new().build())?;
            app.handle().plugin(tauri_plugin_process::init())?;

            let env = preparar_entorno_local(app);
            let port = find_free_port();
            let app_url = format!("http://127.0.0.1:{port}");
            let sincronizacion_en_curso = Arc::new(SincronizacionEnCurso(Mutex::new(None)));

            // Sincronizar aunque la ventana este cerrada: al iniciar
            // sesion en Windows y todos los dias a las 6pm (ver
            // registrar_tareas_programadas). Esto es ADEMAS de
            // iniciar_sincronizacion_automatica(), que solo corre
            // mientras esta app esta abierta.
            if let Ok(data_dir) = app.path().app_data_dir() {
                let bat_path = generar_script_sincronizacion(&env, &data_dir);
                registrar_tareas_programadas(&bat_path);
                registrar_inicio_con_windows(&bat_path);
            }

            // Siempre (no solo en el primer arranque): "migrate" es
            // idempotente y rapido cuando no hay nada pendiente, y asi una
            // actualizacion del bundle que trae migraciones nuevas las
            // aplica sola en el siguiente arranque, sin depender de que el
            // usuario reinstale sobre una base de datos vacia.
            correr_artisan(&env, &["migrate", "--force"], &app_url, &sincronizacion_en_curso);

            // Datos de referencia (tipos de documento, roles, ciudades,
            // plan de cuentas) que hacen falta para operar -- ej. crear un
            // cliente en modo taller necesita tipos_documento -- pero que no
            // vienen en el catalogo de una empresa (ver CatalogoExporter).
            // Solo una vez: correrlo de nuevo fallaria por llaves duplicadas.
            if env.is_first_run {
                correr_artisan(
                    &env,
                    &["db:seed", "--class=Database\\Seeders\\TurionLocalSeeder", "--force"],
                    &app_url,
                    &sincronizacion_en_curso,
                );
            }

            let child = lanzar_servidor_php(&env, port, &app_url);

            if !wait_for_port(port, Duration::from_secs(20)) {
                log::error!("El servidor local de PHP no respondio a tiempo en el puerto {port}");
            }

            app.manage(PhpServerHandle(Mutex::new(Some(child))));
            app.manage(sincronizacion_en_curso.clone());

            iniciar_sincronizacion_automatica(env.clone(), app_url.clone(), sincronizacion_en_curso);

            // "/" es la pagina de aterrizaje (marketing) del sitio -- en
            // Turion no tiene sentido, se entra directo al login (que
            // redirige solo al POS si ya hay sesion activa).
            let ventana_url = format!("{app_url}/login");

            let titulo_ventana = format!("Sistema POS Offline v{}", app.package_info().version);

            WebviewWindowBuilder::new(app, "main", WebviewUrl::External(ventana_url.parse().unwrap()))
                .title(titulo_ventana)
                .inner_size(1280.0, 800.0)
                .min_inner_size(1024.0, 640.0)
                .maximized(true)
                .build()?;

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
    fn machine_id_tiene_el_formato_esperado() {
        let id = machine_id();

        // MID-XXXX-XXXX-XXXX-XXXX (hex mayusculas), el mismo formato que
        // App\Services\LocalLicense\ValidadorLicencia compara tal cual.
        let partes: Vec<&str> = id.split('-').collect();
        assert_eq!(partes.len(), 5);
        assert_eq!(partes[0], "MID");
        for parte in &partes[1..] {
            assert_eq!(parte.len(), 4);
            assert!(parte.chars().all(|c| c.is_ascii_hexdigit() && (c.is_ascii_digit() || c.is_uppercase())));
        }
    }
}
