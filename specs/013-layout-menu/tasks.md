---
spec: 013-layout-menu
estado: implementando
fecha: 2026-10-23
aprobado: 2026-10-23
diseño: design.md
depende-de: 004-identidad-global, 005-auditoria, 006-institucion, 007-anio-escolar, 008-estructura-academica, 009-estudiantes (implementadas); ADR 0009 (aceptado)
---

# Tareas — Layout, menú e inicio por rol

Regla transversal: al cerrar cada tarea, `php artisan test --compact`
(incluidos `--group=rls` y la suite `Architecture`) sigue en verde. TDD
(Q-02): primero el test en rojo, por el motivo correcto. Al cerrar una tarea,
sus tests se añaden a la matriz de `requirements.md` (y, para INS y EST, a la
de su spec).

Cuatro PRs, cada uno usable por sí mismo:

- **PR A (fase 1):** dependencia del plantel y rol Representante al
  vincular. Son cambios en SPEC-006 y SPEC-009 que alimentan el inicio.
- **PR B (fases 2 – 4):** módulo `Workspace`, barra superior, menú por
  permisos y el layout nuevo.
- **PR C (fases 5 – 7):** consultas de resumen y el inicio de cada rol.
- **PR D (fase 8):** navegador de pruebas, accesibilidad y 360 px.

## PR A — Datos que alimentan el inicio

### Fase 1 — Cambios en Tenancy, IdentityAccess y Students

- [x] **T-01** — Migración `add_dependency_to_institutions`:
  - columna `dependency` nula;
  - restricción `CHECK` (solo `publica`, con nacional, estadal o
    municipal);
  - prueba de `up` y `down`.
  - Criterios: INS-R02.4, INS-R02.5
  - Test: `tests/Feature/Tenancy/Infrastructure/Persistence/InstitutionDependencyColumnTest.php`
  - Hecho cuando: la base rechaza una dependencia en un plantel privado y
    un valor desconocido, y el rollback quita la columna.
- [x] **T-02** — `InstitutionDependency` en el agregado `Institution`:
  - solo si el tipo es `publica`;
  - cambiar a otro tipo la borra;
  - `UpdateInstitutionCommand` y `UpdateInstitutionHandler` la reciben, con
    el cambio en la bitácora (INS-R06);
  - `InstitutionView` la expone;
  - adaptador Eloquent.
  - Criterios: INS-R02.4, INS-R02.5
  - Test: `tests/Unit/Tenancy/Domain/InstitutionDependencyTest.php`;
    `tests/Unit/Tenancy/Application/UpdateInstitutionTest.php` (casos
    nuevos); `tests/Feature/Tenancy/Infrastructure/Persistence/EloquentInstitutionRepositoryTest.php`
    (caso nuevo)
- [x] **T-03** — HTTP y pantalla:
  - `InstitutionRequest` acepta `dependency`;
  - en `Institution/Settings.vue`, el selector solo para planteles públicos;
  - INS-R02.4 y R02.5 en `specs/006-institucion/requirements.md`.
  - Criterios: INS-R02.4, INS-R02.5
  - Test: `tests/Feature/Institution/InstitutionSettingsTest.php` (casos
    nuevos: sus funciones auxiliares viven en ese archivo)
- [x] **T-04** — `GrantRoleHandler` (IdentityAccess):
  - da un rol a un miembro activo del plantel abierto, con
    `RoleAssignmentPolicy`;
  - si ya lo tiene, no hace nada;
  - registra `identity.membership.roles_changed`.
  - Criterios: EST-R08.7
  - Test: `tests/Feature/IdentityAccess/GrantRoleTest.php` (integración:
    IdentityAccess prueba sus casos de uso contra PostgreSQL, sin fakes en
    memoria)
- [x] **T-05** — `GuardianInvitations::grantGuardianRole`:
  - lo implementa `IdentityAccessGuardianInvitations` con
    `GrantRoleHandler`;
  - `InviteGuardianHandler` lo llama en la rama del miembro existente,
    antes de vincular;
  - EST-R08.7 en `specs/009-estudiantes/requirements.md`.
  - Criterios: EST-R08.7
  - Test: `tests/Unit/Students/Application/GuardianAccountTest.php` (caso
    nuevo); `tests/Feature/Students/GuardianInvitationTest.php` (caso nuevo)
  - Hecho cuando: un docente vinculado como representante tiene los dos
    roles, y la bitácora lo registra.

