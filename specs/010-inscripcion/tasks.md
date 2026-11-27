---
spec: 010-inscripcion
estado: implementada
fecha: 2026-10-27
aprobada: 2026-10-27
diseño: design.md
depende-de: 005-auditoria, 007-anio-escolar, 008-estructura-academica, 009-estudiantes (implementadas); ADR 0012
---

# Tareas — Inscripción y prosecución

Regla transversal:
- al cerrar cada tarea, `php artisan test --compact` sigue en verde,
  incluidos `--group=rls` y la suite `Architecture`;
- TDD (Q-02): primero el test en rojo, por el motivo correcto;
- al cerrar una tarea, sus tests se añaden a la matriz de
  `requirements.md`.

Tres PRs, cada uno usable por sí mismo:

- **PR A (fases 1 – 5):** inscribir, cambiar de sección o de grado, anular,
  fin por retiro o traslado, e historial en la ficha.
- **PR B (fases 6 – 8):** resultado de prosecución, cierre del año y
  inscripción en lote.
- **PR C (fases 9 – 10):** qué estudiantes ve cada docente y coordinador,
  menú, inicio, accesibilidad y documentación.

## PR A — Inscripción

### Fase 1 — Dominio (PHP puro)

- [x] **T-01** — `Level`, `Placement`, `Period`, `EndReason` y `Occupancy`.
  - Criterios: MAT-R02.1, R02.2, R02.3, R04.2
  - Test: `tests/Unit/Enrollment/Domain/OccupancyTest.php`:
    - «admite hasta el cupo»;
    - «por encima del cupo pide un motivo y lo guarda»;
    - «sabe si está en sobrecupo».

    `tests/Unit/Enrollment/Domain/PeriodTest.php`:
    - «el fin no es anterior al inicio»;
    - «hay motivo de fin si y solo si hay fin».
- [x] **T-02** — `AgeRule`, `AgeCheck` y `AgeException`.
  - Criterios: MAT-R03.1 – R03.5, R03.7
  - Test: `tests/Unit/Enrollment/Domain/AgeCheckTest.php`:
    - «la edad al 31 de diciembre del año en que empieza el año escolar»;
    - los bordes del rango;
    - el 29 de febrero;
    - «regular, aviso o fuera de rango»;
    - «sin regla para el grado, sin comprobación»;
    - «la excepción exige justificación y quién la aprueba».
- [x] **T-03** — Agregado `Enrollment`: `enroll()`, `moveTo()` (sección y
  grado), `end()` y `annul()` con `Annulment`.
  - Criterios: MAT-R01.1, R01.6, R04.1 – R04.3, R04.7, R05.1, R05.2,
    R14.1, R14.3, R14.4
  - Test: `tests/Unit/Enrollment/Domain/EnrollmentTest.php`:
    - «una inscripción terminada no cambia»;
    - «el cambio termina esta y abre la siguiente desde la misma fecha»;
    - «el cambio de grado exige directivo»;
    - «la anulación: 47 y 49 horas, con y sin notas, con y sin directivo,
      con motivo»;
    - «la fecha no es posterior a hoy».

### Fase 2 — Consultas públicas y puntos de extensión en otros módulos

- [x] **T-04** — AcademicStructure:
  - `GradesQuery`, con el orden y el nivel de los 17 grados;
  - `CurriculumQuery`, con las materias del pensum de un grado y año;
  - `RemovalGuard::hasEnrollments`: `RemovalGuards::sectionInUse` lo usa y
    `enrolledIn` sigue contando las vigentes.
  - Criterios: MAT-R10.1, R10.2 (punto de extensión)
  - Test:
    - `tests/Unit/AcademicStructure/Application/GradesQueryTest.php`;
    - `tests/Unit/AcademicStructure/Application/CurriculumQueryTest.php`;
    - `tests/Unit/AcademicStructure/Application/SectionsTest.php` › «no
      quita una sección con inscripciones terminadas».

    Los tests existentes de SPEC-008 no cambian su lógica.
