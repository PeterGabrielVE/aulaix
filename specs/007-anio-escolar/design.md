---
spec: 007-anio-escolar
estado: implementada
fecha: 2026-10-19
requisitos: requirements.md
depende-de: 000-fundaciones-hexagonales, 001-tenancy, 004-identidad-global, 005-auditoria
adr: 0007
---

# Diseño — Año escolar y lapsos

## Resumen

Se crea el módulo `AcademicCalendar`, cuyo agregado `SchoolYear` contiene
sus tres `Term` (lapsos) y protege todas las reglas de un año: fechas, orden,
duración y transiciones. Las reglas entre años del mismo plantel (uno
planificado, uno activo, sin solaparse) las comprueba un servicio de dominio
contra el resto de años. La base de datos las garantiza también, con índices
únicos parciales y una restricción de exclusión (ADR 0007), para que dos
gestores a la vez no las rompan (CAL-NF02).

La regla de bloqueo de notas (CAL-R05) se publica como una consulta de la
capa `Application`, para que SPEC-011 la use sin depender del dominio de
este módulo (A-06). Los permisos nuevos viven en `IdentityAccess`, que
también ofrece concederle a un miembro el permiso especial.

## Módulo y capas

```
src/AcademicCalendar/
├── Domain/
│   ├── SchoolYear.php                agregado: fechas, estado, sus 3 Term; todas las transiciones
│   ├── Term.php                      entidad interna: número, fechas, estado
│   ├── SchoolYearId.php · TermNumber.php (1..3)
│   ├── DateRange.php                 VO: inicio ≤ fin, solo fecha, inclusivo; contains, overlaps
│   ├── SchoolYearStatus.php          planned | active | closed
│   ├── TermStatus.php                pending | open | closed
│   ├── SchoolYearSchedule.php        servicio de dominio: reglas entre años del plantel
│   ├── CalendarRuleViolation.php     BusinessRuleViolation con campo
│   └── SchoolYearRepository.php      puerto
├── Application/
│   ├── CreateSchoolYear/ · ChangeSchoolYearDates/ · DeleteSchoolYear/
│   ├── ActivateSchoolYear/ · CloseSchoolYear/
│   ├── CloseTerm/ · ReopenTerm/
│   ├── ListSchoolYears/              query para la pantalla (CQRS ligero, A-08)
│   └── TermEditability/              API pública para SPEC-011 (CAL-R05)
└── Infrastructure/
    ├── Persistence/                  SchoolYearModel · TermModel · EloquentSchoolYearRepository
    │                                 · mapper · DatabaseCalendarLock · EloquentSchoolYearList
    └── AcademicCalendarServiceProvider.php
```

Fuera de `src/`:

- `app/Http/Controllers/SchoolYearController.php` y `TermController.php`,
  con sus FormRequests.
- `resources/js/Pages/Calendar/Index.vue`, con formularios y modales de
  confirmación, y la entrada «Año escolar» en el menú si `can.manageCalendar`.
  Diseño de una columna que funciona en 360 px y textos con «plantel», «año
  escolar» y «lapso»; el test de terminología de SPEC-004 lo vigila (CAL-NF04).
- Migraciones: las tablas, la RLS, los índices, la extensión `btree_gist` y
  los permisos nuevos para los planteles existentes.

En `IdentityAccess` (SPEC-004) cambia:

- `Permission`: se añaden `ManageCalendar` (`gestionar-calendario`) y
  `EditClosedTermGrades` (`editar-notas-lapso-cerrado`).
- `BaseRole`:
  - Administrador: todos los permisos **salvo** `EditClosedTermGrades`
    (CAL-R05.4);
  - Director: suma `ManageCalendar`.
- `RolePermissionSeeder`: lee los permisos de `BaseRole`, para no volver a
  duplicar la tabla.
- Nuevo caso de uso `SetClosedTermGradeEditing` (ver «Conceder el permiso
  especial»).

## Modelo de dominio

