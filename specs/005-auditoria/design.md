---
spec: 005-auditoria
estado: implementada
fecha: 2026-10-11
requisitos: requirements.md
depende-de: 000-fundaciones-hexagonales, 001-tenancy
adr: 0005
---

# Diseño — Bitácora de auditoría

## Resumen

Nuevo módulo `Audit`. Los casos de uso de otros módulos registran eventos
llamando al puerto `AuditTrail` **dentro de su transacción** (ADR 0005). El
adaptador completa actor, origen y momento, y lo inserta en una de dos
tablas: `audit_entries` (bitácora del plantel, con RLS estándar) o
`platform_audit_entries` (identidad global, con una política que la oculta
siempre que haya un plantel activo). Un trigger de PostgreSQL rechaza
cualquier `UPDATE`, `DELETE` o `TRUNCATE`. La consulta es un query service
paginado detrás de la ruta `/bitacora`, protegida por el permiso
`ver-bitacora`.

## Estructura

```
src/Audit/
├── Application/                         API pública del módulo (A-06: otros módulos usan solo esta capa)
│   ├── AuditTrail.php                   puerto: record(AuditEvent), recordForPlatform(AuditEvent)
│   ├── AuditEvent.php                   DTO: type, subject, changes
│   ├── AuditSubject.php                 DTO: type, id
│   ├── FieldChange.php                  VO: field, from, to; rechaza campos sensibles
│   ├── ChangeSet.php                    cálculo puro: diff(antes, después) → solo campos cambiados
│   ├── AuditContext.php                 puerto: actor() y origin() del proceso en curso
│   ├── AuditOrigin.php                  VO: web(ip) | console(operator) | system
│   └── ListEntries/
│       ├── ListAuditEntriesQuery.php    filtros + página
│       ├── ListAuditEntriesHandler.php
│       ├── AuditEntryView.php           DTO de lectura (incluye actorName ?string)
│       └── AuditLog.php                 puerto de lectura (A-08)
└── Infrastructure/
    ├── DatabaseAuditTrail.php           inserta con la conexión (y transacción) en curso
    ├── LaravelAuditContext.php          Auth + Request + consola
    ├── DatabaseAuditLog.php             consulta paginada con filtros
    └── AuditServiceProvider.php
```

`Audit` no tiene capa `Domain`: no hay invariantes de negocio propias más
allá del formato del evento, que vive en los DTO/VO de `Application`, porque
**son** la API que usan los demás módulos (A-06 no les deja importar el
`Domain` de otro módulo).

Fuera de `src/`:

- `app/Http/Controllers/AuditLogController.php` (`index`, `show`) +
  `app/Http/Requests/AuditLogFilterRequest.php`.
- `resources/js/Pages/Audit/Index.vue` y `Show.vue`; entrada «Bitácora» en el
  menú si `can.viewAudit`.
- Migraciones: tablas, índices, RLS, trigger de inmutabilidad y permiso
  `ver-bitacora` para los planteles existentes.

## Modelo

| Elemento | Tipo | Invariantes / responsabilidad |
|----------|------|-------------------------------|
| `AuditEvent` | DTO | `type` no vacío con formato `modulo.objeto.accion` (`evaluation.grade.changed`); `subject`; `changes` (puede estar vacío: «contraseña cambiada»). |
| `FieldChange` | VO | `from`/`to` son escalares, `null` o listas de escalares (serializables a JSON). **Rechaza** campos cuyo nombre contiene `password`, `token`, `secret`, `hash` o `remember` (AUD-R01.4), con una excepción de programación, no de dominio. |
| `ChangeSet::diff(array $before, array $after)` | Cálculo puro | Devuelve solo los `FieldChange` de los campos cuyo valor difiere (AUD-R01.2). |
| `AuditOrigin` | VO | `web(?string $ip)`, `console(string $operator)`, `system()` (AUD-R01.3). |

Los tipos de evento son cadenas que define cada módulo emisor como
constantes propias: `Audit` no conoce el catálogo, para no depender de los
módulos que lo usan.

## Casos de uso