- [x] **T-05** — AcademicCalendar: `SchoolYearLookup::startOf`, `nextOf` y
  `lastTermClosed`.
  - Criterios: apoyo a MAT-R03.1, R06.1, R07.1
  - Test: `tests/Unit/AcademicCalendar/Application/SchoolYearLookupTest.php`.
- [x] **T-06** — Students:
  - `EnrollableStudentsQuery`, con estado, fecha de nacimiento, nombre e
    identificación por ids, y los estudiantes activos;
  - punto de extensión `WithdrawalHook` (ADR 0012), que llaman
    `WithdrawStudentHandler` y `ApproveTransferHandler` dentro de su
    transacción.
  - Criterios: MAT-R05.1, R05.2, R05.5 (punto de extensión)
  - Test: `tests/Unit/Students/Application/WithdrawalHookTest.php`:
    - «el retiro avisa con la fecha y el motivo»;
    - «el traslado avisa como traslado»;
    - «si el aviso falla, el retiro no ocurre».

    Los tests existentes de retiro y traslado no cambian su lógica.

### Fase 3 — Aplicación

- [x] **T-07** — Puertos de Enrollment, `EnrollmentAccess`,
  `EnrollmentAudit` y los adaptadores en memoria de los tests.
  - Criterios: MAT-R11.1, R11.4, R12.2
  - Test: `tests/Unit/Enrollment/Application/EnrollmentAccessTest.php`;
    `tests/Unit/Enrollment/Application/EnrollmentAuditTest.php` › «sin datos
    de salud».
- [x] **T-08** — `EnrollStudentHandler`, bajo el `CalendarLock`.
  - Criterios: MAT-R01.1 – R01.6, R02.1, R02.2, R03.1 – R03.5, R12.1,
    R13.3
  - Test: `tests/Unit/Enrollment/Application/EnrollStudentTest.php`:
    - «inscribe y registra en la bitácora»;
    - cada rechazo;
    - «sobrecupo con motivo»;
    - «aviso de edad»;
    - «la excepción la aprueba quien está en la sesión»;
    - «toma el bloqueo del calendario».
- [x] **T-09** — `ChangeSectionHandler` y `ChangeGradeHandler`.
  - Criterios: MAT-R04.1 – R04.5, R04.7 – R04.9, R12.1
  - Test: `tests/Unit/Enrollment/Application/ChangePlacementTest.php`.
- [x] **T-10** — `AnnulEnrollmentHandler` y la etiqueta `GradedEnrollments`.
  - Criterios: MAT-R14.1 – R14.4, R12.1
  - Test: `tests/Unit/Enrollment/Application/AnnulEnrollmentTest.php`:
    - «el gestor anula en 48 horas»;
    - «después, solo un directivo»;
    - «con notas, solo un directivo», con una inscripción calificada
      falsa.
- [x] **T-11** — `EndEnrollmentsOnWithdrawal` y `EnrollmentRemovalGuard`.
  - Criterios: MAT-R05.1 – R05.4, R10.1, R10.2, R12.1
  - Test: `tests/Unit/Enrollment/Application/EnrollmentHooksTest.php`:
    - «el retiro termina las vigentes en su fecha»;
    - «la de un año que aún no empieza termina en su inicio»;
    - «tras la reincorporación no hay inscripción vigente»;
    - «el estudiante trasladado llega sin inscripción».
- [x] **T-12** — `YearOverviewQuery`, `SectionRosterQuery`,
  `EnrolledCountsQuery` y `StudentHistoryQuery`.
  - Criterios: MAT-R02.3, R04.6, R09.1 – R09.3, R14.2, R14.6
  - Test: `tests/Unit/Enrollment/Application/RosterQueriesTest.php`.

### Fase 4 — Infraestructura

- [x] **T-13** — Migración `…_create_enrollments_tables`, en su propia
  tarea:
  - `enrollments` y `enrollment_pending_subjects`, con RLS;
  - los `CHECK`;
  - el índice único parcial de vigentes.
  - Criterios: MAT-R01.2, NF02, NF03
  - Test:
    - `tests/Feature/Enrollment/Infrastructure/EnrollmentSchemaTest.php`:
      cada `CHECK` y el índice único;
    - `TenantTableTest`;
    - `migrate:rollback` de la migración y vuelta a `migrate`.
