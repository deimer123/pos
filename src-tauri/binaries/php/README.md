# PHP portable para Turión

Turión (la app de escritorio) empaca este PHP portable como sidecar para
correr el mismo código Laravel del POS localmente, sin depender de que el
usuario tenga PHP instalado, y sin conexión al droplet.

- `php.ini` — configuración versionada en git (extensiones habilitadas,
  límites de memoria/subida, timezone, opcache).
- `runtime/` — el PHP portable ya descomprimido y recortado. **No se
  versiona en git** (~75MB de binarios) — se regenera con el script.

## Regenerar `runtime/`

```
bash scripts/setup-php-portable.sh
```

Descarga `php-8.3.33-nts-Win32-vs16-x64.zip` de `windows.php.net`, lo
descomprime, deja solo las extensiones que el POS necesita (pdo_sqlite,
sqlite3, curl, openssl, mbstring, intl, fileinfo, gd, zip, opcache) y copia
`php.ini` dentro de `runtime/`.

Correr este script después de clonar el repo (antes de `cargo tauri build`)
y cada vez que se quiera actualizar la versión de PHP empacada.
