use std::fs;
use std::net::{TcpListener, TcpStream};
use std::path::PathBuf;
use std::process::{Child, Command, Stdio};
use std::sync::Mutex;
use std::time::{Duration, Instant};

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
        ("APP_NAME".into(), "Sistema POS".into()),
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

    let salida = cmd.output().expect("no se pudo ejecutar artisan localmente");

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
fn iniciar_sincronizacion_automatica(env: LocalEnv, app_url: String) {
    std::thread::spawn(move || loop {
        let intento = std::panic::catch_unwind(|| {
            correr_artisan(&env, &["pos:sync-catalog", "--if-due=12"], &app_url);
        });

        if let Err(e) = intento {
            let mensaje = e
                .downcast_ref::<String>()
                .cloned()
                .or_else(|| e.downcast_ref::<&str>().map(|s| s.to_string()))
                .unwrap_or_else(|| "error desconocido".into());
            log::warn!("Sincronizacion automatica en segundo plano fallo (se reintenta en la proxima revision): {mensaje}");
        }

        std::thread::sleep(Duration::from_secs(60 * 60));
    });
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

#[cfg_attr(mobile, tauri::mobile_entry_point)]
pub fn run() {
    tauri::Builder::default()
        .setup(|app| {
            if cfg!(debug_assertions) {
                app.handle().plugin(
                    tauri_plugin_log::Builder::default()
                        .level(log::LevelFilter::Info)
                        .build(),
                )?;
            }

            let env = preparar_entorno_local(app);
            let port = find_free_port();
            let app_url = format!("http://127.0.0.1:{port}");

            // Siempre (no solo en el primer arranque): "migrate" es
            // idempotente y rapido cuando no hay nada pendiente, y asi una
            // actualizacion del bundle que trae migraciones nuevas las
            // aplica sola en el siguiente arranque, sin depender de que el
            // usuario reinstale sobre una base de datos vacia.
            correr_artisan(&env, &["migrate", "--force"], &app_url);

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
                );
            }

            let child = lanzar_servidor_php(&env, port, &app_url);

            if !wait_for_port(port, Duration::from_secs(20)) {
                log::error!("El servidor local de PHP no respondio a tiempo en el puerto {port}");
            }

            app.manage(PhpServerHandle(Mutex::new(Some(child))));

            iniciar_sincronizacion_automatica(env.clone(), app_url.clone());

            // "/" es la pagina de aterrizaje (marketing) del sitio -- en
            // Turion no tiene sentido, se entra directo al login (que
            // redirige solo al POS si ya hay sesion activa).
            let ventana_url = format!("{app_url}/login");

            WebviewWindowBuilder::new(app, "main", WebviewUrl::External(ventana_url.parse().unwrap()))
                .title("Sistema POS")
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
