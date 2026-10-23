---
spec: 013-layout-menu
estado: aprobada
fecha: 2026-10-23
aprobado: 2026-10-23
requisitos: requirements.md
depende-de: 002-identidad-acceso, 004-identidad-global, 005-auditoria, 006-institucion, 007-anio-escolar, 008-estructura-academica, 009-estudiantes
adr: 0009-vistas-compuestas
---

# Diseño — Layout, menú e inicio por rol

## Resumen

Un módulo nuevo, `Workspace`, sin tablas ni escrituras (ADR 0009). Decide qué ve cada persona: las entradas del menú, la barra
superior y los bloques de su inicio. Sus reglas son de dominio puro, sobre
las capacidades de la persona (permisos, roles y vínculo de representante).
Los datos los pide a la capa Application de cada módulo dueño, a través de
puertos con un adaptador por módulo (A-06).

Cada módulo dueño gana una consulta de resumen (A-08) que calcula sus
cifras y pendientes con unas pocas consultas SQL agregadas.

En `app/`:
- el layout se reescribe con barra lateral, barra superior y panel móvil;
- un catálogo traduce cada entrada a su ruta, su texto y su patrón de
  pantalla actual;
- las seis pantallas de inicio (IAM-R12) comparten un componente de
  bloques.

Fuera del menú y el inicio, dos cambios pequeños en otros módulos:
- `Tenancy`: la dependencia de los planteles públicos (INS-R02.4, R02.5);
- `Students` e `IdentityAccess`: el rol Representante al vincular a un
  miembro (EST-R08.7).

## Módulo y capas

```
src/Workspace/                         (nuevo; sin tablas: ADR 0009)
├── Domain/
│   ├── Capabilities.php               permisos, roles, rol principal, ¿representante?
│   ├── MenuGroup.php                  enum: Academic, Students, Institution
│   ├── MenuEntry.php                  enum: once entradas, con grupo, orden y condición
│   ├── Navigation.php                 servicio: entradas visibles, agrupadas
│   ├── HomeKind.php                   enum: admin, director, coordinator, teacher, guardian, student
│   ├── HomeBlock.php                  enum: los bloques posibles del inicio
│   └── HomePlan.php                   servicio: qué bloques lleva un inicio y en qué orden
├── Application/
│   ├── Port/
│   │   ├── ViewerCapabilities.php     capacidades de la persona en el plantel abierto
│   │   ├── CalendarSource.php         año activo, lapso abierto, ¿año planificado?
│   │   ├── StructureSource.php        resumen de la estructura; grados de un coordinador; mis clases
│   │   ├── StudentSource.php          cifras de estudiantes; representados y sus correcciones
│   │   ├── StaffSource.php            personal por rol; invitaciones
│   │   ├── InstitutionSource.php      datos obligatorios que faltan
│   │   └── ActivitySource.php         bitácora reciente
│   ├── ShowLayout/
│   │   ├── ShowLayoutQuery.php        → LayoutView (menú, año y lapso, rol, ¿cambiar de plantel?)
│   │   └── LayoutView.php
│   └── ShowHome/
│       ├── ShowHomeQuery.php          → HomeView (bloques con sus datos)
│       └── HomeView.php
└── Infrastructure/
    ├── GateViewerCapabilities.php     Gate (permisos) + IdentityAccess (roles) + Students (vínculo)
    ├── AcademicCalendarSource.php     → CalendarContextQuery
    ├── AcademicStructureSource.php    → StructureOverviewQuery, CoordinatedGradesQuery, MyAssignmentsQuery
    ├── StudentsSource.php             → StudentOverviewQuery, GuardianStudentsQuery, GuardianCorrectionsQuery
    ├── IdentityAccessStaffSource.php  → StaffOverviewQuery
    ├── TenancyInstitutionSource.php   → InstitutionGapsQuery
    ├── AuditActivitySource.php        → ListAuditEntriesHandler
    └── WorkspaceServiceProvider.php
```

Consultas nuevas en los módulos dueños (capa Application, con su adaptador
SQL en su Infrastructure):

