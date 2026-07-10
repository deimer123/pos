# Sistema POS — Notas del proyecto

## Entorno de trabajo

- El desarrollo se hace localmente en VSCode sobre Windows (`C:\Users\CAJA01\pos`).
- El repo se sube a GitHub: `https://github.com/deimer123/pos` (remoto `origin`, rama `main`).
- No hay acceso SSH a producción desde este entorno de VSCode.

## Despliegue a producción

- La app corre en un droplet en `159.89.81.81` (panel admin en `159.89.81.81/admin`, Filament).
- No hay CI/CD configurado (sin workflows en `.github/workflows`).
- El despliegue es manual: hay que entrar al servidor por SSH y correr:
  ```bash
  git pull origin main
  php artisan optimize:clear
  ```
- Después de desplegar, si el cambio es visual (favicon, assets), hacer refresco forzado en el navegador (`Ctrl+Shift+R`) porque el navegador cachea agresivamente.