| Caso de uso / puerto | Criterios |
|----------------------|-----------|
| `AuditTrail::record()` (adaptador `DatabaseAuditTrail`) | AUD-R01.1 – R01.4, AUD-R02.1, AUD-R02.2, AUD-R04.1 |
| `AuditTrail::recordForPlatform()` | AUD-R01.*, AUD-R02.*, AUD-R04.2 |
| `ListAuditEntriesHandler` | AUD-R05.1, AUD-R05.2, AUD-R05.4 |
| Detalle (`AuditLog::find()`) | AUD-R05.3, AUD-R05.4 |
| Trigger de inmutabilidad (migración) | AUD-R03.1, AUD-R03.2, AUD-R07.1 |
| Permiso `ver-bitacora` (seeder + migración) | AUD-R06.1 – R06.3 |

`DatabaseAuditTrail` exige una transacción abierta
(`transactionLevel() > 0`): si un caso de uso olvida envolver el cambio en
`TransactionManager::run()`, falla en lugar de auditar sin atomicidad.
(En los tests `RefreshDatabase` ya abre una, así que este guard solo se
nota en producción; por eso cada emisor prueba la atomicidad con su propio
test de rollback.)

## Puertos y adaptadores

| Puerto | Capa | Adaptador | Notas |
|--------|------|-----------|-------|
| `AuditTrail` | Application | `DatabaseAuditTrail` | `insert` con la conexión por defecto; `institution_id` lo pone el `DEFAULT` de la columna (sesión RLS), igual que cualquier tabla de plantel. |
| `AuditContext` | Application | `LaravelAuditContext` | Actor = id de la cuenta autenticada o `null`. Origen: consola si `runningInConsole()` (operador fijado por el comando con `useConsoleOperator()`; si no, `system`), si no `web(request()->ip())`. |
| `AuditLog` | Application | `DatabaseAuditLog` | Query builder sobre `audit_entries`; el nombre del actor se resuelve con un `LEFT JOIN users`: si no hay fila, `actorName = null` → «cuenta eliminada» (AUD-R05.4). |

## Adaptadores de entrada (`app/`)

| Ruta | Middleware | Acción |
|------|------------|--------|
| `GET /bitacora` | `auth`, `active`, `verified`, `permission:ver-bitacora` | `AuditLogController@index`: valida filtros (`AuditLogFilterRequest`: fechas, actor, tipo, objeto) → `ListAuditEntriesHandler` → `Audit/Index` |
| `GET /bitacora/{entry}` | ídem | `AuditLogController@show` → `AuditLog::find()`; si no existe o es de otro plantel (RLS), 404 |

Las fechas se guardan en UTC (`timestamptz`) y el presentador del
controlador las formatea en `America/Caracas` (AUD-R05.5). La aplicación
sigue en `timezone = UTC`.

`HandleInertiaRequests` comparte `can.viewAudit` para el menú.

## Datos

```sql
audit_entries
  id             bigserial PK
  institution_id belongsToInstitution()        -- FK, índice, DEFAULT de la sesión RLS
  occurred_at    timestamptz NOT NULL
  event_type     varchar(100) NOT NULL
  subject_type   varchar(100) NOT NULL
  subject_id     varchar(64)  NOT NULL
  actor_user_id  bigint NULL                   -- sin FK: el registro sobrevive a la cuenta (AUD-R05.4)
  origin         varchar(10) NOT NULL          -- web | console | system
  ip             inet NULL
  operator       varchar(100) NULL
  changes        jsonb NOT NULL DEFAULT '{}'   -- { campo: { from, to } }

  índices: (institution_id, occurred_at DESC)
           (institution_id, event_type, occurred_at DESC)
           (institution_id, actor_user_id, occurred_at DESC)
           (institution_id, subject_type, subject_id)
  RowLevelSecurity::enable('audit_entries')    -- política estándar (TenantTableTest)

platform_audit_entries
  mismas columnas salvo institution_id
  índice: (occurred_at DESC), (event_type, occurred_at DESC)
  RLS forzada con política propia:
    USING      (<tenant de la sesión> IS NULL)
    WITH CHECK (<tenant de la sesión> IS NULL)
  → con un plantel activo la tabla se ve vacía y rechaza inserciones, incluso con SQL directo (AUD-R04.2)

trigger audit_immutable (en ambas tablas)
  BEFORE UPDATE OR DELETE FOR EACH ROW  → RAISE EXCEPTION
  BEFORE TRUNCATE FOR EACH STATEMENT    → RAISE EXCEPTION
```