| Módulo | Consulta | Devuelve | Permiso que comprueba |
|--------|----------|----------|-----------------------|
| `AcademicCalendar` | `CalendarContextQuery` | `CalendarContext`: año activo (id, nombre), número del lapso abierto, ¿hay año planificado? | ninguno (dato del plantel para todo miembro) |
| `AcademicStructure` | `StructureOverviewQuery(?grados)` | `StructureOverview` del año activo: secciones, suma de cupos, grados ofrecidos sin secciones, secciones sin guía, pares sección-materia sin docente, asignaciones de personas no activas | `ver-estructura` |
| `AcademicStructure` | `CoordinatedGradesQuery(cuenta)` | grados coordinados en el año activo (vacío si ninguno) | `ver-estructura` |
| `Students` | `StudentOverviewQuery` | estudiantes activos; traslados recibidos pendientes y correcciones sin atender (estos dos, solo con `gestionar-estudiantes`) | `ver-estudiantes` |
| `IdentityAccess` | `StaffOverviewQuery` | miembros activos por rol base; invitaciones pendientes y vencidas | `gestionar-usuarios` |
| `IdentityAccess` | `MembershipContextQuery(cuenta)` | roles en el plantel abierto, rol principal (IAM-R12.2), número de planteles con membresía activa | la propia cuenta |
| `Tenancy` | `InstitutionGapsQuery` | datos obligatorios que faltan: `levels`, `dea_code`, `director_signature`, `rif` (privado), `dependency` (público) | `ver-plantel` |

Se reutilizan sin cambios: `MyAssignmentsQuery` (EAC-R08.2),
`GuardianStudentsQuery`, `GuardianCorrectionsQuery` e `IsGuardianQuery`
(EST-R08), y `ListAuditEntriesHandler` (AUD).

## Modelo de dominio

| Elemento | Tipo | Invariantes / responsabilidad |
|----------|------|-------------------------------|
| `Capabilities` | Value Object | Permisos (nombres), roles (nombres), rol principal (o ninguno) y si es representante vinculado. Inmutable. `has(permiso)`, `hasRole(rol)`, `isGuardian()`. |
| `MenuEntry` | Enum | Las once entradas de la tabla de NAV-R03, con su grupo, su orden y su condición: un permiso, el rol Docente, el vínculo de representante o «miembro». Es la única definición del menú. |
| `MenuGroup` | Enum | Académico, Estudiantes y Plantel, en ese orden. |
| `Navigation` | Servicio de dominio | `for(Capabilities)`: entradas visibles (NAV-R03.1), agrupadas y en orden, sin grupos vacíos (NAV-R03.2). |
| `HomeKind` | Enum | Las seis pantallas de inicio. Mismos valores que `HomeScreen` de IdentityAccess, que no se importa (A-06). |
| `HomeBlock` | Enum | `Pending`, `Indicators`, `MyClasses`, `MyStudents`, `RecentActivity`, `Shortcuts`, `StudentNotice`. |
| `HomePlan` | Servicio de dominio | `for(HomeKind, Capabilities)`: los bloques del inicio en el orden de NAV-R04.1. Solo incluye un bloque si su condición coincide con la de la entrada de su pantalla de origen (NAV-R04.2, R10.2). Añade `MyClasses` y `MyStudents` desde otros roles (NAV-R04.6, R04.7) sin repetir (NAV-R04.8). `RecentActivity` y los pendientes operativos solo van al inicio `admin` (NAV-R05.5 – R05.7). |

**Alcance de los indicadores.** Lo decide `HomePlan`: `admin` y `director`
ven el plantel; `coordinator` ve sus grados coordinados (NAV-R06). Los
grados sin secciones no son pendiente del coordinador (NAV-R06.2).

## Casos de uso (Application)

| Caso de uso | Entrada | Salida | Criterios |
|-------------|---------|--------|-----------|
| `ShowLayoutQuery` | id de cuenta | `LayoutView`: grupos con entradas (claves de `MenuEntry`), año y lapso, nombre del rol principal, ¿más de un plantel? | NAV-R02.2 – R02.6, R02.8, R03.1 – R03.4 |
| `ShowHomeQuery` | id de cuenta, `HomeKind` | `HomeView`: los bloques de `HomePlan`, cada uno con sus datos. Solo llama a los puertos de los bloques del plan, y no envía lo que no está en él. | NAV-R04.1 – R04.9, R05.1 – R05.7, R06.1 – R06.4, R07.1 – R07.3, R08.1 – R08.3, R09.1, R10.1 – R10.3 |
| `GrantRoleHandler` (IdentityAccess) | actor, cuenta, rol | — | EST-R08.7 |
| `UpdateInstitutionHandler` (Tenancy, ampliado) | `dependency` en el comando | — | INS-R02.4, R02.5 |

