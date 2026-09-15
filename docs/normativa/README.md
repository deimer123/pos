# Normativa RIPS / Facturación Electrónica en Salud (Colombia)

Esta carpeta reúne notas y enlaces de referencia sobre el marco normativo vigente
para el futuro módulo de salud (IPS/consultorio odontológico) del sistema.

**Regla de oro:** estas notas son un resumen de trabajo, no la fuente legal.
Antes de implementar cualquier validación, estructura JSON o endpoint,
verificar contra el documento oficial vigente en las fuentes listadas abajo —
el propio Ministerio puede actualizar los "Documentos Técnicos" sin modificar
la resolución (ver más abajo por qué).

## Documentos en esta carpeta

- [`resolucion-948-2026.md`](./resolucion-948-2026.md) — resumen de la
  Resolución 948 de 2026 (marco vigente de RIPS como soporte de la FEV) y
  qué implica para el diseño del sistema.
- [`fuentes-oficiales.md`](./fuentes-oficiales.md) — enlaces directos a
  Minsalud/SISPRO para el texto legal y la documentación técnica (manuales,
  Swagger/OpenAPI de SIIFA).

## Cómo mantener esto actualizado

1. Cuando se descargue o revise un PDF oficial, anotar aquí la fecha de
   consulta y la versión del documento (los manuales de Minsalud llevan
   versión y fecha en el nombre de archivo, ej. `...-v102-20260706.pdf`).
2. Si el Ministerio publica una nueva versión del Documento Técnico
   (estructura JSON, catálogos, Swagger), actualizar
   `fuentes-oficiales.md` con el enlace nuevo y dejar constancia en el
   resumen de qué cambió, antes de tocar código de validación/generación
   de RIPS.
3. No copiar aquí el contenido completo de las resoluciones o manuales
   (son documentos largos y cambiantes) — solo el resumen operativo y el
   enlace a la fuente.