| Elemento | Tipo | Invariantes / responsabilidad |
|----------|------|-------------------------------|
| `DateRange` | Value Object | Fechas sin hora; `start ≤ end` (CAL-R01.2 para el año, R01.3 para el lapso); `contains(DateRange)`, `overlaps(DateRange)`, `startsAfter(DateRange)`. |
| `SchoolYear` | Agregado | **Al crearse** (`plan()`): estado `planned` y tres `Term` en `pending` (R01.1). Fin del año > inicio (R01.2). Duración ≤ 11 meses: `end ≤ start + 11 meses` (R01.8). Cada lapso dentro del año (R01.3), en orden y sin solaparse (R01.4); se aceptan huecos y no se exige cubrir el año (R01.5). `name()` = «año de inicio-año de fin» (R01.7); se calcula y no se guarda, así cambia con las fechas (R03.4). |
| | | **Transiciones:** `activate()`: planned → active y abre el lapso 1 (R02.2). `close(confirmed)`: active → closed, solo con los 3 lapsos cerrados (R02.4, R02.5); un año cerrado no tiene ninguna transición (R02.6). `changeDates(...)`: re-valida todo (R03.1, R03.2), rechaza en un año cerrado o si cambia un lapso cerrado (R03.3). `closeTerm(confirmed)`: cierra el abierto y abre el siguiente (R04.2). `reopenLastClosedTerm(reason)`: motivo obligatorio (R04.5), solo el último cerrado (R04.4, R04.7), y el abierto vuelve a `pending`. En planned o closed no se toca ningún lapso (R04.6). `canBeDeleted()`: solo en planned (R09.2). |
| | | **Invariante de estado:** activo ⇒ exactamente un lapso abierto, salvo los 3 cerrados (R04.1); planificado ⇒ ninguno abierto. |
| `Term` | Entidad (dentro del agregado) | Número 1..3 (`TermNumber`), `DateRange`, `TermStatus`. Solo el agregado lo modifica. |
| `SchoolYearSchedule` | Servicio de dominio | Recibe el año candidato y los demás años del plantel: rechaza un segundo planificado (R01.6), un segundo activo (R02.1, R02.3) y el solape de fechas con cualquier otro año, sea cual sea su estado (R01.9). Es puro: no consulta la base de datos. |
| `TermEditability` | Regla de dominio (en `Term`/`SchoolYear`) | `allowsGradeChanges(TermNumber, hasSpecialPermission)`: lapso abierto → sí (R05.1); cerrado o pending → solo con el permiso (R05.2); año cerrado → nunca (R05.3). |

Ninguna transición depende de la fecha de hoy (R04.3), así que el dominio
no necesita `Clock`. Las fechas de los lapsos son informativas.

## Casos de uso (Application)

Todos los de escritura: comprueban el permiso (S-04), toman el bloqueo del
calendario del plantel (ver ADR 0007), cargan, aplican, guardan y registran
en la bitácora dentro de **una** `TransactionManager::run` (CAL-NF02, ADR
0005).

| Caso de uso | Entrada | Salida | Criterios |
|-------------|---------|--------|-----------|
| `CreateSchoolYearHandler` | `CreateSchoolYearCommand` (fechas del año y de los 3 lapsos) | `SchoolYearId` | R01.1 – R01.9, R07.1 |
| `ChangeSchoolYearDatesHandler` | `ChangeSchoolYearDatesCommand` (id + fechas) | — | R03.1 – R03.4, R01.8, R01.9, R07.1 |
| `DeleteSchoolYearHandler` | `DeleteSchoolYearCommand` (id, confirmado) | — | R09.1, R09.2, R07.1 |
| `ActivateSchoolYearHandler` | id | — | R02.1 – R02.3, R07.1 |
| `CloseSchoolYearHandler` | id, confirmado | — | R02.4 – R02.6, R06.2, R07.1 |
| `CloseTermHandler` | id del año, confirmado | — | R04.1, R04.2, R04.3 (solo por acción del gestor), R04.6, R06.2, R07.1 |
| `ReopenTermHandler` | id del año, motivo | — | R04.4 – R04.7, R07.1 |
| `ListSchoolYearsHandler` (query) | — | `list<SchoolYearView>` con sus `TermView` | R06.1 |
| `TermEditabilityQuery` (query, API para 011) | id del año, número de lapso, `hasClosedTermPermission` | `bool` | R05.1 – R05.3, NF03 |

