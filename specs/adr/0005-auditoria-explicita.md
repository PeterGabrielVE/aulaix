---
estado: aceptada
fecha-aceptación: 2026-10-11
fecha: 2026-10-11
spec: 005-auditoria
---

# ADR 0005 — Auditoría explícita en los casos de uso, dentro de la transacción

## Contexto

SPEC-005 exige registrar cada cambio auditable con quién, cuándo, valores
antes y después; que el registro y el cambio se confirmen juntos o ninguno
(AUD-R02); y que algunos eventos no tengan valores (una contraseña cambiada)
o no correspondan a una fila (una consolidación de cuentas). Hay tres formas
habituales de capturarlo.

## Decisión

Los casos de uso registran el evento **explícitamente**, llamando al puerto
`AuditTrail` del módulo `Audit` dentro de su `TransactionManager::run()`.
El evento describe el cambio en términos de negocio (tipo, objeto, campos
cambiados); el adaptador añade actor, origen y momento, y lo inserta en la
misma transacción.

## Consecuencias

**Positivas**
- Atomicidad real: misma conexión, misma transacción (AUD-R02.1, AUD-R02.2).
- Eventos de negocio con significado («nota modificada», «consolidación»),
  no diferencias de filas.
- Control fino de lo que **no** se guarda: sin contraseñas ni tokens
  (AUD-R01.4), solo campos cambiados (AUD-R01.2).
- Se prueba con un doble en memoria del puerto.

**Negativas**
- Un caso de uso que olvide llamar al puerto no audita. Mitigación: cada spec
  que emite eventos los declara en su matriz de trazabilidad con un test que
  comprueba el registro, y el catálogo de eventos de SPEC-005 lista quién
  debe emitirlos.
- Los cambios hechos fuera de los casos de uso (SQL manual en la base de
  datos) no quedan auditados. Está prohibido operar así (IDG-R11: nada de
  modificaciones manuales en la base de datos).

## Alternativas consideradas

| Alternativa | Por qué no |
|-------------|------------|
| **Triggers de PostgreSQL** que copian cada fila modificada | Capturan incluso el SQL directo, pero solo ven filas: no saben quién es el actor sin más variables de sesión, no distinguen «nota corregida en revisión» de «nota cargada», no pueden registrar eventos sin fila (consolidación) y guardarían columnas sensibles (hashes) salvo listas de exclusión por tabla. |
| **Eventos de dominio por el `EventBus`** con un listener de auditoría | El `EventBus` publica **después** del commit (FND-R03.4, a propósito, para los correos): un fallo al auditar ya no podría deshacer el cambio, y se incumpliría AUD-R02.2. Un segundo canal síncrono solo para auditoría sería una vía implícita difícil de seguir. |
| **Observers de Eloquent** | Acoplan la auditoría a los modelos de infraestructura (C-05: sin lógica de negocio en modelos) y, como los triggers, solo ven atributos, no eventos de negocio. |
