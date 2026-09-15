# Fuentes oficiales — RIPS / FEV / SIIFA

Última verificación de estos enlaces: 2026-09-15.
Todas son fuentes de `minsalud.gov.co` o `sispro.gov.co` (dominios oficiales
del Ministerio de Salud y Protección Social). Ante cualquier duda, priorizar
siempre el documento con fecha/versión más reciente sobre estas notas.

## Texto legal

- Resolución 0948 de 2026 (texto oficial):
  https://www.minsalud.gov.co/sites/rid/Lists/BibliotecaDigital/RIDE/DE/DIJ/resolucion-0948-de-2026.pdf
- Presentación/resumen oficial de la Resolución 948 de 2026:
  https://www.minsalud.gov.co/sites/rid/Lists/BibliotecaDigital/RIDE/DE/OT/pres-resolucion-0948-de-2026.pdf

## Micrositio SISPRO — Facturación Electrónica

Aquí se publican los "Documentos Técnicos" dinámicos (Anexos 1 y 2:
estructura JSON, catálogos, reglas de validación). Es la página que hay que
revisar con más frecuencia, porque puede cambiar sin que cambie la
resolución:

- https://www.sispro.gov.co/central-financiamiento/Pages/facturacion-electronica.aspx

## SIIFA — Sistema Integral de Información Financiera y Asistencial

- Página institucional SIIFA:
  https://www.minsalud.gov.co/SIIFA/Paginas/sistema-integral-de-informacion-financiera-y-asistencial.aspx

## Documentación técnica FEV-RIPS (integración / desarrollo)

- Manual de interoperabilidad del módulo FEV-RIPS (versión más reciente
  encontrada: v10.2, 2026-07-06):
  https://www.minsalud.gov.co/sites/rid/Lists/BibliotecaDigital/RIDE/VP/FS/manual-interoperabilidad-modulo2-fevrips-v102-20260706.pdf
- Manual de interoperabilidad del módulo FEV-RIPS (versión anterior, verificar
  cuál está vigente antes de usar):
  https://www.minsalud.gov.co/sites/rid/Lists/BibliotecaDigital/RIDE/VP/FS/manual-interoperabilidad-modulo-fev-rips.pdf
- Manual de usuario del sistema cliente-servidor FEV-RIPS:
  https://www.minsalud.gov.co/sites/rid/Lists/BibliotecaDigital/RIDE/DE/OT/manual-usuario-cliente-servidor-fev-rips.pdf

## Nota sobre fuentes secundarias

Artículos de terceros (blogs de proveedores de software, consultoras, etc.)
son útiles para entender el contexto rápidamente, pero **no** deben usarse
como base para reglas de validación o estructuras de datos en código. Usar
solo para orientación inicial; confirmar siempre contra los documentos de
Minsalud/SISPRO listados arriba.

## Pendiente de conseguir

- [ ] Especificación OpenAPI/Swagger de SIIFA (ambiente de pruebas) — no se
      encontró un enlace público directo al archivo `.yaml`/`.json`; puede
      requerir solicitud de credenciales de prueba ante el Ministerio o
      estar embebido dentro del manual de interoperabilidad como anexo.
- [ ] Estructura JSON completa del RIPS vigente (Anexo Técnico 1/2 post-948).