- [x] **T-14** — Migración
  `…_add_enrollment_permissions_to_existing_institutions` y los dos casos de
  `Permission`, también en el sembrado de roles. El Administrador concede
  `gestionar-inscripciones` persona por persona (P-04, 2026-10-28):
  - `SetEnrollmentManagementHandler`, que comparte `DirectPermissionGrant`
    con el de los lapsos cerrados;
  - una casilla en la ficha del miembro.
  - Criterios: MAT-R11.2, R11.3, R11.5
  - Test:
    - `tests/Feature/Enrollment/EnrollmentPermissionsMigrationTest.php` ›
      «Administrador y Director los tienen en planteles existentes y
      nuevos», más `up`, `down` y `up`;
    - `tests/Feature/IdentityAccess/EnrollmentManagementGrantTest.php` ›
      «el Administrador lo concede y lo retira a un miembro, registrado»,
      «solo un Administrador».

    Los tests del permiso de lapsos cerrados no cambian su lógica.
- [x] **T-15** — Adaptadores y enlaces:
  - `EloquentEnrollmentRepository`, que convierte el índice en
    `AlreadyEnrolled`, y `EloquentEnrollmentList`;
  - `GateEnrollmentPermissions`;
  - `ConfigAgeRules`, que valida al arrancar, y `config/enrollment.php`;
  - los adaptadores `…Facts`;
  - las etiquetas en `EnrollmentServiceProvider`.
  - Criterios: MAT-R03.6, R05.5, R10.1, R10.2, R13.1, NF02, NF03
  - Test:
    - `tests/Feature/Enrollment/Infrastructure/EloquentEnrollmentRepositoryTest.php`;
    - `tests/Unit/Enrollment/Infrastructure/ConfigAgeRulesTest.php`;
    - `tests/Isolation/EnrollmentConcurrencyTest.php` › «dos conexiones por
      el último lugar: entra una»;
    - `tests/Feature/Enrollment/EnrollmentIsolationTest.php` (`group=rls`);
    - `tests/Feature/Enrollment/Infrastructure/EnrollmentHooksIntegrationTest.php`:
      - «retirar termina la inscripción»;
      - «si falla la inscripción, el retiro se deshace»;
      - «una sección con inscripciones no se quita»;
      - «el cupo no baja de los inscritos».
    - regla de arquitectura en `tests/Architecture/LayersTest.php`:
      - Enrollment no importa la Infrastructure ni el Domain de otros
        módulos;
      - nadie importa Enrollment;
      - solo su provider etiqueta.

### Fase 5 — HTTP y pantallas

- [x] **T-16** — Rutas, controladores y FormRequests de inscribir, cambiar
  de sección o de grado, y anular:
  - las páginas «Inscripciones» (año) y «Sección»;
  - los errores en `bootstrap/app.php`.
  - Criterios: MAT-R01 – R04, R09.1, R09.2, R11.1, R11.4, R13.2, R14, NF01
  - Test: `tests/Feature/Enrollment/EnrollmentHttpTest.php` (cada ruta y
    cada mensaje de error) y `tests/Feature/Enrollment/EnrollmentPermissionsTest.php`
    (con y sin cada permiso).
- [x] **T-17** — Historial de inscripciones en la ficha del estudiante (prop
  `enrollments` en `StudentController@show`).
  - Criterios: MAT-R04.6, R14.6
  - Test: `tests/Feature/Enrollment/StudentHistoryTest.php`.

## PR B — Prosecución

### Fase 6 — Dominio

- [x] **T-18** — `Outcome`, `PromotionResult` y `PromotionRules`.
  `Enrollment::recordResult()`; `annul()` rechaza con resultado.
  - Criterios: MAT-R06.3 – R06.7, R07.1, R14.5, R15.1, R15.2
  - Test: `tests/Unit/Enrollment/Domain/PromotionRulesTest.php`:
    - Inicial solo promovido;
    - Primaria sin pendientes;
    - Media con 0, 1, 2 y 3 pendientes, y materias fuera del pensum;
    - grado siguiente, mismo grado y último grado;
    - egresado y pendiente de egresar.
    `EnrollmentTest` › «con resultado no se anula».