`TermEditabilityQuery` recibe ya resuelto si la persona tiene el permiso
especial. Quien lo llama (SPEC-011) lo sabe por su ruta o por
`IdentityAccess`, y así este módulo no depende de cómo se guardan los
permisos.

El agregado registra los cambios como eventos internos. El caso de uso los
traduce a `AuditEvent` (los tipos están en la tabla de abajo) y no audita
valores personales: solo fechas, estados y el motivo de la reapertura.

| Evento auditado (SPEC-005) | Campos |
|----------------------------|--------|
| `calendar.school_year.created` | fechas del año y de los lapsos |
| `calendar.school_year.dates_changed` | solo las fechas que cambian (`ChangeSet`) |
| `calendar.school_year.activated` · `.closed` · `.deleted` | estado anterior → nuevo |
| `calendar.term.closed` · `.reopened` | número del lapso, estado; `reason` en la reapertura |

## Conceder el permiso especial (CAL-R05.4)

Hoy no existe una pantalla de permisos (`gestionar-roles` no tiene interfaz).
Propuesta: en la edición de un miembro («Usuarios»), una casilla «Puede
editar notas de lapsos cerrados». Solo la ve y la cambia un **Administrador**.

- Caso de uso nuevo en `IdentityAccess`: `SetClosedTermGradeEditing(actor,
  miembro, bool)`. Concede o retira el permiso **directo** de Spatie al
  miembro, en el plantel activo, y registra
  `identity.membership.permission_granted` / `_revoked`.
- La regla «solo un Administrador» se añade a `RoleAssignmentPolicy`, junto
  a la de quién da el rol de Administrador (IAM-R10.1).
- Al salir del plantel o al cerrar la cuenta, el permiso se retira con los
  roles (IDG-R07.2).

## Puertos y adaptadores

| Puerto (interfaz) | Capa | Adaptador | Notas |
|-------------------|------|-----------|-------|
| `SchoolYearRepository` | Domain | `EloquentSchoolYearRepository` | `byId`, `othersOf(SchoolYearId?)` (los demás años del plantel), `save`, `delete`. RLS limita todo al plantel activo. |
| `CalendarLock` | Application | `DatabaseCalendarLock` | `pg_advisory_xact_lock` por plantel: serializa las escrituras del calendario del plantel (ADR 0007). Se prueba en aislamiento: en los tests de aplicación es un no-op. |
| `SchoolYearList` | Application | `EloquentSchoolYearList` | Lectura de la pantalla (A-08), sin hidratar agregados. |
| `TransactionManager`, `AuditTrail`, `CurrentTenant` | Shared / Audit / Tenancy | existentes | — |

No hay puerto de reloj ni de correo: el dominio no los necesita (R04.3) y los
avisos quedan fuera (F2-05).

## Adaptadores de entrada (`app/`)

Todo en el dominio del plantel (`{tenant}.APP_DOMAIN`), con los middleware
`auth`, `active`, `verified` y `permission:gestionar-calendario` (CAL-R06.3,
NF01).

| Ruta | Controlador | Caso de uso |
|------|-------------|-------------|
| `GET /calendario` | `SchoolYearController@index` | `ListSchoolYears` |
| `POST /calendario/anios` | `@store` (`SchoolYearRequest`) | `CreateSchoolYear` |
| `PUT /calendario/anios/{year}` | `@update` (`SchoolYearRequest`) | `ChangeSchoolYearDates` |
| `DELETE /calendario/anios/{year}` | `@destroy` (`confirmation`) | `DeleteSchoolYear` |
| `POST /calendario/anios/{year}/activar` | `@activate` | `ActivateSchoolYear` |
| `POST /calendario/anios/{year}/cerrar` | `@close` (`confirmation`) | `CloseSchoolYear` |
| `POST /calendario/anios/{year}/lapsos/cerrar` | `TermController@close` (`confirmation`) | `CloseTerm` |
| `POST /calendario/anios/{year}/lapsos/{term}/reabrir` | `TermController@reopen` (`reason`) | `ReopenTerm` |

- **Confirmación explícita (R06.2):** el modal de la pantalla envía
  `confirmation=true`. El caso de uso la exige (`confirmed`), así que una
  petición directa sin ella también se rechaza.