Criterios que resuelve solo la presentación (`app/` y Vue), sin caso de
uso:
- NAV-R01.1 – R01.5: layout, panel móvil, entrada actual y mensajes;
- NAV-R02.1: nombre y logo, ya compartidos por `institution`;
- NAV-R02.7: menú de la persona.

Los no funcionales se cubren así:
- NAV-NF01: tests de arquitectura (Workspace sin escrituras) y
  comprobaciones de permiso en las consultas de resumen;
- NAV-NF02 y NF03: tests `perf`;
- NAV-NF04 y NF05: tests de navegador.

Notas de los casos de uso:

- **`ShowHomeQuery` y los pendientes.** Construye cada pendiente con su
  clave, su número de casos y su destino. Si el número es 0, no lo
  incluye. Si no queda ninguno, el bloque `Pending` llega vacío y la página
  dice «No hay pendientes» (NAV-R04.4). El destino es una clave de
  `MenuEntry`, de modo que el enlace sale del mismo catálogo que el menú
  (NAV-R04.3).
- **Sin año activo.** `ShowHomeQuery` sustituye los pendientes de
  estructura y los indicadores del año:
  - dirección: el pendiente «No hay año escolar activo», con
    `hasPlannedYear` (NAV-R05.2);
  - coordinador y docente: un aviso (NAV-R06.4, R07.3).
- **`GrantRoleHandler`.** Da un rol a un miembro activo del plantel abierto,
  con `RoleAssignmentPolicy` (nadie escala a Administrador, IAM-R10). Si ya
  lo tiene, no hace nada. Registra `identity.membership.roles_changed`, como
  `UpdateMemberHandler`.
- **Rol al vincular a un miembro.** `InviteGuardianHandler`, en la rama del
  miembro existente, llama al puerto `GuardianInvitations::grantGuardianRole`
  antes de vincular la cuenta. El adaptador invoca `GrantRoleHandler`.
- **Dependencia del plantel.** El agregado `Institution` de Tenancy guarda
  `Dependency` (enum `nacional`, `estadal`, `municipal`) solo si es
  `publica`. Si se indica para otro tipo, la rechaza con una
  `BusinessRuleViolation` en `dependency`. Cambiar el tipo a privada o
  subvencionada la borra (INS-R02.5). El cambio entra en el registro de
  cambios de la bitácora que ya hace `UpdateInstitutionHandler` (INS-R06).

## Puertos y adaptadores

| Puerto (interfaz) | Capa | Adaptador | Notas |
|-------------------|------|-----------|-------|
| `ViewerCapabilities` | Workspace Application | `GateViewerCapabilities` | Permisos con `Gate::allows` sobre los permisos que usa `MenuEntry` (Spatie los cachea). Roles, rol principal y número de planteles con `MembershipContextQuery`; vínculo con `IsGuardianQuery`. Se memoriza por petición: layout e inicio la calculan una vez. |
| `CalendarSource` | Workspace Application | `AcademicCalendarSource` | → `CalendarContextQuery`. |
| `StructureSource` | Workspace Application | `AcademicStructureSource` | → `StructureOverviewQuery`, `CoordinatedGradesQuery`, `MyAssignmentsQuery`. |
| `StudentSource` | Workspace Application | `StudentsSource` | → `StudentOverviewQuery`, `GuardianStudentsQuery`, `GuardianCorrectionsQuery` (solo las pendientes). |
| `StaffSource` | Workspace Application | `IdentityAccessStaffSource` | → `StaffOverviewQuery`. |
| `InstitutionSource` | Workspace Application | `TenancyInstitutionSource` | → `InstitutionGapsQuery`. |
| `ActivitySource` | Workspace Application | `AuditActivitySource` | → `ListAuditEntriesHandler`, primera página, las 10 primeras. |
| `CalendarContexts` | AcademicCalendar Application | `SqlCalendarContexts` | Una consulta: año activo con su lapso abierto, y existencia de un año planificado. |
| `StructureStatistics` | AcademicStructure Application | `SqlStructureStatistics` | Consultas agregadas sobre `sections`, `curriculum_subjects`, `teaching_assignments` y `coordinator_assignments`. Para las personas no activas reutiliza `StaffDirectory::describe`. |
| `StudentStatistics` | Students Application | `SqlStudentStatistics` | `count` sobre `students`, `correction_requests` y las solicitudes de traslado recibidas (ADR 0008). |
| `StaffStatistics` | IdentityAccess Application | `SqlStaffStatistics` | Miembros activos por rol con `model_has_roles` del *team*; invitaciones por estado y vencimiento. |
| `InstitutionGaps` | Tenancy Application | `SqlInstitutionGaps` | Una fila de `institutions` y la existencia de la firma `director`. |