### Fase 7 — Aplicación

- [x] **T-19** — `RecordResultHandler`. `EnrollStudentHandler` rechaza al
  pendiente de egresar.
  - Criterios: MAT-R06.1, R06.2, R06.8, R06.9, R15.4, R16.3, R12.1
  - Test: `tests/Unit/Enrollment/Application/RecordResultTest.php`:
    - «solo con el año activo y su tercer lapso cerrado»;
    - «corrige antes de inscribir en el año siguiente, no después»;
    - «perdió por inasistencia».

    `EnrollStudentTest` › «el pendiente de egresar no se inscribe en un
    grado».
- [x] **T-20** — Punto de extensión `YearClosingGuard` en AcademicCalendar
  (`CloseSchoolYearHandler`, tras el bloqueo), `UnresolvedResultsGuard` y
  `PendingResultsQuery`.
  - Criterios: MAT-R06.10, R16.1, R16.2, R16.4
  - Test:
    - `tests/Unit/AcademicCalendar/Application/YearClosingGuardTest.php`:
      «el cierre se rechaza con lo que diga el guard». Los tests de cierre
      existentes no cambian su lógica.
    - `tests/Unit/Enrollment/Application/PendingResultsTest.php`:
      - «por sección»;
      - «el retirado ya no cuenta».
- [x] **T-21** — `ProposeNextYearQuery` y `EnrollNextYearHandler`: todo o
  nada, y revalida dentro de la transacción.
  - Criterios: MAT-R07.1 – R07.7, R15.3, R12.1
  - Test: `tests/Unit/Enrollment/Application/NextYearTest.php`:
    - cada exclusión, con su motivo: sin resultado, retirado, ya inscrito,
      grado no ofrecido, fuera de rango, egresado y pendiente de egresar;
    - «sobrecupo con motivo por sección»;
    - «si uno falla, no se inscribe nadie»;
    - «año siguiente cerrado o inexistente».

### Fase 8 — Infraestructura y HTTP

- [x] **T-22** — Persistencia del resultado y de las materias pendientes;
  enlace del `YearClosingGuard`.
  - Criterios: MAT-R06.5, R16.2
  - Test:
    - `EloquentEnrollmentRepositoryTest` › «guarda y lee el resultado con
      sus materias»;
    - `EnrollmentHooksIntegrationTest` › «el año no se cierra con
      estudiantes sin resultado».
- [x] **T-23** — Pantallas y rutas:
  - «Prosecución» por sección;
  - lote, con propuesta y confirmación;
  - el diálogo de cierre del año con `pendingResults` (nombres solo con
    `ver-estudiantes`).
  - Criterios: MAT-R06.10, R07.4, R16.1, NF04
  - Test:
    - `tests/Feature/Enrollment/PromotionHttpTest.php`;
    - `tests/Feature/Enrollment/NextYearHttpTest.php`;
    - `tests/Feature/AcademicCalendar/CloseYearPendingResultsTest.php`;
    - `tests/Feature/Enrollment/NextYearPerformanceTest.php` (`group=perf`):
      600 estudiantes en menos de 10 s.

## PR C — Visibilidad, navegación y cierre

### Fase 9 — Visibilidad

- [x] **T-24** — Punto de extensión `StudentVisibilityRule` en Students
  (`ListStudentsQuery` y `ShowStudentQuery`, intersección de las reglas;
  ajeno → `StudentNotFound`). Sin reglas registradas, todo sigue igual.
  - Criterios: MAT-R08.2, R08.4
  - Test: `tests/Unit/Students/Application/StudentVisibilityRuleTest.php`.
