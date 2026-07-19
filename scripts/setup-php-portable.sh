#!/usr/bin/env bash
# Descarga y prepara el PHP portable que Turion (Tauri) empaca como sidecar
# para correr el POS localmente sin depender de una instalacion de PHP en la
# maquina del usuario. Correr una vez antes de "cargo tauri build" (o cuando
# cambie la version de PHP objetivo). No versionado en git por su tamano
# (~75MB) -- ver .gitignore.
#
# Uso: bash scripts/setup-php-portable.sh
set -euo pipefail

PHP_VERSION="8.3.32"
ZIP_NAME="php-${PHP_VERSION}-nts-Win32-vs16-x64.zip"
URL="https://downloads.php.net/~windows/releases/${ZIP_NAME}"

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PHP_DIR="${ROOT_DIR}/src-tauri/binaries/php"
RUNTIME_DIR="${PHP_DIR}/runtime"
ZIP_PATH="${PHP_DIR}/${ZIP_NAME}"

# Extensiones que necesita el POS localmente:
#  - pdo_sqlite/sqlite3: la base de datos local de Turion
#  - curl: llamadas a la API del droplet (emparejamiento, sincronizar, subir)
#  - openssl: HTTPS del curl anterior, hashing de contraseñas
#  - mbstring, intl: requeridos por el framework y formatos de fecha/numero
#  - fileinfo: deteccion de tipo de archivo (subir fotos de ordenes de taller)
#  - gd: manejo de imagenes (fotos de producto/taller)
#  - zip: exportacion de reportes/plantillas
KEEP_EXTENSIONS=(
    php_curl.dll
    php_fileinfo.dll
    php_gd.dll
    php_intl.dll
    php_mbstring.dll
    php_opcache.dll
    php_openssl.dll
    php_pdo_sqlite.dll
    php_sqlite3.dll
    php_zip.dll
)

echo "==> Descargando PHP ${PHP_VERSION} portable (nts x64) desde ${URL}"
mkdir -p "${PHP_DIR}"
rm -rf "${RUNTIME_DIR}" "${PHP_DIR}/extracted"
curl -sL --fail -o "${ZIP_PATH}" "${URL}"

echo "==> Extrayendo"
unzip -q "${ZIP_PATH}" -d "${PHP_DIR}/extracted"

echo "==> Recortando extensiones/binarios no necesarios"
cd "${PHP_DIR}/extracted"

for f in ext/*.dll; do
    name="$(basename "$f")"
    keep=false
    for k in "${KEEP_EXTENSIONS[@]}"; do
        [ "$name" = "$k" ] && keep=true
    done
    $keep || rm -f "$f"
done

rm -rf dev lib
rm -f php-cgi.exe php-win.exe phpdbg.exe php8phpdbg.dll deplister.exe \
      phar.phar.bat pharcommand.phar news.txt php8embed.lib \
      README.md readme-redist-bins.txt snapshot.txt \
      php.ini-development php.ini-production
# Dependencias de extensiones que NO se incluyen (enchant/pgsql/ldap/sodium):
rm -f libenchant2.dll libpq.dll libsasl.dll libsodium.dll glib-2.dll gmodule-2.dll gobject-2.dll

cd "${PHP_DIR}"
mv extracted runtime
rm -f "${ZIP_PATH}"
cp "${PHP_DIR}/php.ini" "${RUNTIME_DIR}/php.ini" 2>/dev/null || true

echo "==> Verificando"
"${RUNTIME_DIR}/php.exe" -v
"${RUNTIME_DIR}/php.exe" -m

echo "==> Listo. Tamaño final:"
du -sh "${RUNTIME_DIR}"