**Por qué un puerto por fuente.** `ShowHomeQuery` tiene la lógica de
NAV-R04 a R09. Con adaptadores en memoria se prueba cada rol, sin base de
datos y en milisegundos. Las consultas finales de los otros módulos no se
pueden sustituir sin una interfaz.

## Adaptadores de entrada (`app/`)

- **`HandleInertiaRequests`.** Comparte `layout`, de forma diferida
  (closure), con `ShowLayoutQuery`:

  ```
  layout: {
    navigation: [{ group: 'academic'|null, entries: [{ key, label, href, active }] }],
    calendar: { year: '2026-2027' | null, term: 1 | null },
    person: { name, primaryRole: 'Director' | null },
    canSwitchInstitution: bool
  }
  ```

  - `auth.isGuardian` y `auth.isTeacher` desaparecen: los sustituye
    `navigation`.
  - `auth.can` se mantiene, porque lo usan pantallas (p. ej. `Users/Edit`).
  - `switchInstitutionUrl` solo se envía si `canSwitchInstitution` es
    verdadero (NAV-R02.5, R02.6).
- **`app/Http/Navigation/MenuCatalog.php`.** Traduce cada `MenuEntry` a su
  presentación:
  - nombre de ruta;
  - texto;
  - descripción del acceso directo (NAV-R04.9);
  - patrón de «pantalla actual», p. ej. `students.*` para la ficha
    (NAV-R01.4).

  Sin reglas: si una clave no está en el catálogo, falla un test.
- **`HomeController`.** Las seis acciones de IAM-R12 siguen igual y con el
  mismo componente (`Home/Admin`…). Cada una llama a `ShowHomeQuery` con su
  `HomeKind` y envía `home`. El middleware de IAM-R12.4 no cambia.
- **Vue.**
  - `Layouts/AuthenticatedLayout.vue` se reescribe:
    - barra lateral desde 1024 px;
    - panel `dialog` modal por debajo, que retiene el foco y se cierra con
      Escape, con el botón o al navegar;
    - enlace «Saltar al contenido»;
    - regiones `header`, `nav` y `main`;
    - `aria-current="page"` en la entrada actual.
  - Componentes nuevos en `Components/Layout/` (`SideNav`, `TopBar`,
    `MobileNav`) y `Components/Home/` (un componente por bloque).
  - `Components/HomeScreen.vue` pasa a dibujar `home.blocks`.
  - Sin dependencias nuevas de UI: la retención del foco es un composable
    propio, `useFocusTrap`.
- **Plantel.**
  - `InstitutionRequest` acepta `dependency`, nulo o uno de los tres
    valores (`Rule::in`).
  - `Institution/Show.vue` muestra el selector solo si el tipo es
    `publica`.

## Datos

- **Migración nueva:** `add_dependency_to_institutions`.
  - Columna `dependency varchar(16) null`.
  - Restricción `CHECK (dependency IS NULL OR (type = 'publica' AND
    dependency IN ('nacional', 'estadal', 'municipal')))`.
  - `down`: quita la restricción y la columna.
  - `institutions` ya tiene su política de RLS (TEN). No hay tablas nuevas.
- **Índices.** Los resúmenes usan los índices existentes:
  - `students (institution_id, status, …)`;
  - `correction_requests (institution_id, status, …)`;
  - `transfer_requests … WHERE status = 'pending'`;
  - los de `sections` y asignaciones por `school_year_id`.

  El test `perf` (NAV-NF02) decide si hace falta alguno más, en su propia
  migración.
