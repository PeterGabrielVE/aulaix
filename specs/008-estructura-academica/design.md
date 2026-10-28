---
spec: 008-estructura-academica
estado: implementada
fecha: 2026-10-22
requisitos: requirements.md
depende-de: 003-catalogos, 004-identidad-global, 005-auditoria, 006-institucion, 007-anio-escolar
---

# Diseño — Estructura académica

## Resumen

Un módulo nuevo, `AcademicStructure`, con un agregado por año escolar:
`SchoolYearStructure`. Agrupa las secciones, el pensum de cada grado, las
asignaciones de docentes, los docentes guía y los coordinadores de ese año.
Así, las reglas que cruzan esas piezas viven en un solo lugar:

- una materia se asigna solo si está en el pensum;
- quitar una materia del pensum quita sus asignaciones;
- qué se puede cambiar según el estado del año.

Los grados son un enum del dominio (oficiales, sin tabla). Las escrituras
toman el bloqueo del calendario del plantel (ADR 0007), así la estructura no
cambia mientras el año se activa o se cierra.

Este diseño resuelve el ERD de F0-07 para la estructura: sus tablas son la
base de la inscripción (SPEC-010) y de las notas (SPEC-011).

## Módulo y capas

```
src/AcademicStructure/
├── Domain/
│   ├── Grade.php                       enum: 17 grados oficiales; level(), stage(), order(), capacityCap()
│   ├── Stage.php                       enum: maternal | preescolar | primaria | media_general (para el tope)
│   ├── SchoolYearStructure.php         agregado: secciones, pensum, coordinadores de un año
│   ├── Section.php                     entidad: grado, letra, cupo (+ motivo), guía, docentes por materia
│   ├── SectionLetter.php · Capacity.php    VOs
│   ├── CoordinatorAssignment.php       VO: cuenta, nivel, grados (vacío = todo el nivel)
│   ├── YearStatus.php                  enum: planned | active | closed (copia del calendario)
│   └── SchoolYearStructureRepository.php
├── Application/
│   ├── CreateSection/ · ChangeCapacity/ · RemoveSection/
│   ├── SetCurriculum/ · CopyStructure/
│   ├── AssignTeacher/ · SetGuide/ · AssignCoordinator/ · RemoveCoordinator/
│   ├── ShowStructure/ · MyAssignments/                      queries (A-08)
│   ├── SectionsOfYear/ · TeachingQuery/                     API pública para 010 y 011
│   ├── ActiveAssignmentBlocker.php     implementa IdentityAccess DeletionBlocker (EAC-R09.1)
│   ├── StructureAudit.php
│   └── Port/
│       ├── StructurePermissions.php    canManage() / canView()
│       ├── StaffDirectory.php          ¿miembro activo con tal rol?; candidatos (vía IdentityAccess)
│       └── RemovalGuard.php            punto de extensión: ¿tiene estudiantes o notas? (010, 011)
└── Infrastructure/
    ├── Persistence/                    modelos, repositorio, query services
    ├── GateStructurePermissions.php · IdentityAccessStaffDirectory.php
    └── AcademicStructureServiceProvider.php
```

Fuera de `src/`:

- **Controladores:** `app/Http/Controllers/StructureController.php`,
  `SectionController.php`, `AssignmentController.php` y
  `MyAssignmentsController.php`.
- **Páginas:** `resources/js/Pages/Structure/Index.vue` (año, grados,
  secciones, pensum, asignaciones) y `MyAssignments/Index.vue`, con las
  entradas «Estructura» y «Mis asignaciones» del menú.
