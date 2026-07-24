# Sistema POS — Notas del proyecto

## Entorno de trabajo

- El desarrollo se hace localmente en VSCode sobre Windows (`C:\Users\CAJA01\pos`).
- El repo se sube a GitHub: `https://github.com/deimer123/pos` (remoto `origin`, rama `main`).
- No hay acceso SSH a producción desde este entorno de VSCode.

## Despliegue a producción

- La app corre en un droplet en `159.89.81.81` (panel admin en `159.89.81.81/admin`, Filament).
- CI en GitHub Actions (`.github/workflows/ci.yml`): corre en cada push/PR a `main` — lint de sintaxis PHP + suite completa de tests (Pest) contra un MySQL efímero. Es solo validación, NO despliega nada.
- El despliegue sigue siendo manual: hay que entrar al servidor por SSH y correr:
  ```bash
  git pull origin main
  php artisan optimize:clear
  ```
- Después de desplegar, si el cambio es visual (favicon, assets), hacer refresco forzado en el navegador (`Ctrl+Shift+R`) porque el navegador cachea agresivamente.
- Para tests locales: crear `.env.testing` (no versionado) con una base MySQL de prueba separada — ver el comentario en `phpunit.xml` y el seed compartido en `tests/Pest.php`.