- [x] **T-25** — `SectionScopedVisibility` y su registro, también en
  `SectionRosterQuery`.
  - Los tests de SPEC-009 que esperan que el docente vea a todos cambian
    su preparación, y su aserción donde haga falta. Es una excepción
    expresa a Q-03 que se anota test por test en `design.md`.
  - Criterios: MAT-R08.1 – R08.4, NF05
  - Test:
    - `tests/Feature/Enrollment/StudentVisibilityTest.php`:
      - «el docente ve los de sus secciones»;
      - «el guía, los de la suya»;
      - «el coordinador, los de sus grados»;
      - «la ficha ajena da 404»;
      - «al dejar la sección deja de verlos»;
      - «el gestor ve a todos».
    - `tests/Feature/Enrollment/VisibilityPerformanceTest.php`
      (`group=perf`): 10 ms (p95) y 300 ms (p95) con 10 secciones.

### Fase 10 — Navegación, accesibilidad y documentación

- [x] **T-26** — Entrada «Inscripciones» en el menú (`ver-estudiantes`) y
  paridad menú–rutas; bloque de inscritos por sección del inicio con
  `EnrolledCountsQuery`.
  - Criterios: MAT-R09.3
  - Test: `tests/Feature/Workspace/MenuParityTest.php` (existente, gana la
    entrada); `tests/Feature/Workspace/HomeEnrollmentBlockTest.php`.
- [x] **T-27** — Tests de navegador: axe y 360 px en «Inscripciones»,
  «Sección», «Prosecución» y el lote; terminología «plantel».
  - Criterios: MAT-NF06
  - Test: `tests/Browser/EnrollmentTest.php`.
- [x] **T-28** — Cambios en otras specs:
  - SPEC-002: IAM-R11.1;
  - SPEC-007: CAL-R02.4, con la condición de MAT-R16.2;
  - SPEC-008: EAC-R02.6, R07.3 y `RemovalGuard`;
  - SPEC-009: EST-R05.1, R05.2 y los puntos de extensión;
  - SPEC-013: el menú y el bloque de inicio;
  - roadmap.

  Se aplica en el PR que cambia cada comportamiento (P-04): A para 008 y
  009 (retiro); B para 007; C para 009 (visibilidad), 013 y 002.
  - Criterios: —
  - Test: `tests/Architecture/SpecsTest.php`

## Verificación final

- [x] `php artisan test --compact` en verde (incluidos `--group=rls`,
  `--group=perf` y `tests/Isolation`), y tests de navegador en verde.
- [x] Tests de arquitectura en verde (capas, sin dependencias hacia
  Enrollment, etiquetas solo desde su provider).
- [x] `vendor/bin/pint --dirty` sin cambios; `npm run build` sin errores.
- [x] `migrate:fresh --seed` y rollback de las dos migraciones nuevas.
- [x] Matriz de trazabilidad completa.
- [x] `/security-review` de cada PR (permisos y tenancy; S-12).
- [x] `estado` de requirements/design/tasks actualizado.

## Cobertura de criterios

| Grupo | Tareas |
|-------|--------|
| MAT-R01 | T-03, T-08, T-13, T-16 |
| MAT-R02 | T-01, T-08, T-12, T-16 |
| MAT-R03 | T-02, T-08, T-15, T-16 |
| MAT-R04 | T-03, T-09, T-12, T-16, T-17 |
| MAT-R05 | T-03, T-06, T-11, T-15 |
| MAT-R06 | T-18, T-19, T-20, T-22, T-23 |
| MAT-R07 | T-18, T-21, T-23 |
| MAT-R08 | T-24, T-25 |
| MAT-R09 | T-12, T-16, T-26 |
| MAT-R10 | T-04, T-11, T-15 |
| MAT-R11 | T-07, T-14, T-16 |
| MAT-R12 | T-07 – T-11, T-19, T-21 |
| MAT-R13 | T-08, T-15, T-16 |
| MAT-R14 | T-03, T-10, T-12, T-16, T-17, T-18 |
| MAT-R15 | T-18, T-19, T-21 |
| MAT-R16 | T-19, T-20, T-22, T-23 |
| MAT-NF01 | T-16 |
| MAT-NF02 | T-13, T-15 |
| MAT-NF03 | T-13, T-15 |
| MAT-NF04 | T-23 |
| MAT-NF05 | T-25 |
| MAT-NF06 | T-27 |