- **Validación en el borde:** el `SchoolYearRequest` comprueba la forma: 8
  fechas `Y-m-d` y el motivo de hasta 500 caracteres. Las reglas de negocio
  (orden, duración, solapes) viven en el dominio (S-09).
- **Rutas sin modelo:** `{year}` es un `int` sin enlace a modelo. Un año de
  otro plantel no existe bajo la RLS: `SchoolYearNotFound` → 404 (R08.1).
- **Permiso en la interfaz:** `HandleInertiaRequests` comparte
  `can.manageCalendar`, que muestra la entrada del menú.
- **Usuarios:** la edición del miembro gana la casilla del permiso especial
  (solo para Administradores).

## Datos

```
school_years  (belongsToInstitution + RowLevelSecurity::enable)          S-01, R08.2
  id, institution_id, starts_on date, ends_on date, status varchar, timestamps
  CHECK (ends_on > starts_on)
  UNIQUE (institution_id) WHERE status = 'active'                        R02.1, NF02
  UNIQUE (institution_id) WHERE status = 'planned'                       R01.6, NF02
  EXCLUDE USING gist (institution_id WITH =, daterange(starts_on, ends_on, '[]') WITH &&)   R01.9, NF02

terms  (belongsToInstitution + RowLevelSecurity::enable)
  id, institution_id, school_year_id FK cascade, number smallint CHECK (1..3),
  starts_on date, ends_on date, status varchar, timestamps
  UNIQUE (school_year_id, number)
  UNIQUE (school_year_id) WHERE status = 'open'                          R04.1, NF02
  CHECK (ends_on >= starts_on)
  INDEX (school_year_id, number)                                         NF03
```

- `CREATE EXTENSION IF NOT EXISTS btree_gist`: hace falta para la
  restricción de exclusión. En PostgreSQL ≥ 13 es una extensión *trusted*:
  el rol de la app (`NOSUPERUSER`, S-03) puede crearla porque es dueño de la
  base de datos (comprobado en el entorno local, PostgreSQL 16).
- El motivo de la reapertura no se guarda en `terms`: queda en la bitácora,
  que es su fuente de verdad (R07.1).
- `terms.institution_id` repite el del año para que la RLS sea estándar y
  `TenantTableTest` la vigile.
- Migración de permisos (como la de `ver-bitacora`, SPEC-005): vuelve a
  sembrar los roles de cada plantel existente (R06.5). El `down()` borra los
  dos permisos nuevos.

## Patrones aplicados

| Patrón | Fuerza que resuelve (C-05) |
|--------|----------------------------|
| Agregado (`SchoolYear` con sus `Term`) | Las reglas de los lapsos (orden, un solo abierto, reapertura del último) dependen unas de otras y del estado del año: un solo dueño las mantiene consistentes. |
| Servicio de dominio (`SchoolYearSchedule`) | Las reglas entre años no son de ningún año en particular; ponerlas en el agregado obligaría a cargar todos los años dentro de cada uno. |
| Value Object (`DateRange`) | Inicio ≤ fin, inclusión y solape aparecen en 6 criterios: un solo lugar los define y los prueba. |
| Repository | Persistencia del agregado con sus lapsos en una sola operación. |
| Query service (`SchoolYearList`, `TermEditability`) | Las lecturas no necesitan el agregado (A-08). `TermEditability` además es la API estable para SPEC-011 y tiene un objetivo de latencia (NF03). |
| Bloqueo pesimista por plantel (`CalendarLock`) | Dos gestores a la vez podrían pasar las comprobaciones de `SchoolYearSchedule` en paralelo. El bloqueo los serializa y las restricciones de la base de datos son el último respaldo (ADR 0007). |

## Modelo de amenazas (STRIDE)

