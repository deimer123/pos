# Resolución 948 de 2026 — RIPS como soporte de la FEV en salud

**Fecha de firma:** 14 de mayo de 2026, Ministro Guillermo Alfonso Jaramillo Martínez.
**Fecha de consulta de estas notas:** 2026-09-15.
**Fuente primaria:** ver [`fuentes-oficiales.md`](./fuentes-oficiales.md).

## Qué deroga

Deroga expresamente:

- Resolución 2275 de 2023
- Resolución 558 de 2024 (modificaba la 2275)
- Resolución 1884 de 2024 (modificaba la 2275)

Estas tres quedan sin efecto; la 948/2026 es ahora la norma única vigente
para RIPS y su relación con la Factura Electrónica de Venta (FEV) en salud.

## Qué establece

- Consolida el **RIPS como soporte obligatorio de la FEV** en salud —ya no
  se tramitan por separado, deben guardar correspondencia entre sí.
- Aplica a: IPS, proveedores de tecnologías en salud, EPS, ARL (componente
  salud), aseguradoras SOAT, y demás pagadores/entidades que participen en
  el proceso de facturación del sistema de salud.
- El RIPS se transmite en **formato JSON** (no en los archivos planos tipo
  texto AC/AP/AT/... del esquema anterior a 2023).
- Formaliza el uso de **SIIFA** (Sistema Integral de Información Financiera
  y Asistencial) y de "Documentos Técnicos" dinámicos: los Anexos Técnicos 1
  y 2, que antes iban dentro del cuerpo de la resolución, ahora se publican
  aparte en el micrositio de SISPRO y **el Ministerio puede actualizarlos
  sin modificar la resolución**. Consecuencia práctica: la estructura JSON
  exacta, catálogos y reglas de validación pueden cambiar con más
  frecuencia que la norma — hay que revisar el micrositio periódicamente,
  no asumir que "ya se validó una vez" es suficiente para siempre.
- Prepara la migración a **CIE-11** (reemplazo de CIE-10) y al esquema
  **"código VIDA"**, con exigencia técnica desde el **1 de julio de 2026**.
  Esto afecta directamente el catálogo de diagnósticos que use el módulo de
  historia clínica — no diseñar el campo de diagnóstico asumiendo solo
  CIE-10 fijo.

## Flujo funcional esperado (FEV-RIPS / SIIFA)

```
Atención clínica + Facturación
        │
        ▼
   Generar RIPS (JSON) ── debe corresponder 1:1 con la factura
        │
        ▼
     Validar (reglas del Documento Técnico vigente)
        │
        ▼
   Transmitir a SIIFA (API, auth Bearer/JWT)
        │
        ▼
   Consultar respuesta / estado
        │
        ▼
   Registrar trazabilidad (envío, respuesta, errores, reintentos)
```

## Implicaciones para el diseño del sistema (a tener en cuenta)

1. **No generar el RIPS como archivo aislado.** Debe derivarse de la misma
   fuente de datos que la factura (historia clínica + facturación), para
   que la correspondencia factura↔RIPS sea estructural (constraint de
   datos), no una convención de proceso que se pueda romper.
2. **Catálogo de diagnóstico dual-ready:** diseñar el campo de diagnóstico
   pensando en la transición CIE-10 → CIE-11 / código VIDA (julio 2026 en
   adelante), no hardcodear solo CIE-10.
3. **Tabla de trazabilidad de transmisión** separada de la factura:
   fecha de envío, identificador de transmisión, factura relacionada,
   estado, respuesta de SIIFA, errores de validación, fecha de respuesta,
   reintentos.
4. **Autenticación SIIFA:** el manual de interoperabilidad describe
   Bearer Token (JWT) — confirmar en el manual vigente antes de programar
   el cliente HTTP de integración.
5. **Versionar los Documentos Técnicos que se usen como referencia** (ver
   README de esta carpeta) porque pueden cambiar sin que cambie la
   resolución.

## Pendiente de verificar con fuente oficial antes de implementar

- [ ] Estructura JSON exacta del RIPS vigente (campos obligatorios por tipo
      de registro: consulta, procedimiento, medicamento, etc.)
- [ ] Especificación OpenAPI/Swagger de SIIFA (ambiente de pruebas y
      producción) — endpoints, esquema de autenticación, límites de tasa.
- [ ] Catálogo y reglas de transición CIE-10/CIE-11/código VIDA.
- [ ] Reglas concretas de validación cruzada factura↔RIPS.