`platform_audit_entries` no tiene `institution_id`, así que
`TenantTableTest` no la exige; un test propio verifica su política.

**Permiso:** `RolePermissionSeeder` añade `ver-bitacora` a `PERMISSIONS`
(Administrador lo recibe por tener todos) y a Director. Una migración lo
aplica a los planteles existentes con `RolePermissionSeeder::seedForInstitution()`,
igual que `2026_10_09_215951_add_director_and_coordinator_roles_…`.
Esto cambia la tabla de IAM-R11.1: se actualiza SPEC-002 en el mismo PR (P-04).

## Patrones aplicados

| Patrón | Fuerza que resuelve |
|--------|---------------------|
| Puerto explícito llamado en la transacción (ADR 0005) | Atomicidad cambio + registro (AUD-R02). |
| Context object (`AuditContext`) | Los casos de uso declaran *qué* cambió; *quién* y *desde dónde* los resuelve la infraestructura, sin pasar IPs ni usuarios por todas las capas. |
| Value Object con lista de exclusión (`FieldChange`) | Imposible guardar un campo sensible por descuido (AUD-R01.4). |
| Query service (A-08) | La consulta paginada no hidrata agregados. |
| Defensa en profundidad (trigger + RLS) | Inmutabilidad y aislamiento no dependen del código de la aplicación. |

## Modelo de amenazas (STRIDE)

| Amenaza | Vector | Mitigación | Verificado por |
|---------|--------|------------|----------------|
| Spoofing | Registrar acciones a nombre de otro actor | El actor sale de la sesión autenticada (`AuditContext`), nunca de la entrada del usuario | Test: el actor es la cuenta autenticada |
| Tampering | Borrar o alterar un registro para ocultar un cambio de nota | Sin rutas de edición; trigger que rechaza `UPDATE`/`DELETE`/`TRUNCATE` | AUD-R03.1, AUD-R03.2 (SQL directo) |
| Tampering | Desactivar el trigger (`ALTER TABLE … DISABLE TRIGGER`) | **Riesgo residual**: el rol de la app es dueño de las tablas (corre las migraciones). Mitigación futura: rol de migraciones separado del de ejecución (ADR futuro) | — |
| Repudiation | Negar haber cambiado una nota | Registro atómico con actor, momento, origen e IP | AUD-R01.*, AUD-R02.* |
| Information disclosure | Un plantel lee la bitácora de otro | RLS estándar en `audit_entries` | AUD-R04.1 |
| Information disclosure | Un plantel lee cambios de identidad global (email de una persona en otros planteles) | Tabla separada con política que la oculta con cualquier plantel activo | AUD-R04.2 |
| Information disclosure | Guardar una contraseña o token en `changes` | `FieldChange` rechaza nombres sensibles | AUD-R01.4 |
| Information disclosure | Un Docente consulta la bitácora | `permission:ver-bitacora` | AUD-R06.2 |
| Denial of service | Consultas lentas con muchos registros | Índices por plantel + paginación de 50 | AUD-NF01 (test de rendimiento, grupo `perf`) |
| Elevation of privilege | — | La bitácora es solo lectura; no concede permisos | — |

## Errores

| Situación | HTTP | Notas |
|-----------|------|-------|
| Filtro inválido (fecha mal formada, rango invertido) | 422 | `AuditLogFilterRequest` |
| Registro inexistente o de otro plantel | 404 | RLS lo hace invisible → `NotFound` |
| Sin permiso | 403 | middleware |
| Fallo al registrar (sin transacción, campo sensible, BD) | 500 | Error de programación: el cambio auditado se revierte (AUD-R02.2) |