- **Sin caché** de año y lapso: ver [Decisiones](#decisiones-y-alternativas).

## Patrones aplicados

| Patrón | Fuerza que resuelve (C-05) |
|--------|----------------------------|
| Servicio de dominio (`Navigation`, `HomePlan`) | La regla «se ve si puede abrir su pantalla» (NAV-R03.1, R04.2, R10.2) vive en un solo sitio, sin framework, y se prueba por rol. Menú, accesos directos y bloques no pueden divergir. |
| Adapter (un adaptador por módulo fuente) | Workspace no conoce las clases de los otros módulos. Cada adaptador traduce su consulta a un DTO de Workspace (A-06), igual que `IdentityAccessStaffDirectory`. |
| Query service (A-08) en cada módulo dueño | Las cifras salen de `count`/`group by` agregados, sin hidratar agregados de 2000 estudiantes (NAV-NF02). |
| Mapper (`MenuCatalog`) | Separa qué entrada ve cada persona (dominio) de su ruta y su texto (presentación). |

## Modelo de amenazas (STRIDE)

| Amenaza | Vector | Mitigación | Verificado por |
|---------|--------|------------|----------------|
| **S**poofing | Abrir el inicio de otro rol o de otro plantel | Sesión del plantel y tenant del subdominio (S-02). El middleware de IAM-R12.4 no cambia. | `RoleHomeTest` (sin cambios, Q-03) |
| **T**ampering | Enviar una dependencia inválida o para un plantel privado; forzar otro rol al vincular a un representante | `Rule::in` y el invariante del agregado, más la restricción `CHECK`. El rol lo fija el código (`Representante`), no la petición, y pasa por `RoleAssignmentPolicy`. | `InstitutionDependencyTest`; `GuardianRoleGrantTest`; test de la restricción |
| **R**epudiation | Cambiar la dependencia o dar el rol sin rastro | `identity.membership.roles_changed` y el cambio del plantel (INS-R06) en la bitácora, en la misma transacción | los mismos tests, con `assertAudited` |
| **I**nformation disclosure | Datos de un bloque sin permiso, ocultos solo en el navegador; cifras de otro plantel; nombres o salud de estudiantes en el inicio | `HomePlan` no incluye el bloque y `ShowHomeQuery` no llama a su puerto (NAV-R10.2). Cada consulta de resumen vuelve a comprobar su permiso (S-04). Todas leen tablas con RLS (S-01). Los DTO solo llevan cifras y claves, salvo los representados propios (NAV-R10.3, S-10). | `HomeVisibilityTest` (props ausentes por rol); `WorkspaceIsolationTest` (dos planteles, `--group=rls`); unit de `HomePlan` |
| **D**enial of service | El inicio con planteles grandes; consultas en cada página | Consultas agregadas con índices. El layout se calcula una vez por petición y de forma diferida. | `perf`: NAV-NF02 (< 300 ms p95) y NAV-NF03 (≤ 5 consultas) |
| **E**levation of privilege | Una entrada visible que lleva a una pantalla prohibida, o al revés; usar el menú como única protección | Las rutas conservan su middleware (NAV-R03.5). Un test barre cada entrada con cada rol base y con el vínculo, y compara visibilidad con la respuesta HTTP (NAV-R03.3). | `NavigationRouteParityTest` |

## Errores

No hay excepciones de dominio nuevas. Los dos cambios de escritura usan las
que ya existen:

| Excepción de dominio | HTTP | Mensaje al usuario |
|----------------------|------|--------------------|
| `BusinessRuleViolations` (`dependency`) | 422 | «La dependencia solo aplica a planteles públicos.» / «Elige nacional, estadal o municipal.» |
| `BusinessRuleViolations` (política de roles, al vincular) | 422 | Los mensajes actuales de `RoleAssignmentPolicy` |
| Permiso que falta en una consulta de resumen | 403 | No debería ocurrir: `HomePlan` no la pide. Si ocurre, es un error de diseño y lo detecta un test. |

## Estrategia de pruebas

- **Dominio (unit, `tests/Unit/Workspace/Domain`):**
  - `Navigation`: cada rol base, roles combinados, vínculo de representante
    y sin rol;
  - `HomePlan`: bloques y orden por `HomeKind`, extras sin repetir, y
    bloques sin permiso ausentes;
  - `Capabilities`.
- **Aplicación (unit, adaptadores en memoria, `tests/Fixtures/Workspace`):**
  - `ShowHomeQuery`: pendientes con 0 casos omitidos, «No hay pendientes»,
    sin año activo, coordinador sin grados, administrador frente a
    director;
  - `ShowLayoutQuery`.
  - Además, `GrantRoleHandler` y el agregado `Institution` con la
    dependencia.
- **Infraestructura (integración, PostgreSQL):**
  - cada consulta de resumen nueva contra datos sembrados: cifras exactas y
    filtro por grados;
  - `WorkspaceIsolationTest` (`--group=rls`): dos planteles con datos y
    cifras solo del abierto;
  - `perf`: inicio de dirección con 2000 estudiantes, 60 secciones y 100
    miembros (NAV-NF02), y número de consultas del layout (NAV-NF03).
- **Feature (HTTP):**
  - `LayoutPropsTest`: barra superior con y sin año, lapso, rol, un plantel
    frente a varios, y un cambio de año visible en la siguiente petición;
  - `NavigationRouteParityTest`;
  - `HomeVisibilityTest`: props de cada inicio, extras de varios roles y
    representados sin salud;
  - los tests de dependencia y de rol al vincular.
- **Navegador (`tests/Browser`, Pest con Playwright, grupo `browser`):**
  - panel móvil: abrir, Escape, foco retenido y devuelto, y cierre al
    navegar (NAV-R01.2, R01.3);
  - entrada actual (NAV-R01.4);
  - `assertNoAccessibilityIssues` (axe, AA) sobre el layout y los seis
    inicios, a 1280 px y a 360 px (NAV-NF04);
  - sin desplazamiento horizontal a 360 px (NAV-NF05).

## Decisiones y alternativas

| Decisión | Alternativa descartada | Motivo |
|----------|------------------------|--------|
| Módulo `Workspace` sin tablas, con reglas en su dominio (ADR 0009) | Componer el inicio en los controladores de `app/` | Las reglas de visibilidad (NAV-R03, R04, R10.2) son reglas de producto que deben probarse por rol sin HTTP. En un controlador quedarían sin tests de arquitectura y mezcladas con la presentación (A-05). |
| Un puerto por módulo fuente | Que `ShowHomeQuery` dependa de las consultas concretas de los otros módulos | Son clases `final`: sin interfaz, los tests de aplicación necesitarían base de datos. |
| Año y lapso **sin caché**: una consulta indexada por petición | Caché por plantel (Redis o sesión), invalidada al activar o cerrar un año o un lapso | La consulta cuesta menos de 1 ms y cabe en NAV-NF03. La caché exige claves por plantel e invalidación en cinco casos de uso del calendario; un fallo dejaría la barra mal (NAV-R02.8). Se reconsidera si el test `perf` la pide. |
| Una consulta de resumen por módulo, sin servicio central de alertas | `DashboardAlertsService` que lea todas las tablas | Un servicio que lee tablas de varios módulos rompe A-06 y la propiedad de los datos. Cada módulo agrupa sus propias cifras en una consulta (nota de diseño de los requisitos). |
| Menú enviado por el servidor (`layout.navigation`) | Que el frontend decida con `auth.can` | Una sola definición (`MenuEntry`), que también usan los accesos directos y el test de paridad (NAV-R03.3). |
| Pest con Playwright para el navegador (`pestphp/pest-plugin-browser`, `playwright` en npm) | axe en Vitest con jsdom; Dusk | Decidido en los requisitos. jsdom no calcula contraste ni foco reales. Dusk necesita ChromeDriver y no trae axe. El plugin de Pest trae `assertNoAccessibilityIssues`. |
| Retención del foco propia (`useFocusTrap`) | `focus-trap` o `@headlessui/vue` | Es un solo componente. Se evita una dependencia de producción nueva. |

## Cambios en otras specs y módulos

- **SPEC-006 (Tenancy):** INS-R02.4 y R02.5 en `requirements.md`; agregado,
  comando, formulario y migración de `dependency`; `InstitutionGapsQuery`.
- **SPEC-009 (Students):** EST-R08.7; `GuardianInvitations::grantGuardianRole`;
  `StudentOverviewQuery`.
- **SPEC-004 (IdentityAccess):** `GrantRoleHandler`, `StaffOverviewQuery` y
  `MembershipContextQuery`.
- **SPEC-007 y SPEC-008:** `CalendarContextQuery`, `StructureOverviewQuery` y
  `CoordinatedGradesQuery`.
- **Infraestructura de pruebas:**
  - `pestphp/pest-plugin-browser` y `playwright` como dependencias de
    desarrollo;
  - Chromium en la imagen de desarrollo (`npx playwright install
    --with-deps chromium`);
  - un trabajo `browser` en CI.

## Riesgos y plan de rollback

- **Subdominios en el navegador de pruebas.** El tenant sale del subdominio
  (S-02). Los tests de navegador usarán `<plantel>.localhost`, que Chromium
  resuelve a la máquina local, con `APP_DOMAIN=localhost` en ese entorno.
  Si el servidor del plugin no lo admite, se hará un paso previo antes del
  resto: una prueba de concepto en la primera tarea de la fase de
  navegador.
- **Imagen de desarrollo en Alpine.** `docker/php/Dockerfile` usa
  `php:8.4-fpm-alpine`, y Playwright no da soporte oficial a Alpine (musl).
  El spike de la fase de navegador elige una de dos salidas:
  - un servicio `browser` aparte en `compose`, sobre una imagen Debian con
    PHP y Chromium, que ejecuta solo el grupo `browser`;
  - una etapa Debian en el Dockerfile de desarrollo.

  En los dos casos Chromium queda fuera de producción: `Dockerfile.prod` es
  multietapa y no lo instala (condición del visto bueno, 2026-10-23).
- **Imagen y CI más pesadas.** Chromium suma unos 300 MB a la imagen de
  desarrollo y unos minutos a CI. Mitigación: el grupo `browser` corre en
  un trabajo aparte y en paralelo, y queda fuera de la ejecución local por
  defecto.
- **Reescritura del layout.** Cambia todas las pantallas a la vez.
  Mitigación: cambian el layout y los inicios, no las páginas. Los tests
  feature actuales no dependen del markup, y los de navegador cubren el
  layout. Rollback: revertir el PR del layout; los datos no cambian.
- **Deriva entre `MenuEntry` y los permisos reales** (los nombres de
  permiso se repiten como texto, porque Workspace no importa
  IdentityAccess). Mitigación: `NavigationRouteParityTest` falla si una
  entrada y su ruta discrepan.
- **Migración de `dependency`.** Es aditiva y nula, y los planteles
  existentes quedan sin dependencia (aparece como pendiente). El rollback
  (`down`) quita la columna. Se prueba con `migrate:fresh --seed` dos veces
  y con el rollback.
- **Rol al vincular.** Dar un rol es un cambio de permisos. Solo se da
  `Representante`, que no lleva permisos de gestión, por la política
  existente y con bitácora. Rollback: revertir el PR; los roles ya dados se
  quitan desde «Usuarios».

## Notas de implementación

### PR B

- **Planteles de la cuenta, en la sesión** (decisión P-04, 2026-10-23). Con
  un plantel abierto, la RLS oculta las demás membresías (ADR 0006), así
  que `MembershipContextQuery` no puede contarlas. Las cuenta
  `IssueHandoffHandler` en el dominio central, que sí las ve, y viajan en
  el token de traspaso (`HandoffGrant`). `ConsumeHandoffHandler` las guarda
  en la sesión del plantel con el puerto nuevo `MembershipSession`. Una
  sesión sin traspaso cuenta 1. Si la persona entra a otro plantel durante
  la sesión, el número se actualiza en su próximo traspaso.
- **«Mis asignaciones» exige el rol Docente** (`role:Docente`). Antes abría
  para cualquier miembro, con la lista vacía. NAV-R03.3 pide que una
  pantalla abra si y solo si se ve su entrada, y el test de paridad lo
  detectó.
- **La entrada actual la marca el servidor.** `MenuCatalog::present` usa
  `routeIs` con los patrones de cada entrada y envía `active`, en vez de
  enviar los patrones al navegador.
- **`ViewerCapabilities` pregunta al Gate** por los permisos que usan el
  menú y el inicio. Los roles y el rol principal salen de
  `MembershipContextQuery`, y `RoleSet` gana `primaryRole()`, que
  reutiliza `homeScreen()`.
- **Medida de NAV-NF03.** El test cuenta lo que añade el layout (3
  consultas: roles, vínculo de representante y calendario). Los permisos
  de la persona ya los carga `auth.can` en cada página.
- **Tests que cambian solo dónde leen.** `GuardianPortalTest` comprobaba
  la entrada «Mis representados» con `auth.isGuardian`. Ahora la busca en
  `layout.navigation`. El test de `HandoffTest` sobre «Cambiar de plantel»
  entra con dos planteles por el traspaso, y un caso nuevo cubre el de un
  solo plantel (NAV-R02.6).