## PR B — Barra superior, menú y layout

### Fase 2 — Dominio de `Workspace` (PHP puro)

- [x] **T-06** — Esqueleto del módulo (`src/Workspace`, ServiceProvider
  registrado), `Capabilities`, `MenuGroup` y `MenuEntry` (las once entradas
  con grupo, orden y condición).
  - Criterios: NAV-R03.1
  - Test: `tests/Unit/Workspace/Domain/CapabilitiesTest.php`
- [x] **T-07** — Servicio `Navigation::for(Capabilities)`:
  - entradas visibles por condición, sumando roles y vínculo;
  - agrupadas, en orden y sin grupos vacíos;
  - «Inicio» siempre visible.
  - Criterios: NAV-R03.1, R03.2
  - Test: `tests/Unit/Workspace/Domain/NavigationTest.php` (cada rol base,
    Director que también es Docente, representante sin otro rol, sin rol)

### Fase 3 — Consultas del layout

- [x] **T-08** — `CalendarContextQuery` (AcademicCalendar), con el puerto
  `CalendarContexts` y el adaptador `SqlCalendarContexts` (una consulta).
  Devuelve el año activo y su nombre, el lapso abierto y si hay un año
  planificado.
  - Criterios: NAV-R02.2, R02.3, R02.8
  - Test: `tests/Feature/AcademicCalendar/Infrastructure/CalendarContextQueryTest.php`
- [x] **T-09** — `MembershipContextQuery` (IdentityAccess): roles en el
  plantel abierto, rol principal (IAM-R12.2) y número de planteles con
  membresía activa.
  - Criterios: NAV-R02.4, R02.5, R02.6
  - Test: `tests/Feature/IdentityAccess/Infrastructure/MembershipContextQueryTest.php`
- [x] **T-10** — Puertos `ViewerCapabilities` y `CalendarSource`:
  - `ShowLayoutQuery` → `LayoutView`;
  - adaptadores `GateViewerCapabilities` (memorizado por petición) y
    `AcademicCalendarSource`;
  - fixtures en memoria.
  - Criterios: NAV-R02.2 – R02.6, R03.1 – R03.4
  - Test: `tests/Unit/Workspace/Application/ShowLayoutTest.php`

### Fase 4 — Adaptadores de entrada y layout

- [x] **T-11** — `app/Http/Navigation/MenuCatalog`: ruta, texto,
  descripción y patrón de pantalla actual por `MenuEntry`.
  `HandleInertiaRequests` comparte `layout`:
  - quita `auth.isGuardian` y `auth.isTeacher`;
  - envía `switchInstitutionUrl` solo con más de un plantel.
  - Criterios: NAV-R02.1 – R02.8, R03.4
  - Test: `tests/Feature/Workspace/LayoutPropsTest.php` (con y sin año
    activo, sin lapso abierto, rol principal, uno o varios planteles, y un
    año activado visible en la siguiente petición)
- [x] **T-12** — Paridad menú–rutas. Para cada rol base y para un
  representante vinculado:
  - cada entrada visible responde 200;
  - cada entrada oculta responde 403 o lleva a otro inicio, como hoy;
  - toda clave de `MenuEntry` está en `MenuCatalog`.
  - Criterios: NAV-R03.3, R03.5
  - Test: `tests/Feature/Workspace/NavigationRouteParityTest.php`
- [x] **T-13** — Layout Vue:
  - `AuthenticatedLayout` reescrito, con `Components/Layout/SideNav`,
    `TopBar` y `MobileNav`;
  - composable `useFocusTrap`;
  - «Saltar al contenido», regiones y `aria-current`;
  - mensajes de éxito;
  - `npm run build`.
  - Criterios: NAV-R01.1 – R01.5, R02.1, R02.7
  - Test: `tests/Feature/Workspace/LayoutPropsTest.php` (props que dibuja).
    El comportamiento en el navegador lo verifica T-25: R01.1 – R01.3,
    R01.5, R02.1 y R02.7 quedan sin test hasta entonces.
- [x] **T-14** — Rendimiento del layout: no más de 5 consultas por petición
  para `layout` (registro de consultas en una pantalla del plantel).
  - Criterios: NAV-NF03
  - Test: `tests/Feature/Workspace/LayoutQueryCountTest.php` (`group=perf`)

