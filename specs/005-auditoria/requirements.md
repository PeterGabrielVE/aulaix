---
spec: 005-auditoria
prefijo: AUD
estado: implementada
fecha: 2026-10-11
backlog: F1-08
depende-de: 001-tenancy
la-usan: 004-identidad-global (IDG-R08.14, IDG-R10, IDG-R11.7), 011-evaluacion
---

# Requisitos — Bitácora de auditoría

## Contexto

F1-08 pide una «bitácora de cambios de notas y datos sensibles: registro de
quién, cuándo, valor anterior y nuevo». Las notas son el dato más disputado
de un plantel (reclamos de representantes, revisiones) y la identidad
decide quién puede hacer qué. La bitácora tiene que existir **antes** que
esas funcionalidades, para que nazcan auditadas.

Esta spec construye el **mecanismo**: cómo se registra un cambio, cómo se
protege el registro y cómo se consulta. Las specs que generan cambios
auditables (SPEC-004 identidad, SPEC-011 evaluación) declaran qué eventos
registran usando este mecanismo.

## Alcance

- **Incluye:** registro de cambios auditables, inmutabilidad, aislamiento
  por plantel, bitácora de plataforma para la identidad global, pantalla de
  consulta con filtros, permiso de consulta.
- **Excluye:** auditar **lecturas** (quién vio qué), exportar la bitácora,
  alertas sobre eventos, la pantalla de la bitácora de plataforma (llega con
  el superadministrador, SPEC-014).

## Decisiones de producto incorporadas (2026-10-11)

| Pregunta | Decisión |
|----------|----------|
| Qué se audita en v1 | Notas y evaluación, e identidad y accesos. |
| Quién consulta la bitácora del plantel | Administrador y Director. |
| Cuánto se conserva | Indefinidamente: no se borra nada hasta que la asesoría legal fije plazos (dependencia D1 de SPEC-004). |

## Glosario