| Amenaza | Vector | Mitigación | Verificado por |
|---------|--------|------------|----------------|
| **S**poofing | Actuar como gestor sin serlo | `auth` + `active` (membresía activa, IDG-R03.8) | Tests feature de 403 y de sesión (R06.4) |
| **T**ampering | Cambiar notas de un lapso cerrado | `TermEditability` (R05), que SPEC-011 aplica en cada guardado | Tests de `TermEditability` en dominio y aplicación |
| **T**ampering | Saltarse la confirmación con una petición directa | El caso de uso exige `confirmed` | Test feature sin `confirmation` → 422 |
| **T**ampering | Dos gestores a la vez dejan dos años activos o dos lapsos abiertos | `CalendarLock` + índices únicos parciales + `EXCLUDE` | Test de integración que viola cada restricción con SQL directo |
| **R**epudiation | «Yo no cerré ese lapso» | Bitácora en la misma transacción, con el motivo de la reapertura | Tests de R07.1 |
| **I**nformation disclosure | Ver el calendario de otro plantel por id | RLS estándar en `school_years` y `terms`; ids de otro plantel → 404 | Tests `rls` y feature de R08.1 – R08.3; `TenantTableTest` |
| **D**enial of service | Abusar de las acciones de escritura | Solo gestores autenticados; `throttle:30,1` en las rutas de escritura | Regla en las rutas |
| **E**levation of privilege | Un Director se da `editar-notas-lapso-cerrado` | Solo un Administrador concede el permiso, y ningún rol base lo trae (R05.4) | Tests de `RoleAssignmentPolicy` y de semilla de roles |
| **E**levation of privilege | El Administrador recibe el permiso especial por ser «todos» | `BaseRole::Administrador` excluye `EditClosedTermGrades` explícitamente | Test unitario de `BaseRole` |

## Errores

| Excepción de dominio | HTTP | Mensaje al usuario (ejemplos) |
|----------------------|------|-------------------------------|
| `CalendarRuleViolation` (fechas, duración, orden, solape, estados) | 422 en el campo | «El año escolar no puede durar más de 11 meses.» · «Las fechas se solapan con el año 2025-2026.» · «Cierra los tres lapsos antes de cerrar el año.» |
| `CalendarRuleViolation` en `confirmation` / `reason` | 422 | «Confirma la acción.» · «Indica el motivo de la reapertura.» |
| `SchoolYearNotFound` (`NotFound`) | 404 | — |
| Falta el permiso (middleware) | 403 | — |
| Violación de restricción de la base de datos (carrera que el bloqueo no evitó) | 409 (`Conflict`) | «Otra persona acaba de cambiar el calendario. Vuelve a cargar la página.» |

## Estrategia de pruebas

- **Dominio (unit, sin Laravel):**
  - `DateRange`;
  - `SchoolYear`: cada invariante y cada transición válida o rechazada, de
    R01 a R04 y R09, más el nombre derivado;
  - `SchoolYearSchedule`: un planificado, un activo y el solape con cada
    estado;
  - `TermEditability`: R05.1 – R05.3;
  - `BaseRole` y `Permission`: R05.4, R06.5.
- **Aplicación (unit, repositorio en memoria, `AuditTrail` falso y
  `CalendarLock` no-op):** cada handler emite sus eventos auditados (R07.1) y
  exige la confirmación.
- **Infraestructura (Postgres real):**
  - repositorio: ida y vuelta con sus lapsos;
  - restricciones: dos activos, dos planificados, solape y dos lapsos
    abiertos, cada una rechazada con SQL directo;
  - grupo `rls`: aislamiento de `school_years` y `terms` (R08.2);
  - `TermEditability` < 10 ms p95 con 1.000 años sembrados (grupo `perf`,
    NF03).
- **Feature (HTTP):** la pantalla (R06.1), 403 sin permiso (R06.4), 404 con
  el id de otro plantel (R08.1), dos planteles con su propio año activo
  (R08.3), el flujo completo crear → activar → cerrar los 3 lapsos → cerrar
  el año, y la reapertura con motivo.
- **Arquitectura:** `SchoolYearController` y `TermController` en
  `MIGRATED_CONTROLLERS`; el módulo no usa la `Infrastructure` de otros.

## Decisiones y alternativas