- **Migraciones:**
  - las tablas de [Datos](#datos);
  - la re-siembra de `gestionar-estructura` y `ver-estructura`.

## Modelo de dominio

| Elemento | Tipo | Invariantes / responsabilidad |
|----------|------|-------------------------------|
| `Grade` | Enum | Los 17 grados oficiales (R01.1), cada uno con su nivel y su orden: `maternal_1..3`, `preescolar_1..3`, `primaria_1..6`, `media_1..5`. `stage()` da Maternal, Preescolar, Primaria o Media General; `capacityCap()` da 15, 25, 35 o 40 (R02.8). `ofLevels(list<level>)` limita a los niveles del plantel (R01.2). |
| `SectionLetter` | VO | Una sola letra A–Z, en mayúscula (R02.3). |
| `Capacity` | VO | Entero > 0 (R02.4). Si supera `capacityCap()` del grado, exige un motivo de hasta 500 caracteres (R02.8, R02.9). |
| `Section` | Entidad | Grado, letra, cupo y motivo, docente guía (cuenta o nada) y docentes por materia (como máximo uno por materia, R05.4). |
| `SchoolYearStructure` | Agregado | Lo identifica el id del año escolar; conoce su `YearStatus` y los grados del plantel. Reglas: letra única por grado (R02.2); pensum solo con materias del nivel del grado y sin repetir (R03.2, R03.3); asignar solo materias del pensum (R05.3); quitar una materia del pensum o una sección quita sus asignaciones (R02.7, R03.4); coordinador sobre niveles o grados del plantel, varios por nivel (R06.3, R06.6). Cambios según el estado (R07): planificado, todo; activo, todo salvo quitar lo que el `RemovalGuard` dice que está en uso; cerrado, nada. `copyFrom(SchoolYearStructure)`: solo si este año está planificado y vacío, y solo los grados que hoy ofrece el plantel (R04). |
| `CoordinatorAssignment` | VO | Cuenta, nivel y grados del nivel (vacío = el nivel entero). |
| `YearStatus` | Enum | Lo que el calendario dice del año al cargar la estructura. |

Las reglas que dependen de **personas** (miembro activo con rol Docente o
Coordinador, R05.2, R06.2, R06.4, R12.3) las comprueba el caso de uso con
`StaffDirectory` antes de llamar al agregado. Las que dependen del
**catálogo** (nivel de cada materia) llegan resueltas al agregado: el caso
de uso le pasa el nivel de cada materia, leído de `Catalogs`.

## Casos de uso (Application)

Todos los de escritura:

- comprueban `StructurePermissions::canManage()` (S-04, R10.1, NF01);
- toman el plantel de `CurrentTenant` (R12.2);
- toman el bloqueo del calendario del plantel
  (`AcademicCalendar\Application\CalendarLock`, ADR 0007);
- cargan la estructura del año, aplican el cambio, guardan y registran en la
  bitácora, en una transacción (R11.1).

| Caso de uso | Entrada | Criterios |
|-------------|---------|-----------|
| `CreateSectionHandler` | año, grado, letra, cupo, ¿motivo? | R01.2, R01.3, R02.1 – R02.4, R02.8, R02.9, R07.1, R07.2, R07.4 |
| `ChangeCapacityHandler` | sección, cupo, ¿motivo? | R02.5, R02.6 (vía `RemovalGuard::enrolledIn`), R02.8, R02.9 |
| `RemoveSectionHandler` | sección | R02.7, R07.3 |
| `SetCurriculumHandler` | año, grado, lista de materias | R03.1 – R03.4, R07.3 |
| `CopyStructureHandler` | año origen, año destino | R04.1 – R04.3 |
| `AssignTeacherHandler` | sección, materia, ¿cuenta? (nada = quitar) | R05.1 – R05.5, R05.7, R12.3 |
| `SetGuideHandler` | sección, ¿cuenta? | R06.1, R06.2, R06.5 |
| `AssignCoordinatorHandler` / `RemoveCoordinatorHandler` | año, cuenta, nivel, grados | R06.3 – R06.6 |
| `ShowStructureQuery` | año | R08.1, R08.5 (asignaciones cuya persona ya no es miembro activo) |
| `MyAssignmentsQuery` | cuenta | R08.2 |
| `SectionsOfYearQuery` (pública) | año | R08.3 |
| `TeachingQuery` (pública) | `teaches(cuenta, sección, materia)`, `sectionsOf(cuenta)` | R08.4, NF02 |
| `ActiveAssignmentBlocker` | cuenta | R09.1 |

Detalles:

- **R05.6: se conservan las asignaciones.** No hay nada que hacer al salir
  un docente: la estructura no escucha las membresías y sus asignaciones
  quedan. `ShowStructureQuery` las marca («ya no es miembro activo») con
  `StaffDirectory` (R08.5).
- **Directores y Coordinadores que dictan clases (R05.2).** Se aceptan si
  tienen además el rol Docente: la comprobación es por rol, no por «rol
  principal».
- **`RemovalGuard` es un punto de extensión etiquetado, como
  `DeletionBlocker`.** Pregunta `enrolledIn(sección)`,
  `hasStudents(sección)` y `hasGrades(sección, materia)`. Hoy no hay ningún
  implementador: todo «no está en uso». SPEC-010 y SPEC-011 registrarán los
  suyos y R02.6 y R07.3 se activan solos.
- **El bloqueador (R09.1)** recorre los planteles de la cuenta con
  `InstitutionScope` (id `int`, como en SPEC-009). En cada uno mira si la
  cuenta dicta o guía en el año activo.

## Puertos y adaptadores

| Puerto (interfaz) | Capa | Adaptador | Notas |
|-------------------|------|-----------|-------|
| `SchoolYearStructureRepository` | Domain | `EloquentSchoolYearStructureRepository` | Carga y guarda el año entero (secciones, pensum, asignaciones, coordinadores) en una transacción. |
| `StructurePermissions` | Application | `GateStructurePermissions` | `gestionar-estructura` / `ver-estructura`. |
| `StaffDirectory` | Application | `IdentityAccessStaffDirectory` | `isActiveWithRole(cuenta, rol)` y `candidates(rol)`, vía `IdentityAccess\Application\Members\MemberDirectory` (+ `activeMembersWithRole`). Se prueba con un doble en memoria. |
| `RemovalGuard` | Application | ninguno hoy (etiquetado; los aportan 010 y 011) | Evita depender de módulos que aún no existen. |
| `CalendarLock` | AcademicCalendar Application (existente) | `DatabaseCalendarLock` | El mismo bloqueo que el calendario. |
| `SchoolYearLookup` | AcademicCalendar Application (nuevo) | lee `school_years` en su módulo | `statusOf(año)`, `activeYear()`, `previousOf(año)`. |
| `Subjects` | Catalogs Application (nuevo) | lee `subjects` en su módulo | `levelOf(materia)`, `ofLevel(nivel)`. |
| `InstitutionSettingsQuery` | Tenancy Application (existente) | — | Niveles del plantel (R01.2, R01.3). |
| `DeletionBlocker`, `InstitutionScope` | IdentityAccess Application (existentes, con `int`) | `ActiveAssignmentBlocker` | R09.1. |

## Adaptadores de entrada (`app/`)

En el dominio del plantel, con `auth`, `active` y `verified`.

| Ruta | Middleware | Controlador | Caso de uso |
|------|------------|-------------|-------------|
| `GET /estructura?anio={id}` | `permission:ver-estructura` | `StructureController@show` | `ShowStructureQuery` (por defecto, el año activo o el planificado) |
| `POST /estructura/{year}/secciones` | `permission:gestionar-estructura`, `throttle:60,1` | `SectionController@store` | `CreateSection` |
| `PUT /estructura/secciones/{section}/cupo` · `DELETE /estructura/secciones/{section}` | ídem | `@capacity` / `@destroy` | `ChangeCapacity` / `RemoveSection` |
| `PUT /estructura/secciones/{section}/guia` | ídem | `AssignmentController@guide` | `SetGuide` |
| `PUT /estructura/secciones/{section}/materias/{subject}/docente` | ídem | `@teacher` | `AssignTeacher` |
| `PUT /estructura/{year}/pensum/{grade}` | ídem | `StructureController@curriculum` | `SetCurriculum` |
| `POST /estructura/{year}/copiar` | ídem | `@copy` | `CopyStructure` |
| `POST /estructura/{year}/coordinadores` · `DELETE /estructura/{year}/coordinadores/{assignment}` | ídem | `AssignmentController@coordinator` / `@removeCoordinator` | `AssignCoordinator` / `RemoveCoordinator` |
| `GET /mis-asignaciones` | (cualquier miembro) | `MyAssignmentsController` | `MyAssignmentsQuery` |

- `HandleInertiaRequests` comparte `can.viewStructure` y
  `can.manageStructure`.
- Las FormRequests solo comprueban la forma: listas, enteros, longitudes y
  el grado dentro del enum. Las reglas son del dominio (S-09).

## Datos

Todas con `belongsToInstitution()` y `RowLevelSecurity::enable()` (S-01).

```
sections
  id, institution_id, school_year_id → school_years (cascade),
  grade varchar(16) CHECK (17 valores), letter char(1) CHECK (letter ~ '^[A-Z]$'),
  capacity smallint CHECK (capacity > 0), capacity_reason varchar(500) NULL,
  guide_account_id NULL → users (null on delete), timestamps
  UNIQUE (institution_id, school_year_id, grade, letter)

curriculum_subjects                       -- pensum
  id, institution_id, school_year_id → school_years (cascade), grade varchar(16),
  subject_id → subjects
  UNIQUE (institution_id, school_year_id, grade, subject_id)

teaching_assignments
  id, institution_id, section_id → sections (cascade), subject_id → subjects,
  account_id NULL → users (null on delete), timestamps
  UNIQUE (section_id, subject_id)                                  -- R05.4
  INDEX (account_id, section_id, subject_id)                       -- R08.4, NF02

coordinator_assignments
  id, institution_id, school_year_id → school_years (cascade), account_id → users (cascade),
  level varchar(16) CHECK (inicial|primaria|media_general), grade varchar(16) NULL   -- NULL = todo el nivel
  UNIQUE (school_year_id, account_id, level, COALESCE(grade, ''))
```

- **`account_id` con `null on delete`.** Una cuenta eliminada (SPEC-004) no
  borra la historia: la asignación queda «sin docente». En el año activo la
  eliminación ya está bloqueada (R09.1).
- **Que la asignación sea de una materia del pensum** lo garantiza el
  agregado, que se guarda entero. No hay FK compuesta.
- **Permisos:** una migración de re-siembra añade, también en los planteles
  existentes:
  - `gestionar-estructura` para Administrador y Director (R10.2);
  - `ver-estructura` para Administrador, Director, Coordinador y Docente
    (R10.3).

## Patrones aplicados

| Patrón | Fuerza que resuelve (C-05) |
|--------|----------------------------|
| Agregado por año (`SchoolYearStructure`) | Las reglas cruzan sus piezas: asignar solo materias del pensum, quitar en cascada, copia, estado del año. Cargarlo entero (60 secciones × ~12 materias) es barato y deja una sola fuente de verdad. |
| Enum de dominio (`Grade`) | Los grados son oficiales e inmutables; con su nivel, etapa y tope, una tabla solo añadiría sincronización. |
| Value Object (`Capacity`, `SectionLetter`) | Tope por etapa y motivo obligatorio sobre el tope, en un solo tipo. |
| Punto de extensión (`RemovalGuard`) | R02.6 y R07.3 dependen de SPEC-010 y SPEC-011, que aún no existen. Estructura no debe conocerlas (dependencia invertida). |
| Puerto (`StaffDirectory`) | La comprobación de roles vive en IdentityAccess; el puerto la aísla y permite probar los casos de uso en memoria. |
| Bloqueo compartido (`CalendarLock`) | Sin él, una sección podría crearse en un año mientras otro gestor lo cierra (R07.4). |
| Query service (`ShowStructure`, `TeachingQuery`) | Lecturas sin hidratar el agregado (A-08); `teaches()` es una sola búsqueda por índice (NF02). |

## Modelo de amenazas (STRIDE)

| Amenaza | Vector | Mitigación | Verificado por |
|---------|--------|------------|----------------|
| **S**poofing | Un Docente se asigna materias o cambia cupos | `permission:gestionar-estructura` en la ruta y `canManage()` en el caso de uso | Tests feature de 403 y unit de handlers (R10.1, R10.4) |
| **S**poofing | Asignar como docente a quien no lo es, o a alguien de otro plantel | `StaffDirectory::isActiveWithRole` bajo la RLS de membresías del plantel abierto | Tests de R05.2, R06.2, R12.3 |
| **T**ampering | Cambiar la estructura de otro plantel enviando ids | RLS en todas las tablas; el plantel sale del subdominio | Tests de aislamiento (`rls`) y feature (R12.1, R12.2) |
| **T**ampering | Cambiar un año cerrado, o mientras se cierra | Regla del agregado (R07.4) y `CalendarLock` | Unit del agregado; integración con el bloqueo |
| **R**epudiation | «Yo no quité esa sección» | Bitácora de cada cambio, con anterior y nuevo | Tests de R11.1 |
| **I**nformation disclosure | Ver la estructura o las asignaciones de otro plantel | RLS; `ver-estructura` | Tests `rls` y 403 |
| **D**enial of service | Copias o pensums masivos repetidos | `throttle:60,1`; copia solo sobre un año vacío | Regla de rutas; test de R04.2 |
| **E**levation of privilege | Ser asignado como docente para cargar notas (SPEC-011) sin serlo | Solo los gestores asignan, y solo a quien tiene el rol Docente | Tests de R05.2 y R10.1 |

## Errores

| Excepción de dominio | HTTP | Mensaje al usuario (ejemplos) |
|----------------------|------|-------------------------------|
| `BusinessRuleViolation(s)` | 422 | «Ya hay una sección 1.er año A en este año escolar.» · «La sección es una sola letra de la A a la Z.» · «El cupo de Preescolar es de hasta 25: indica el motivo para superarlo.» · «Esa materia no es de este nivel.» · «La materia no está en el pensum del grado.» · «Esa persona no es docente activo del plantel.» · «Completa los niveles del plantel antes de crear secciones.» · «El año escolar está cerrado.» · «El año destino ya tiene estructura.» |
| `StructureInUse` (`Conflict`) | 409 | «La sección tiene estudiantes inscritos.» · «La materia ya tiene notas.» |
| `SectionNotFound`, `SchoolYearNotFound` (`NotFound`) | 404 | «No encontramos esa sección.» |
| `AuthorizationDenied` | 403 | «No tienes permiso para gestionar la estructura.» |

## Estrategia de pruebas

- **Dominio (unit):**
  - `Grade` (17 grados, niveles, etapas, topes), `SectionLetter`, `Capacity`
    (tope y motivo);
  - `SchoolYearStructure`: letra única, pensum por nivel, asignación solo
    del pensum, cascadas al quitar, estados del año, copia (vacío, niveles
    actuales) y coordinadores por nivel o grado, varios por nivel.
- **Aplicación (unit, en memoria):**
  - cada handler con `StaffDirectory`, `RemovalGuard` y `SchoolYearLookup`
    en memoria;
  - permisos denegados;
  - la bitácora dentro de la transacción;
  - un `RemovalGuard` que dice «en uso» (R02.6, R07.3);
  - el bloqueador.
- **Infraestructura (integración, Postgres):**
  - repositorio (año completo de ida y vuelta);
  - `TenantTableTest` y aislamiento de las cuatro tablas (`rls`);
  - índices únicos;
  - `teaches()` en menos de 10 ms y la pantalla de 60 secciones en menos de
    300 ms (`perf`);
  - el bloqueo compartido con el calendario.
- **Feature (HTTP):**
  - la pantalla por rol y los 403;
  - crear secciones, pensum, copia y asignaciones;
  - el aviso de la persona que ya no es miembro;
  - «Mis asignaciones»;
  - el bloqueo de eliminación de cuenta (IDG-R08.4).
- **Arquitectura:**
  - controladores en `MIGRATED_CONTROLLERS`;
  - `AcademicStructure` usa otros módulos solo por su `Application`.

## Decisiones y alternativas

| Decisión | Alternativa descartada | Motivo |
|----------|------------------------|--------|
| Un agregado por año escolar | Un agregado por sección y otro por pensum | Las reglas cruzan sección y pensum (asignar solo materias del pensum, cascada al quitar una materia); con agregados separados harían falta servicios de dominio y más concurrencia que resolver. |
| Grados como enum del dominio de `AcademicStructure` | Tabla de grados en `Catalogs` | Son 17, oficiales y fijos, con datos propios de la estructura (etapa, tope). Si otro módulo los necesita, se exponen por una consulta pública. |
| `RemovalGuard` como punto de extensión | Que estructura consulte tablas de inscripción y notas | Esas specs no existen; y estructura no debe conocerlas (A-06, dependencia invertida). |
| Reusar `CalendarLock` (ADR 0007) | Bloqueo propio de la estructura | El riesgo es justamente la carrera con el cierre del año; un solo bloqueo por plantel la evita. |
| Coordinador por fila (nivel y grado opcional) | Lista JSON de grados | Consultar «quién coordina 3.er grado» y el índice único son directos. |

## Cambios en otras specs y módulos

- **SPEC-002:** IAM-R11.1 suma `gestionar-estructura` (Administrador,
  Director) y `ver-estructura` (Administrador, Director, Coordinador,
  Docente).
- **SPEC-003 / Catalogs:** consulta pública `Subjects` (`levelOf`,
  `ofLevel`).
- **SPEC-004 / IdentityAccess:**
  - `MemberDirectory::activeMembersWithRole(rol)` (candidatos);
  - IDG-R08.4 lo cumple `ActiveAssignmentBlocker`.
- **SPEC-007 / AcademicCalendar:** consulta pública `SchoolYearLookup`
  (`statusOf`, `activeYear`, `previousOf`).

## Riesgos y plan de rollback

- **ERD que heredan 010 y 011.** `sections`, `curriculum_subjects` y
  `teaching_assignments` serán la clave de la inscripción y las notas.
  Cambiarlas después obliga a migrar datos; por eso este diseño las fija
  con índices únicos y nombres estables.
- **Bloqueo compartido.** La estructura espera mientras el calendario
  escribe, y viceversa. Las escrituras son cortas, pero hay que vigilarlo.
- **`RemovalGuard` vacío hasta 010/011.** Mientras tanto, en el año activo
  se puede quitar cualquier sección o materia. Es correcto, porque aún no
  hay estudiantes ni notas, pero 010 y 011 deben registrar su guardia.
- **Migraciones:**
  - todas son tablas nuevas, sin migración de datos;
  - el `down()` las borra en orden inverso y quita los permisos;
  - se prueban `fresh --seed` dos veces y el rollback.

## Notas de implementación

Ajustes hechos al implementar el PR A, sin cambiar ningún criterio:

- **17 grados, no 14.** Con Maternal I a III los grados oficiales son 17
  (3 + 3 + 6 + 5). Se corrigió en este diseño y en las tareas.
- **`RemovalGuard` más simple.** Pregunta dos cosas: `enrolledIn(sección)`,
  y de ahí sale si la sección está en uso, y
  `subjectsInUse(año, grado, materias)`. El agregador `RemovalGuards` junta
  las respuestas de los módulos registrados bajo `RemovalGuard::TAG`.
- **`StructureLoader`.** Los casos de uso cargan la estructura con el estado
  del año (`SchoolYearLookup`) y los niveles del plantel
  (`InstitutionSettingsQuery`) por un mismo servicio. Un año que no existe
  da `SchoolYearNotFound` y una sección ajena da `SectionNotFound` (404).
- **`SchoolYearLookup` vive en la capa Application del calendario**, sobre
  su repositorio: no hace falta un adaptador nuevo.
- **`Subjects` de Catalogs** expone `ofLevel(nivel)` y `levelsOf(materias)`
  (en lote, para validar un pensum entero con una consulta).
- **`ShowStructureQuery` carga el agregado.** No usa un query service SQL:
  60 secciones caben de sobra en el umbral (test `perf`). Si SPEC-010 lo
  hace crecer, se reemplaza sin tocar la interfaz.
- **Parámetros de las rutas.** El año a mostrar va en `?anio=`, y el año
  desde el que copiar, si no es el anterior, en `desde`.

Ajustes hechos al implementar el PR B, también sin cambiar criterios:

- **`MemberDirectory` de IdentityAccess** ganó `activeMembersWithRole(rol)` y
  `accountNames(ids)`. El adaptador `IdentityAccessStaffDirectory` los usa
  para el puerto `StaffDirectory` (`isActiveWithRole`, `candidates`,
  `describe`), sin tocar el modelo de cuentas (A-06).
- **Puerto `AssignmentDirectory`** (`teaches`, `sectionIdsOf`), con un
  adaptador Eloquent. Sobre él se apoyan `TeachingQuery`, que se expone a
  otros módulos, y `ActiveAssignmentBlocker`.
- **Asignaciones huérfanas.** Incluyen las de docente guía (`subjectId`
  nulo), no solo las de materia. La pantalla las lista y muestra a la
  persona como «(inactivo)» en su selector, hasta que se reasigne.
- **Rutas.** Docente de una materia:
  `PUT /estructura/secciones/{sección}/materias/{materia}/docente`. Guía:
  `PUT /estructura/secciones/{sección}/guia`. En los dos, `account_id` nulo
  deja el puesto vacío. Coordinadores: `POST /estructura/{año}/coordinadores`
  y `DELETE /estructura/{año}/coordinadores/{cuenta}/{nivel}`. «Mis
  asignaciones» está en `GET /mis-asignaciones`.
- **Entrada «Mis asignaciones» del menú.** Se muestra a quien tiene el rol
  Docente en el plantel (`auth.isTeacher`, compartido por Inertia). La
  página está abierta a cualquier miembro, y cada uno ve solo lo suyo.

### SPEC-010 (inscripción), 2026-10-28

- **EAC-R02.6 y EAC-R07.3 se activan.** Enrollment registra
  `EnrollmentRemovalGuard` bajo `RemovalGuard::TAG` (ADR 0012).
- **`RemovalGuard` gana `hasEnrollments()`.** Una sección con cualquier
  inscripción, vigente, terminada o anulada, no se quita en ningún estado
  del año (MAT-R10.1); la clave foránea es `RESTRICT`.
  `SchoolYearStructure::removeSection()` recibe ese dato aparte de
  `inUse`, así que la regla de SPEC-008 (en un año planificado se puede
  quitar lo que está en uso) no cambia y sus tests tampoco.
- **Consultas públicas nuevas:** `GradesQuery` (los 17 grados con su nivel y
  nombre) y `CurriculumQuery` (el pensum de un grado y año).