| Término | Significado |
|---------|-------------|
| Cambio auditable | Acción que modifica un dato listado en el [catálogo de eventos](#catálogo-de-eventos-auditables-v1). |
| Registro | Entrada inmutable de la bitácora: quién, cuándo, qué, sobre qué, valores antes y después. |
| Bitácora del plantel | Registros de cambios que pertenecen a un plantel. Solo la ve ese plantel. |
| Bitácora de plataforma | Registros de cambios de la identidad global, que no pertenecen a ningún plantel. Solo la ve el superadministrador. |
| Actor | Quién hizo el cambio: una cuenta, un operador de soporte (comando de consola) o el propio sistema (tarea programada). |

## Catálogo de eventos auditables (v1)

| Evento | Bitácora | Valores antes / después | Lo emite |
|--------|----------|-------------------------|----------|
| Nota registrada, modificada o eliminada | Plantel | Nota | SPEC-011 |
| Definitiva recalculada por un cambio | Plantel | Definitiva | SPEC-011 |
| Plan de evaluación modificado (actividades, porcentajes) | Plantel | Actividades y porcentajes | SPEC-011 |
| Lapso abierto, cerrado o reabierto | Plantel | Estado del lapso | SPEC-007 |
| Invitación creada, corregida, reenviada o anulada | Plantel | Email, nombre y roles invitados | SPEC-004 |
| Membresía creada, suspendida, reactivada o terminada | Plantel | Estado y motivo | SPEC-004 |
| Roles de una membresía cambiados | Plantel | Roles | SPEC-004 |
| Nombre o email de la cuenta cambiados | Plataforma | Nombre / email | SPEC-004 |
| Contraseña cambiada o restablecida | Plataforma | **Ninguno** (solo que ocurrió) | SPEC-004 |
| Consolidación de cuentas (automática o por soporte) | Plataforma | Identificadores implicados | SPEC-004 |
| Solicitud, cancelación, aplazamiento, cierre o eliminación de cuenta | Plataforma | Estado de la cuenta | SPEC-004 |

Añadir un evento al catálogo no requiere cambiar esta spec: lo declara la
spec que lo emite, citando este mecanismo.

## Requisitos

### AUD-R01 — Qué se registra

- **AUD-R01.1** — Cuando se confirma un cambio auditable, el sistema deberá
  registrar: el tipo de evento, el actor, el momento (UTC), el plantel (si
  aplica), el tipo e identificador del objeto afectado y, para cada campo que
  cambió, su valor anterior y el nuevo.
- **AUD-R01.2** — El sistema deberá registrar solo los campos que cambiaron,
  no el objeto completo.
- **AUD-R01.3** — El sistema deberá registrar el origen del cambio: petición
  web (con la IP del cliente), comando de consola (con el operador) o tarea
  del sistema.
- **AUD-R01.4** — El sistema no deberá registrar nunca contraseñas, hashes,
  tokens ni secretos, ni siquiera cuando el evento trata sobre ellos.

### AUD-R02 — Atomicidad

- **AUD-R02.1** — Si un cambio auditable no llega a confirmarse (falla o se
  revierte), entonces el sistema no deberá dejar su registro.
- **AUD-R02.2** — Si el registro no puede guardarse, entonces el sistema no
  deberá confirmar el cambio auditable.

### AUD-R03 — Inmutabilidad

- **AUD-R03.1** — El sistema no deberá ofrecer ninguna forma de modificar ni
  borrar un registro.
- **AUD-R03.2** — Si se intenta modificar o borrar un registro directamente en
  la base de datos con el rol de la aplicación, entonces la base de datos
  deberá rechazarlo.

### AUD-R04 — Aislamiento

- **AUD-R04.1** — El sistema deberá guardar cada registro de la bitácora del
  plantel con su plantel, y no deberá mostrarlo en ningún otro (S-01).
- **AUD-R04.2** — El sistema deberá guardar los eventos de la identidad global
  en la bitácora de plataforma, y no deberá mostrarlos en la bitácora de
  ningún plantel (IDG-NF02).

### AUD-R05 — Consulta

**Historia:** Como director, quiero ver quién cambió una nota y cuándo, para
responder a un reclamo con evidencia.

- **AUD-R05.1** — El sistema deberá mostrar la bitácora del plantel del más
  reciente al más antiguo, paginada de 50 en 50.
- **AUD-R05.2** — El sistema deberá permitir filtrar por rango de fechas,
  actor, tipo de evento y objeto afectado.
- **AUD-R05.3** — Cuando se abre un registro, el sistema deberá mostrar, para
  cada campo cambiado, su valor anterior y el nuevo.
- **AUD-R05.4** — Si el actor ya no tiene cuenta (eliminada o anonimizada),
  entonces el sistema deberá seguir mostrando el registro, identificando al
  actor como «cuenta eliminada».
- **AUD-R05.5** — El sistema deberá mostrar las fechas en la hora de Venezuela
  (America/Caracas).

### AUD-R06 — Permiso de consulta

- **AUD-R06.1** — El sistema deberá permitir consultar la bitácora del plantel
  solo a quien tenga el permiso `ver-bitacora`.
- **AUD-R06.2** — Si alguien sin el permiso intenta consultarla, entonces el
  sistema deberá responder 403.
- **AUD-R06.3** — El sistema deberá incluir `ver-bitacora` entre los permisos
  base de Administrador y Director, también en los planteles que ya existen.

### AUD-R07 — Conservación

- **AUD-R07.1** — El sistema deberá conservar los registros indefinidamente,
  sin ningún proceso de borrado automático, mientras no existan plazos
  aprobados (D1).

## Requisitos no funcionales

- **AUD-NF01** (rendimiento) — Consultar un año de bitácora de un plantel, con
  cualquier combinación de filtros, responde en menos de 2 s.
- **AUD-NF02** (rendimiento) — Registrar un evento añade menos de 20 ms al
  cambio auditado.
- **AUD-NF03** (mantenibilidad) — Emitir un evento auditable desde otro módulo
  requiere solo declarar el evento, sin conocer cómo se guarda (puerto en
  `Shared` o en el módulo `Audit`, se decide en el diseño).

## Dependencias

| ID | Dependencia | Bloquea | No bloquea |
|----|-------------|---------|------------|
| **D1** | La misma de SPEC-004: plazos de conservación validados por asesoría legal. | Cualquier borrado o anonimización de registros. | Toda esta spec. |
| **D2** | SPEC-014 (superadministración). | La pantalla de la bitácora de plataforma. | Registrar eventos de plataforma (AUD-R04.2). |

## Preguntas abiertas

Ninguna.

## Matriz de trazabilidad

| Criterio | Test(s) |
|----------|---------|
| AUD-R01.1 | `tests/Feature/Audit/Infrastructure/DatabaseAuditTrailTest.php` › «records a change in the active institution log with who, when, what and the values»; `tests/Unit/Audit/Application/AuditEventTest.php` › «describes what changed on which object» |
| AUD-R01.2 | `tests/Unit/Audit/Application/ChangeSetTest.php` › «lists only the fields whose value changed» |
| AUD-R01.3 | `tests/Feature/Audit/Infrastructure/LaravelAuditContextTest.php` › «attributes a web change to the authenticated account and the client IP»; `tests/Feature/Audit/Infrastructure/LaravelAuditContextTest.php` › «attributes a console change to the operator who ran the command»; `tests/Feature/Audit/Infrastructure/LaravelAuditContextTest.php` › «attributes a change outside any request to the system»; `tests/Feature/TrustedProxiesTest.php` › «uses the forwarded client IP when the request comes from a trusted proxy»; `tests/Feature/TrustedProxiesTest.php` › «ignores a forwarded IP sent by anyone else» |
| AUD-R01.4 | `tests/Unit/Audit/Application/FieldChangeTest.php` › «refuses to record a sensitive field, whatever its value» |
| AUD-R02.1 | `tests/Feature/Audit/Infrastructure/DatabaseAuditTrailTest.php` › «leaves no entry when the audited change rolls back» |
| AUD-R02.2 | `tests/Feature/Audit/Infrastructure/DatabaseAuditTrailTest.php` › «does not commit the change when its entry can not be stored»; `tests/Feature/Audit/Infrastructure/DatabaseAuditTrailTest.php` › «refuses to record outside a transaction, where it could not be atomic» |
| AUD-R03.1 | `tests/Feature/Audit/AuditImmutabilityTest.php` › «an audit entry can not be modified»; `tests/Feature/Audit/AuditImmutabilityTest.php` › «an audit entry can not be deleted» |
| AUD-R03.2 | `tests/Feature/Audit/AuditImmutabilityTest.php` › «an audit entry can not be modified»; `tests/Feature/Audit/AuditImmutabilityTest.php` › «an audit entry can not be deleted»; `tests/Feature/Audit/AuditImmutabilityTest.php` › «an audit table can not be truncated» |
| AUD-R04.1 | `tests/Feature/Audit/AuditIsolationTest.php` › «an institution never sees the audit entries of another»; `tests/Feature/Audit/AuditIsolationTest.php` › «an audit entry is stored with the active institution»; `tests/Feature/Audit/AuditLogPageTest.php` › «an entry of another institution is not found» |
| AUD-R04.2 | `tests/Feature/Audit/AuditIsolationTest.php` › «the platform log is invisible while an institution is active»; `tests/Feature/Audit/AuditIsolationTest.php` › «nothing can be written to the platform log while an institution is active»; `tests/Feature/Audit/Infrastructure/DatabaseAuditTrailTest.php` › «records a global identity change in the platform log only» |
| AUD-R05.1 | `tests/Feature/Audit/AuditLogPageTest.php` › «a director sees the log newest first, fifty per page»; `tests/Feature/Audit/Infrastructure/DatabaseAuditLogTest.php` › «lists the newest entries first, one page at a time»; `tests/Unit/Audit/Application/ListAuditEntriesHandlerTest.php` › «asks the log for the requested page, fifty entries at a time» |
| AUD-R05.2 | `tests/Feature/Audit/AuditLogPageTest.php` › «the log can be filtered»; `tests/Feature/Audit/AuditLogPageTest.php` › «a date range that ends before it starts is rejected»; `tests/Feature/Audit/Infrastructure/DatabaseAuditLogTest.php` › «filters by date range, inclusive»; `tests/Feature/Audit/Infrastructure/DatabaseAuditLogTest.php` › «filters by actor, event type and affected object» |
| AUD-R05.3 | `tests/Feature/Audit/AuditLogPageTest.php` › «an entry shows each change, its actor and the time in Venezuela»; `tests/Feature/Audit/Infrastructure/DatabaseAuditLogTest.php` › «opens one entry with the values before and after each change» |
| AUD-R05.4 | `tests/Feature/Audit/AuditLogPageTest.php` › «an entry whose actor account is gone names it as deleted»; `tests/Feature/Audit/Infrastructure/DatabaseAuditLogTest.php` › «names the actor, and keeps the entry once the account is gone» |
| AUD-R05.5 | `tests/Feature/Audit/AuditLogPageTest.php` › «an entry shows each change, its actor and the time in Venezuela» |
| AUD-R06.1 | `tests/Feature/Audit/AuditLogPageTest.php` › «a director sees the log newest first, fifty per page»; `tests/Feature/Audit/AuditLogPageTest.php` › «the menu offers the log only to those who may view it» |
| AUD-R06.2 | `tests/Feature/Audit/AuditLogPageTest.php` › «a user without the ver-bitacora permission gets 403»; `tests/Feature/Audit/AuditLogPageTest.php` › «guests are sent to the login screen» |
| AUD-R06.3 | `tests/Feature/Audit/AuditPermissionTest.php` › «only administrador and director may view the audit log»; `tests/Feature/Audit/AuditPermissionTest.php` › «the permission migration gives the audit log to the managers of existing institutions» |
| AUD-R07.1 | `tests/Feature/Audit/AuditImmutabilityTest.php` › «an audit entry can not be deleted» |
| AUD-NF01 | `tests/Feature/Audit/AuditPerformanceTest.php` › «a year of audit log answers any combination of filters in under 2 seconds» (grupo `perf`) |
| AUD-NF02 | `tests/Feature/Audit/AuditPerformanceTest.php` › «recording an audit entry adds under 20 ms to a change» (grupo `perf`) |