| Decisión | Alternativa descartada | Motivo |
|----------|------------------------|--------|
| Agregado `SchoolYear` + servicio de dominio para las reglas entre años | Agregado `AcademicCalendar` por plantel con todos sus años | Cada operación cargaría todos los años del plantel; las reglas entre años son pocas y se comprueban bien con los demás años como dato. |
| Bloqueo por plantel + restricciones de la base de datos (ADR 0007) | Solo validación en el código | Dos peticiones concurrentes pasarían ambas la validación (CAL-NF02). |
| `EXCLUDE … gist` para el solape | Un trigger que compruebe el solape | La restricción es declarativa, la entiende el planificador y no tiene condiciones de carrera; el trigger sí las tiene sin bloqueo. |
| Nombre del año calculado | Columna `name` | Cambia con las fechas (R03.4): guardarlo solo añade una fuente de inconsistencia. |
| `TermEditability` recibe el permiso ya resuelto | Que consulte los permisos a `IdentityAccess` | Mantiene el módulo independiente de Spatie y la consulta en una sola lectura indexada (NF03). |
| Permiso especial directo por persona, concedido por el Administrador | Un rol nuevo «Editor de lapsos cerrados» | Es una excepción individual y temporal, no un perfil; un rol más complicaría la pantalla de usuarios. |

## Riesgos y plan de rollback

- **`btree_gist` en producción.** Si el proveedor no permite crear la
  extensión con el rol de la app, la migración falla al desplegar. Antes del
  despliegue se comprueba con `SELECT * FROM pg_available_extensions`. Si no
  está disponible, el plan B es quitar la restricción de exclusión y dejar
  el solape solo bajo el `CalendarLock` (un cambio en el ADR 0007).
- **Cambio de permisos de los roles base.** La migración vuelve a sembrar
  los roles de cada plantel. Quitar al Administrador un permiso que nunca
  tuvo no afecta a nadie. El `down()` borra los dos permisos nuevos y sus
  asignaciones.
- **Rollback de las tablas.** `school_years` y `terms` son nuevas: el
  `down()` las borra. Mientras SPEC-011 no exista, ningún otro dato depende
  de ellas.
- **Contrato con SPEC-011.** Si 011 necesita más datos que `bool`, por
  ejemplo el motivo del bloqueo para mostrarlo, `TermEditability` se amplía
  con un DTO sin romper a quien ya la usa.

## Notas de implementación

Ajustes hechos al implementar, sin cambiar ningún criterio:

- **Sin eventos de dominio.** El `AggregateRoot` del kernel exige eventos con
  fecha (`occurredAt`), y este dominio no usa reloj (CAL-R04.3). Cada caso de
  uso arma su entrada de la bitácora con `CalendarAudit`, dentro de la misma
  transacción (ADR 0005).
- **Reabrir indica el lapso.** La ruta es `/lapsos/{term}/reabrir` y el
  dominio comprueba que es el último cerrado, así CAL-R04.7 se puede
  verificar y una petición vieja no reabre otro lapso.
- **`TermEditabilityQuery` niega por defecto.** Un año que no existe en el
  plantel activo (o de otro plantel, bajo la RLS) no admite cambios.
- **409 ante una regla rota en la base de datos.** El repositorio convierte
  las violaciones de los índices únicos (`23505`) y de la exclusión
  (`23P01`) en `Conflict`.
- **Permiso especial.** La regla «solo un Administrador» está en
  `RoleAssignmentPolicy::closedTermGradeEditing`. Al salir del plantel o al
  cerrar la cuenta se retiran los roles **y** los permisos directos
  (`RoleCatalog::revokeEverything`).
- **Sembrado de roles.** `RolePermissionSeeder` lee los permisos de
  `BaseRole` y crea todos los de `Permission`, incluido el que ningún rol
  trae.

### SPEC-010 (inscripción), 2026-10-28

- **Punto de extensión `SchoolYearGuard`** (ADR 0012), lo implementa
  Enrollment. `CloseSchoolYearHandler` y `DeleteSchoolYearHandler` lo
  consultan bajo el bloqueo del calendario:
  - el año no se cierra con estudiantes sin resultado de prosecución
    (CAL-R02.4, MAT-R16.2);
  - un año planificado con inscripciones no se borra (antes daba un error
    de clave foránea).

  `SchoolYearGuards` se enlaza en el contenedor; si no, ganaría el valor
  por omisión del constructor (ninguna guarda).
- **`SchoolYearLookup`** gana `startOf`, `nextOf` y `lastTermClosed`.
- **La pantalla «Año escolar»** recibe `pendingResults` (`PendingResultsQuery`).
  El diálogo de cierre muestra, por sección, quién no tiene resultado: los
  nombres solo con `ver-estudiantes`.