## Estrategia de pruebas

- **Unit (Application, sin Laravel):** `ChangeSetTest` (solo campos cambiados,
  sin cambios → vacío), `FieldChangeTest` (rechaza campos sensibles, acepta
  valores serializables), `AuditOriginTest`, `ListAuditEntriesHandlerTest`
  con `AuditLog` en memoria.
- **Integración (Postgres):** `DatabaseAuditTrailTest`: registra con actor,
  origen y momento (`travelTo`); rollback del cambio → sin registro; fallo del
  registro → el cambio se revierte; sin transacción → excepción; plataforma
  rechazada con plantel activo. `AuditImmutabilityTest`: `UPDATE`, `DELETE` y
  `TRUNCATE` con SQL directo fallan. `AuditIsolationTest` (grupo `rls`): otro
  plantel no ve registros; la bitácora de plataforma se ve vacía con un
  plantel activo. Las tablas entran en `TenantTableTest` automáticamente.
- **Feature (HTTP):** lista ordenada y paginada, cada filtro, detalle con
  antes/después, actor eliminado, hora de Caracas, 403 sin permiso, 404 de
  otro plantel, permiso presente en Administrador y Director (planteles
  nuevos y existentes vía migración).
- **Rendimiento (grupo `perf`, fuera de CI):** 1 año simulado (≈ 200 000
  registros en un plantel) → consulta filtrada < 2 s; registrar < 20 ms.
- **Arquitectura:** `AuditLogController` entra en `MIGRATED_CONTROLLERS`.

## Cambios a otras specs

- **SPEC-002 / IAM-R11.1:** Administrador y Director ganan `ver-bitacora`.
- **SPEC-004:** IDG-R10 e IDG-R11.7 se implementarán con `AuditTrail`
  (`recordForPlatform` para identidad global, `record` para membresías,
  roles e invitaciones).

## Decisiones y alternativas

| Decisión | Alternativa descartada | Motivo |
|----------|------------------------|--------|
| Puerto explícito en la transacción (ADR 0005) | Triggers, EventBus, observers | Atomicidad y eventos de negocio; ver ADR. |
| Dos tablas | Una tabla con `institution_id` nullable | La política RLS estándar (que `TenantTableTest` exige idéntica en todas las tablas de plantel) no admite filas sin plantel; y separar hace imposible mezclar ambas bitácoras en una consulta. |
| Actor por id sin FK, nombre resuelto al leer | Guardar el nombre del actor en el registro | Guardar nombres obligaría a reescribir registros inmutables cuando D1 exija anonimizar. |
| API del módulo en `Audit\Application` | Puerto en `Shared` | A-06 ya permite usar la capa Application de otro módulo; `Shared` queda mínimo. |
| Tipos de evento como cadenas de cada emisor | Enum central en `Audit` | Un enum central haría que `Audit` dependiera de todos los módulos que auditan. |
| Configurar proxies de confianza (`TRUSTED_PROXIES`) | Guardar la IP tal como llega | Detrás del proxy TLS del despliegue, `request()->ip()` sería la IP del proxy: la IP registrada no serviría (y ya afecta hoy a la clave del bloqueo de login). **Requiere visto bueno.** |

## Riesgos y plan de rollback

- **Trigger desactivable por el dueño de la tabla** (ver STRIDE): riesgo
  residual aceptado hasta separar el rol de migraciones.
- **D1 puede exigir anonimizar registros** (p. ej. emails en invitaciones).
  La inmutabilidad entonces necesitará una vía controlada (función
  `SECURITY DEFINER` auditada). Se decidirá con D1; hoy no se borra nada.
- **Migración de permiso:** idempotente (`seedForInstitution`); su `down()`
  quita `ver-bitacora` de todos los planteles.
- **Rollback:** revertir el PR y ejecutar los `down()` (eliminan tablas,
  trigger y permiso). Los registros escritos entre el despliegue y el
  rollback se pierden: aceptable, porque todavía ningún módulo emite eventos
  (los emitirán SPEC-004 y SPEC-011).