## PR C — Inicio por rol

### Fase 5 — Dominio del inicio

- [x] **T-15** — `HomeKind`, `HomeBlock` y servicio `HomePlan::for(HomeKind,
  Capabilities)`:
  - orden de bloques;
  - bloque solo con la condición de su pantalla de origen;
  - extras de otros roles sin repetir;
  - bloques operativos y bitácora solo en `admin`;
  - alcance del coordinador;
  - aviso del estudiante.
  - Criterios: NAV-R04.1, R04.2, R04.6, R04.7, R04.8, R04.9, R05.5, R05.6,
    R05.7, R06.2, R09.1, R10.2
  - Test: `tests/Unit/Workspace/Domain/HomePlanTest.php`

### Fase 6 — Consultas de resumen en los módulos dueños

- [x] **T-16** — `StructureOverviewQuery(?grados)` y
  `CoordinatedGradesQuery(cuenta)` (AcademicStructure), con el puerto
  `StructureStatistics` y su adaptador SQL; comprueban `ver-estructura`.
  - Criterios: NAV-R05.1, R05.3, R06.1, R06.2, R06.3
  - Test: `tests/Feature/AcademicStructure/Infrastructure/StructureOverviewQueryTest.php`
- [x] **T-17** — `StudentOverviewQuery` (Students):
  - estudiantes activos (con `ver-estudiantes`);
  - traslados recibidos pendientes y correcciones sin atender (con
    `gestionar-estudiantes`).
  - Criterios: NAV-R05.1, R05.4
  - Hecho así: `StudentsSource` (Workspace) reutiliza `ListStudentsQuery`,
    `ListTransfersQuery` y `ListCorrectionsQuery`, con sus permisos; no hizo
    falta una consulta nueva en Students.
  - Test: `tests/Feature/Workspace/Infrastructure/HomeSourcesTest.php`
- [x] **T-18** — `StaffOverviewQuery` (IdentityAccess): miembros activos por
  rol base, e invitaciones pendientes y vencidas; comprueba
  `gestionar-usuarios`.
  - Criterios: NAV-R05.1, R05.5
  - Hecho así: `IdentityAccessStaffSource` (Workspace) reutiliza
    `MemberDirectory`, el de la pantalla de usuarios; no hizo falta una
    consulta nueva en IdentityAccess.
  - Test: `tests/Feature/Workspace/Infrastructure/HomeSourcesTest.php`
- [x] **T-19** — `InstitutionGapsQuery` (Tenancy): niveles, código DEA,
  firma del Director, RIF si es privado y dependencia si es público.
  - Criterios: NAV-R05.6
  - Test: `tests/Feature/Tenancy/Infrastructure/InstitutionGapsQueryTest.php`

### Fase 7 — Aplicación, HTTP y pantallas del inicio

- [x] **T-20** — `ShowHomeQuery`, con los puertos `StructureSource`,
  `StudentSource`, `StaffSource`, `InstitutionSource` y `ActivitySource`,
  sus fixtures en memoria y sus adaptadores:
  - solo llama a los puertos de los bloques del plan;
  - pendientes con 0 casos omitidos;
  - sin año activo;
  - coordinador sin grados;
  - mis clases y mis representados con sus estados vacíos.
  - Criterios: NAV-R04.1, R04.3, R04.4, R04.5, R05.1 – R05.7, R06.1 –
    R06.4, R07.1 – R07.3, R08.1 – R08.3, R09.1, R10.2
  - Test: `tests/Unit/Workspace/Application/ShowHomeTest.php`
- [x] **T-21** — `HomeController`: cada acción envía `home` desde
  `ShowHomeQuery`, sin cambiar sus componentes ni su middleware
  (`RoleHomeTest` sin cambios, Q-03).
  - Criterios: NAV-R04.1 – R04.9, R05 – R09, R10.2, R10.3
  - Test: `tests/Feature/Workspace/HomeVisibilityTest.php`:
    - props de cada inicio;
    - Director que es Docente con «Mis clases»;
    - bloques sin permiso ausentes de los props;
    - representados sin datos de salud;
    - enlaces de cada pendiente a su entrada.
- [x] **T-22** — Aislamiento: dos planteles con estudiantes, secciones,
  invitaciones y bitácora; el inicio solo cuenta los del plantel abierto.
  - Criterios: NAV-R10.1
  - Test: `tests/Feature/Workspace/WorkspaceIsolationTest.php`
    (`group=rls`)
- [x] **T-23** — Pantallas:
  - `Components/HomeScreen.vue` dibuja `home.blocks`;
  - un componente por bloque en `Components/Home/`;
  - las seis páginas `Home/*` usan los bloques;
  - `npm run build`.
  - Rendimiento: inicio de dirección con 2000 estudiantes, 60 secciones y
    100 miembros en menos de 300 ms (p95).
  - Criterios: NAV-R04.3, R04.4, NAV-NF02
  - Test: `tests/Feature/Workspace/HomePerformanceTest.php` (`group=perf`)

## PR D — Navegador de pruebas y accesibilidad

### Fase 8 — Pest con Playwright

- [ ] **T-24** — Spike y entorno (riesgos del diseño):
  - `pestphp/pest-plugin-browser` y `playwright` como dependencias de
    desarrollo;
  - Chromium fuera de la imagen Alpine: servicio `browser` en `compose` o
    etapa Debian, nunca en `Dockerfile.prod`;
  - abrir `<plantel>.localhost` con sesión iniciada;
  - trabajo `browser` en CI.

  Si los subdominios no funcionan, se detiene y se propone el cambio en
  `design.md` (P-04).
  - Criterios: — (habilita NAV-NF04 y NF05)
  - Test: `tests/Browser/TenantSmokeTest.php` › «opens an institution page
    signed in»
- [ ] **T-25** — Comportamiento del layout en el navegador:
  - panel bajo 1024 px, que retiene el foco;
  - cierre con Escape, con el botón y al navegar, devolviendo el foco;
  - «Saltar al contenido»;
  - entrada actual en una pantalla interna (ficha de estudiante).
  - Criterios: NAV-R01.1 – R01.5, R02.1, R02.7
  - Test: `tests/Browser/LayoutTest.php`
- [ ] **T-26** — Accesibilidad y 360 px:
  - `assertNoAccessibilityIssues` (AA) sobre una pantalla del plantel y los
    seis inicios, a 1280 px y a 360 px;
  - sin desplazamiento horizontal a 360 px.
  - Criterios: NAV-NF04, NF05
  - Test: `tests/Browser/AccessibilityTest.php`

## Verificación final

- [ ] `php artisan test --compact` en verde (incluidos `--group=rls`,
  `--group=perf` y `--group=browser`).
- [ ] Tests de arquitectura en verde: `Workspace` sin `Persistence` ni
  escrituras (NAV-NF01, ADR 0009).
- [ ] `vendor/bin/pint --dirty` sin cambios; `npm run build` sin errores.
- [ ] `migrate:fresh --seed` dos veces y rollback de la migración nueva.
- [ ] Matriz de trazabilidad completa (también INS-R02.4, R02.5 y
  EST-R08.7 en sus specs).
- [ ] `/security-review` de cada PR (permisos del menú y del inicio, y el
  rol al vincular; S-12).
- [ ] `estado` de requirements/design/tasks actualizado.

## Cobertura de criterios

| Grupo | Tareas |
|-------|--------|
| NAV-R01 | T-13, T-25 |
| NAV-R02 | T-08, T-09, T-10, T-11, T-13, T-25 |
| NAV-R03 | T-06, T-07, T-10, T-11, T-12 |
| NAV-R04 | T-15, T-20, T-21, T-23 |
| NAV-R05 | T-15, T-16, T-17, T-18, T-19, T-20, T-21 |
| NAV-R06 | T-15, T-16, T-20, T-21 |
| NAV-R07 | T-20, T-21 |
| NAV-R08 | T-20, T-21 |
| NAV-R09 | T-15, T-20, T-21 |
| NAV-R10 | T-15, T-20, T-21, T-22 |
| NAV-NF01 | Verificación final (arquitectura); T-16 – T-19 (permisos) |
| NAV-NF02 | T-23 |
| NAV-NF03 | T-14 |
| NAV-NF04, NF05 | T-26 |
| INS-R02.4, R02.5 | T-01, T-02, T-03 |
| EST-R08.7 | T-04, T-05 |
