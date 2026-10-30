---
spec: 010-inscripcion
estado: implementada
fecha: 2026-10-27
aprobada: 2026-10-27
requisitos: requirements.md
adr: 0012
---

# Diseño — Inscripción y prosecución

## Resumen

- **Módulo nuevo `Enrollment`.** Su agregado es `Enrollment`: un estudiante
  en un año, un grado y una sección, durante un periodo, con su resultado
  de prosecución. Las reglas por nivel (prosecución y edad) viven en su
  dominio.
- **Lo que necesita de otros módulos** lo pide por sus consultas públicas:
  - de AcademicStructure, secciones, grados y pensum;
  - de Students, los datos del estudiante;
  - de AcademicCalendar, el estado y las fechas del año.
- **Las reglas que se cumplen dentro de las escrituras de esos módulos**
  van por puntos de extensión síncronos (ADR 0012): retiro y traslado,
  cierre del año, retirada de secciones y visibilidad de los estudiantes.
  Así la dependencia va en un solo sentido.
- **La concurrencia del cupo** se resuelve con el mismo bloqueo del
  calendario que ya usa AcademicStructure (ADR 0007).

## Módulo y capas

```
src/Enrollment/
├── Domain/
│   ├── Enrollment.php                 agregado
│   ├── EnrollmentId.php
│   ├── EnrollmentRepository.php
│   ├── Placement.php                  sección + grado + nivel
│   ├── Level.php                      inicial, primaria, media_general
│   ├── Period.php                     desde, hasta, motivo del fin
│   ├── EndReason.php                  section_change, grade_change, withdrawal, transfer, annulled
│   ├── Occupancy.php                  cupo e inscritos de una sección; admite o pide motivo
│   ├── AgeRule.php · AgeCheck.php     edad regular, rango y fecha de corte
│   ├── AgeException.php               justificación + quién la aprobó
│   ├── Outcome.php                    promoted, repeats, promoted_with_pending, lost_by_absence
│   ├── PromotionResult.php            resultado + materias pendientes
│   ├── PromotionRules.php             reglas por nivel (MAT-R06.3 – R06.7)
│   ├── Annulment.php · AnnulmentReason.php
│   └── Exceptions: NotEnrollable, OverCapacity, AgeOutOfRange, ResultNotAllowed, …
├── Application/
│   ├── EnrollStudent/ · ChangeSection/ · ChangeGrade/ · AnnulEnrollment/
│   ├── RecordResult/ · NextYear/ (ProposeNextYearQuery, EnrollNextYearHandler)
│   ├── Roster/ (SectionRosterQuery, YearOverviewQuery, EnrolledCountsQuery, StudentHistoryQuery, PendingResultsQuery)
│   ├── Port/ EnrollmentPermissions, AgeRules, StudentFacts, StructureFacts, CalendarFacts, GradedEnrollments, EnrollmentList
│   ├── Hooks/ EndEnrollmentsOnWithdrawal, EnrollmentRemovalGuard, UnresolvedResultsGuard, SectionScopedVisibility
│   └── EnrollmentAccess.php · EnrollmentAudit.php
└── Infrastructure/
    ├── EnrollmentServiceProvider.php   enlaces y etiquetas (ADR 0012)
    ├── GateEnrollmentPermissions.php · ConfigAgeRules.php
    ├── StudentsStudentFacts.php · AcademicStructureFacts.php · AcademicCalendarFacts.php
    └── Persistence/ EnrollmentModel, EloquentEnrollmentRepository, EloquentEnrollmentList
```

## Modelo de dominio

| Elemento | Tipo | Invariantes / responsabilidad |
|----------|------|-------------------------------|
| `Enrollment` | Agregado | Un estudiante en un año, en una `Placement`, con un `Period`. Se crea con `enroll()` (MAT-R01.1). `moveTo()` termina este y devuelve el nuevo desde la misma fecha, con fecha ≥ inicio y ≤ hoy (MAT-R04.1, R04.2, R04.7). `end()` cierra por retiro o traslado (MAT-R05). `annul()` (MAT-R14). `recordResult()` (MAT-R06). Una inscripción terminada no cambia. Una con resultado no se anula ni cambia de sección. |
| `Placement` | Value Object | Sección, grado y nivel; el grado pertenece al nivel. |
| `Period` | Value Object | `startsOn ≤ endsOn`. `endReason` presente si y solo si hay `endsOn`. |
| `Occupancy` | Value Object | `capacity`, `enrolled`. `admit(?reason)`: con `enrolled ≥ capacity` exige motivo (MAT-R02.1, R02.2). `isOver()` para MAT-R02.3. |
| `AgeRule` | Value Object | Grado → edad regular, mínima, máxima; `min ≤ regular ≤ max`. |
| `AgeCheck` | Servicio de dominio | Edad a la fecha de corte (31 de diciembre del año en que empieza el año escolar, MAT-R03.1). Devuelve regular, aviso o fuera de rango. Sin regla para el grado: sin comprobación (MAT-R03.7). |
| `AgeException` | Value Object | Justificación no vacía y cuenta del directivo (MAT-R03.4, R03.5). |
| `PromotionResult` | Value Object | `Outcome` y 0 – 2 materias pendientes. Pendientes solo con `PromotedWithPending`, y entonces de 1 a 2 (MAT-R06.5, R06.6). |
| `PromotionRules` | Servicio de dominio | Por `Level`: Inicial, solo `Promoted` (R06.3). Primaria, sin pendientes (R06.4). Media: pendientes del pensum del grado (R06.7). `nextGrade()`: el siguiente si fue promovido; el mismo si repite o perdió por inasistencia (R07.1). |
| `Annulment` | Value Object | Motivo (error de digitación, doble registro, otro con observación) y quién y cuándo. `Enrollment::annul()` exige directivo si pasaron más de 48 horas o hay notas (R14.3), y rechaza si hay resultado (R14.5). |
| Egresado / pendiente de egresar | Derivado | Se deriva del resultado en `media_5`: `Promoted` → egresado; `PromotedWithPending` → pendiente de egresar (MAT-R15.1, R15.2). No es un estado guardado. |

Las excepciones de dominio extienden `BusinessRuleViolation` (422) con el
campo afectado, salvo `AlreadyEnrolled`, que es un `Conflict` (409).

## Casos de uso (Application)

Todos los de escritura:
- comprueban el permiso (`EnrollmentAccess`, S-04);
- abren la transacción y toman el `CalendarLock` (ADR 0007), que serializa
  la inscripción con los cambios de cupo, la retirada de secciones y el
  cierre del año;
- leen el agregado y lo guardan;
- registran en la bitácora (`EnrollmentAudit`).

| Caso de uso | Entrada | Salida | Criterios |
|-------------|---------|--------|-----------|
| `EnrollStudentHandler` | `EnrollStudentCommand` (estudiante, sección, fecha, motivo de sobrecupo?, excepción de edad?) | `EnrollmentId` + aviso de edad? | MAT-R01.1 – R01.6, R02.1, R02.2, R02.4, R03.1 – R03.7, R15.4, R11.1, R11.4, R12.1, R13.3 |
| `ChangeSectionHandler` | inscripción, sección, fecha, motivo, motivo de sobrecupo? | `EnrollmentId` nueva | MAT-R04.1 – R04.5, R12.1 |
| `ChangeGradeHandler` | ídem + excepción de edad? | `EnrollmentId` nueva | MAT-R04.7 – R04.9, R03, R02 |
| `AnnulEnrollmentHandler` | inscripción, motivo, observación? | — | MAT-R14.1 – R14.5, R12.1 |
| `RecordResultHandler` | inscripción, resultado, materias? | — | MAT-R06.1 – R06.9, R16.3, R12.1 |
| `ProposeNextYearQuery` | año origen | propuesta por estudiante (grado destino, secciones posibles) + excluidos con motivo | MAT-R07.1 – R07.3, R07.6, R07.7, R15.3 |
| `EnrollNextYearHandler` | año origen, `{estudiante → sección}`, motivo de sobrecupo por sección | número inscrito | MAT-R07.4, R07.5, R12.1. Todo o nada. Revalida la propuesta dentro de la transacción. |
| `EndEnrollmentsOnWithdrawal` (implementa `WithdrawalHook` de Students) | estudiante, fecha, retiro o traslado | — | MAT-R05.1, R05.2, R05.5, R16.4. Termina toda inscripción vigente; la de un año que aún no empieza termina en su fecha de inicio. |
| `EnrollmentRemovalGuard` (implementa `RemovalGuard`) | sección | inscritos vigentes / si tiene inscripciones | MAT-R10.1, R10.2 |
| `UnresolvedResultsGuard` (implementa `YearClosingGuard`) | año | número sin resultado | MAT-R16.2 |
| `SectionScopedVisibility` (implementa `StudentVisibilityRule`) | cuenta actual | `null` (todos) o ids visibles | MAT-R08.1 – R08.4 |
| `SectionRosterQuery` | sección | inscritos vigentes, cupo, sobrecupo, resultados | MAT-R02.3, R06.10, R09.1, R09.3 |
| `YearOverviewQuery` | año | secciones con cupo e inscritos; activos sin inscripción | MAT-R02.3, R09.1, R09.2 |
| `EnrolledCountsQuery` | año | inscritos por sección | MAT-R09.3 (para SPEC-008 y SPEC-013) |
| `StudentHistoryQuery` | estudiante | inscripciones con fin, anulaciones, resultado, egreso | MAT-R04.6, R14.6, R15.1, R15.2 |
| `PendingResultsQuery` | año | por sección, quién no tiene resultado | MAT-R06.10, R16.1 |

`RecordResultHandler` exige que el año esté activo y su tercer lapso
cerrado (`CalendarFacts`). Rechaza corregir el resultado si el estudiante ya
tiene una inscripción en el año siguiente (R06.9).

**Visibilidad (MAT-R08).**
- **Quién ve todo.** Con `gestionar-estudiantes` o
  `gestionar-inscripciones`, la regla devuelve `null`: ve todos los
  estudiantes.
- **Docente y Coordinador.** Para el resto devuelve los ids de los
  estudiantes con inscripción vigente y no anulada en el año activo:
  - en las secciones de `TeachingQuery::sectionsOf`, donde dicta alguna
    materia o es guía;
  - o en los grados de `CoordinatedGradesQuery`.
- **Cómo lo usa Students.** `ListStudentsQuery` y `ShowStudentQuery` aplican
  la intersección de las reglas registradas; con un id ajeno, la ficha
  responde `StudentNotFound` (404, R08.2). Como se calcula en cada petición,
  R08.3 se cumple sin más.

## Puertos y adaptadores

| Puerto (interfaz) | Capa | Adaptador | Notas |
|-------------------|------|-----------|-------|
| `EnrollmentRepository` | Domain | `EloquentEnrollmentRepository` | Traduce la violación del índice único de vigentes a `AlreadyEnrolled` (Conflict). |
| `EnrollmentList` | Application | `EloquentEnrollmentList` | Lado de lectura (A-08) de las consultas de lista. |
| `EnrollmentPermissions` | Application | `GateEnrollmentPermissions` | `canManage` (gestionar-inscripciones), `canApproveExceptions` (aprobar-excepciones-inscripcion), `canView` (ver-estudiantes), `seesAllStudents`, `currentAccountId`. |
| `AgeRules` | Application | `ConfigAgeRules` (`config/enrollment.php`) | Edades por grado y fecha de corte (MAT-R03.6). Ver decisión 3. |
| `StudentFacts` | Application | `StudentsStudentFacts` | Sobre la consulta pública nueva de Students `EnrollableStudentsQuery`: estado, fecha de nacimiento, nombre e identificación por ids, y los activos. |
| `StructureFacts` | Application | `AcademicStructureFacts` | Sobre `SectionsOfYearQuery`, `TeachingQuery`, `CoordinatedGradesQuery` y dos consultas públicas nuevas de AcademicStructure: `GradesQuery` (orden y nivel de los 17 grados) y `CurriculumQuery` (materias del pensum de un grado y año). |
| `CalendarFacts` | Application | `AcademicCalendarFacts` | Sobre `SchoolYearLookup`, que gana `startOf`, `nextOf` y `lastTermClosed`, y sobre `CalendarLock`. |
| `GradedEnrollments` | Application (etiqueta) | — (SPEC-011) | «¿Tiene notas esta inscripción?» (MAT-R14.3). Sin nadie registrado: no. |
| `WithdrawalHook` | Application de Students (etiqueta) | `EndEnrollmentsOnWithdrawal` | ADR 0012. Lo llaman `WithdrawStudentHandler` y `ApproveTransferHandler` dentro de su transacción. |
| `StudentVisibilityRule` | Application de Students (etiqueta) | `SectionScopedVisibility` | ADR 0012. |
| `YearClosingGuard` | Application de AcademicCalendar (etiqueta) | `UnresolvedResultsGuard` | ADR 0012. Lo llama `CloseSchoolYearHandler` tras el bloqueo. |
| `RemovalGuard` (existente) | Application de AcademicStructure (etiqueta) | `EnrollmentRemovalGuard` | Gana `hasEnrollments(sectionId)`: una sección con cualquier inscripción, también terminada o anulada, no se quita (MAT-R10.1; la clave foránea es `RESTRICT`). `enrolledIn` cuenta solo las vigentes (R10.2). |

## Adaptadores de entrada (`app/`)

Rutas del plantel, en español como las existentes. Middleware de permiso en
la ruta (S-04, grueso):

| Ruta | Controlador | Permiso | Caso de uso |
|------|-------------|---------|-------------|
| `GET /inscripciones/{year?}` | `EnrollmentController@index` | `ver-estudiantes` | `YearOverviewQuery` |
| `GET /inscripciones/secciones/{section}` | `EnrollmentController@section` | `ver-estudiantes` | `SectionRosterQuery` (filtrado por la visibilidad) |
| `POST /inscripciones` | `EnrollmentController@store` | `gestionar-inscripciones` | `EnrollStudentHandler` |
| `POST /inscripciones/{enrollment}/seccion` | `EnrollmentChangeController@section` | `gestionar-inscripciones` | `ChangeSectionHandler` |
| `POST /inscripciones/{enrollment}/grado` | `EnrollmentChangeController@grade` | `aprobar-excepciones-inscripcion` | `ChangeGradeHandler` |
| `POST /inscripciones/{enrollment}/anular` | `EnrollmentChangeController@annul` | `gestionar-inscripciones` | `AnnulEnrollmentHandler` |
| `GET /prosecucion/secciones/{section}` | `PromotionController@section` | `gestionar-inscripciones` | `SectionRosterQuery` |
| `PUT /inscripciones/{enrollment}/resultado` | `PromotionController@update` | `gestionar-inscripciones` | `RecordResultHandler` |
| `GET /prosecucion/{year}/lote` | `NextYearController@show` | `gestionar-inscripciones` | `ProposeNextYearQuery` |
| `POST /prosecucion/{year}/lote` | `NextYearController@store` | `gestionar-inscripciones` | `EnrollNextYearHandler` |

Además:
- **Ficha del estudiante (SPEC-009).** `StudentController@show` añade la prop
  `enrollments` (`StudentHistoryQuery`).
- **Pantalla «Año escolar» (SPEC-007).** El diálogo de cierre recibe
  `pendingResults` (`PendingResultsQuery`): cifras por sección, con nombres
  solo para quien tiene `ver-estudiantes`.
- **Menú (SPEC-013).** Una entrada «Inscripciones» con `ver-estudiantes`, y
  la paridad menú–rutas.
- **Formularios.** Los FormRequest validan la forma: fechas, enteros y
  motivos de hasta 500 caracteres. Las reglas están en el dominio (S-09).
- **Respuesta a Inertia.** Solo DTOs (S-10), nunca datos de salud.

## Datos

Migración `create_enrollments_tables`:

- **`enrollments`**
  - **Columnas:**
    - `id`, `belongsToInstitution()`;
    - `student_id` → `students` y `school_year_id` → `school_years`, las dos `RESTRICT`;
    - `section_id` → `sections` `RESTRICT`, y `grade` (string);
    - `starts_on`, `ends_on` nulo y `end_reason` nulo, con `CHECK` en los cinco valores;
    - sobrecupo: `over_capacity_reason` nulo;
    - excepción de edad: `age_exception_reason` y `age_exception_by` (cuenta), nulos;
    - registro: `registered_at` (timestamptz) y `registered_by`;
    - anulación: `annulment_reason`, `annulment_note`, `annulled_by` y `annulled_at`, nulos;
    - resultado: `outcome` nulo con `CHECK`, `outcome_recorded_at` y `outcome_recorded_by`, nulos;
    - `previous_id` nulo, que apunta a la inscripción anterior en un cambio;
    - `timestamps`.
  - **`CHECK`:**
    - `ends_on IS NULL = end_reason IS NULL`;
    - `ends_on >= starts_on`;
    - `annulled_at IS NOT NULL = (end_reason = 'annulled')`;
    - `age_exception_reason IS NULL = age_exception_by IS NULL`.
  - **Índices:**
    - **único parcial** `(student_id, school_year_id) WHERE ends_on IS NULL`: una sola vigente por estudiante y año (MAT-R01.2, NF03);
    - `(section_id) WHERE ends_on IS NULL`, para el cupo y la lista;
    - `(school_year_id, grade) WHERE ends_on IS NULL`, para coordinadores y lotes;
    - `(student_id)`, para el historial.
  - `RowLevelSecurity::enable('enrollments')`.
- **`enrollment_pending_subjects`**
  - **Columnas:** `id`, `belongsToInstitution()`, `enrollment_id` → `enrollments` (`CASCADE`), `subject_id` → `subjects`.
  - **Restricciones:** único `(enrollment_id, subject_id)`.
  - `RowLevelSecurity::enable(...)`.

Migración `add_enrollment_permissions_to_existing_institutions`, como la de
SPEC-008:
- `gestionar-inscripciones` y `aprobar-excepciones-inscripcion` para
  Administrador y Director;
- también en los planteles nuevos, por el sembrado de roles (MAT-R11.2,
  R11.5);
- el Administrador concede y retira `gestionar-inscripciones` a otros
  miembros, persona por persona, con `SetEnrollmentManagementHandler`
  (IdentityAccess). Sigue el modelo de `SetClosedTermGradeEditingHandler`
  y lo comparten con `DirectPermissionGrant` (MAT-R11.3). *Cambiado en la
  implementación (P-04): no existe una gestión de permisos por rol;*
- `Permission` (IdentityAccess) gana los dos casos.

**Edades.** `config/enrollment.php` lleva `cutoff` (`12-31`) y la tabla de
edades de Primaria y Media de las decisiones de producto. Inicial no tiene
entradas (MAT-R03.7, Q6).

## Patrones aplicados

| Patrón | Fuerza que resuelve (C-05) |
|--------|----------------------------|
| Aggregate (`Enrollment`) | Las reglas de una inscripción (periodo, cambio, anulación, resultado) se cumplen juntas y se guardan juntas. |
| Value Object (`Occupancy`, `AgeRule`, `PromotionResult`, `Period`) | Reglas sin identidad que se prueban solas y se reutilizan en la inscripción, el cambio y el lote. |
| Domain Service (`PromotionRules`, `AgeCheck`) | Son reglas del nivel o del grado, no de una inscripción; las comparten el resultado y el lote. |
| Adapter (`…Facts`) | Enrollment habla con otros módulos por sus consultas públicas (A-06) y se prueba con fakes en memoria. |
| Punto de extensión etiquetado (ADR 0012) | Reglas atómicas dentro de escrituras de otros módulos sin dependencia circular. |
| Repository | Escrituras por el agregado (A-08). Las listas van por `EnrollmentList`. |

## Modelo de amenazas (STRIDE)

| Amenaza | Vector | Mitigación | Verificado por |
|---------|--------|------------|----------------|
| **S**poofing | Inscribir o aprobar una excepción en nombre de otro. | Quien aprueba es siempre la cuenta de la sesión (`currentAccountId`), nunca un campo del formulario. | `EnrollStudentTest` › «la excepción la aprueba quien está en la sesión» |
| **T**ampering | Inscribir a un estudiante o usar una sección de otro plantel enviando su id. | RLS en `enrollments` y en las tablas de Students y AcademicStructure (S-01, MAT-NF02). El plantel sale solo del subdominio (S-02). Un id ajeno es invisible y da 404 (MAT-R13.1 – R13.3). | `EnrollmentIsolationTest` (`group=rls`); `TenantTableTest` |
| **T**ampering | Superar el cupo con dos peticiones a la vez, o dos inscripciones vigentes en el mismo año. | `CalendarLock` en toda escritura y el índice único parcial de vigentes. | `EnrollmentConcurrencyTest` (dos conexiones); `EloquentEnrollmentRepositoryTest` |
| **T**ampering | Cerrar el año mientras se inscribe, o inscribir en un año que se está cerrando. | El cierre y la inscripción toman el mismo `CalendarLock`; el guard cuenta tras el bloqueo. | `UnresolvedResultsGuardTest` |
| **R**epudiation | Negar un sobrecupo, una excepción de edad, una anulación o un cambio de grado. | Bitácora del plantel (SPEC-005) con motivo y cuenta, dentro de la transacción; la fila guarda quién y cuándo. | `EnrollmentAuditTest` |
| **I**nformation disclosure | Un docente ve fichas o listas de estudiantes ajenos a sus secciones. | `StudentVisibilityRule` en la lista y la ficha (404), y la misma visibilidad en `SectionRosterQuery`. | `StudentVisibilityTest` (docente, guía, coordinador, gestor) |
| **I**nformation disclosure | Datos de salud en listas, la bitácora o el diálogo de cierre. | Los DTOs de Enrollment no llevan salud; la bitácora solo ids, motivos y resultados (MAT-R12.2). | `EnrollmentAuditTest` › «sin datos de salud»; DTOs explícitos |
| **D**enial of service | El lote de 600 estudiantes bloquea el calendario. | Una inserción por lotes en una sola transacción; objetivo de menos de 10 s (MAT-NF04). | `NextYearPerformanceTest` (`group=perf`) |
| **E**levation of privilege | Un gestor sin `aprobar-excepciones-inscripcion` cambia de grado, inscribe fuera de rango o anula pasadas 48 horas. | Middleware en la ruta del cambio de grado; `EnrollmentAccess` en el caso de uso para todas. | `EnrollmentPermissionsTest`; tests de unidad de cada handler |
| **E**levation of privilege | Un docente con `ver-estudiantes` usa las rutas de escritura. | `permission:gestionar-inscripciones` en la ruta y en el caso de uso (S-04, MAT-NF01). | `EnrollmentPermissionsTest` |

## Errores

| Excepción de dominio | HTTP | Mensaje al usuario |
|----------------------|------|--------------------|
| `AlreadyEnrolled` (Conflict) | 409 | «El estudiante ya está inscrito en {grado} sección {letra} en este año.» |
| `NotEnrollable` (BusinessRuleViolation) | 422 | «El estudiante está retirado: reincorpóralo primero.» / «Está pendiente de egresar: solo cursa sus materias pendientes.» |
| `YearNotOpen` (BusinessRuleViolation) | 422 | «El año escolar está cerrado.» |
| `OverCapacity` (BusinessRuleViolation, campo `over_capacity_reason`) | 422 | «La sección tiene {n} inscritos y cupo {c}: indica el motivo para superarlo.» |
| `AgeOutOfRange` (BusinessRuleViolation, campo `age_exception`) | 422 | «Tiene {e} años; el rango de {grado} es {min} – {max}. Hace falta la excepción de un directivo.» |
| `ResultNotAllowed` (BusinessRuleViolation, campo `outcome` o `pending_subjects`) | 422 | Uno por regla (R06.2 – R06.7, R06.9). |
| `AnnulmentNotAllowed` (BusinessRuleViolation) | 422 | «Pasaron más de 48 horas: la anula un directivo.» / «Tiene resultado: no se puede anular.» |
| `AuthorizationDenied` | 403 | «No tienes permiso para gestionar inscripciones.» |
| `EnrollmentNotFound`, `StudentNotFound`, `SectionNotFound` (NotFound) | 404 | Respuesta genérica (R08.2, R13.3). |
| `BusinessRuleViolation` del cierre (AcademicCalendar) | 422 | «Hay {n} estudiantes sin resultado de prosecución.» |

## Estrategia de pruebas

- **Dominio (unit, `tests/Unit/Enrollment/Domain`):**
  - `Enrollment`: inscripción, cambio, fin, anulación y resultado, con sus
    invariantes;
  - `Occupancy`, `AgeCheck` (bordes de rango, la fecha de corte, el 29 de
    febrero y un grado sin regla), `PromotionRules` (cada nivel, pendientes
    0/1/2/3, grado siguiente y último grado) y `Annulment` (47 y 49 horas,
    con y sin notas, directivo).
- **Aplicación (unit, adaptadores en memoria):**
  - un test por handler, con fakes de `StudentFacts`, `StructureFacts`,
    `CalendarFacts`, permisos y un `CalendarLock` que registra que se tomó;
  - la propuesta del lote con todas las exclusiones;
  - `SectionScopedVisibility` con docente, guía, coordinador y gestor.
- **Infraestructura (integración, Postgres):**
  - el repositorio con el índice único → `AlreadyEnrolled`;
  - los `CHECK`;
  - `EnrollmentConcurrencyTest`: dos conexiones por el último lugar,
    `tests/Isolation`;
  - `EnrollmentIsolationTest` (`group=rls`);
  - los cuatro puntos de extensión con los módulos reales: un retiro termina
    la inscripción, y un fallo de la inscripción deshace el retiro (R05.5);
    el cierre rechazado; la sección con inscripciones; la ficha invisible
    para el docente;
  - las migraciones con `up`, `down` y `up`.
- **Feature (HTTP):**
  - cada ruta con permiso y sin él;
  - los mensajes de la tabla de errores;
  - la ficha con el historial;
  - el diálogo de cierre;
  - el menú.
- **Navegador:** axe y 360 px en «Inscripciones», la lista de una sección,
  «Prosecución» y el lote (MAT-NF06).
- **Rendimiento (`group=perf`):**
  - lote de 600 en menos de 10 s (NF04);
  - «¿puede ver a este estudiante?» en menos de 10 ms (p95), y la lista de
    un docente con 10 secciones en menos de 300 ms (NF05).
- **Arquitectura:**
  - Enrollment no importa la Infrastructure ni el Domain de otros módulos;
  - Students, AcademicStructure y AcademicCalendar no importan Enrollment;
  - solo `EnrollmentServiceProvider` etiqueta sus implementaciones
    (ADR 0012).

## Decisiones y alternativas

| Decisión | Alternativa descartada | Motivo |
|----------|------------------------|--------|
| 1. Puntos de extensión síncronos (ADR 0012) | Eventos después del commit; que los otros módulos llamen a Enrollment | Atomicidad de MAT-R05.5 y R16.2 sin dependencia circular. Sigue el precedente de `RemovalGuard`. |
| 2. Reutilizar el `CalendarLock` del plantel (ADR 0007) | Bloqueo por sección (`pg_advisory_xact_lock` por id) | El cupo, el cierre del año, la retirada de secciones y el cambio de cupo tienen que serializarse entre sí, y ese bloqueo ya lo comparten el calendario y la estructura. Por bloqueo por sección, el lote tendría que tomar decenas de bloqueos en orden. A escala de un plantel la espera es corta. |
| 3. Edades en `config/enrollment.php`, detrás del puerto `AgeRules` | Tabla global en Catalogs, editable desde el panel de plataforma (SPEC-014) | Cumple MAT-R03.6: se cambia la configuración, no el código. Un cambio pide despliegue, que es aceptable para una regla nacional que cambia poco. El puerto deja pasar a una tabla sin tocar el dominio. |
| 4. El resultado vive en la inscripción, más una tabla de materias pendientes | Agregado aparte `PromotionDecision` | El resultado es de una inscripción vigente al terminar el año, y las reglas de anulación y cambio dependen de él (R14.5): agregado único, una escritura. |
| 5. La visibilidad por ids que calcula Enrollment | Política RLS por cuenta; que Students lea las tablas de Enrollment | La RLS por cuenta exigiría llevar la cuenta a la sesión de la base de datos (ADR 0011). Leer tablas ajenas rompe A-06. Unos cientos de ids por docente caben en un `whereIn` con índice. |
| 6. Anulación = fin con motivo `annulled` | Borrado lógico con `deleted_at` | Un solo modelo de «terminada», que el índice de vigentes, el cupo y el historial ya entienden; la fila queda (MAT-R14.1, R14.6). |
| 7. Egreso derivado del resultado de `media_5` | Estado nuevo del estudiante en Students | Students no conoce grados. Si un día hace falta en Students (p. ej. en las listas), se expone como consulta de Enrollment. |
| 8. Inscripción en lote en dos pasos (propuesta y confirmación), revalidada en la transacción | Un paso | El gestor elige la sección de cada estudiante (R07.4). Entre los dos pasos la propuesta puede cambiar, y la transacción la revalida bajo el bloqueo. |

## Cambios en otras specs y módulos

- **SPEC-002 / IdentityAccess:** `Permission` gana `gestionar-inscripciones`
  y `aprobar-excepciones-inscripcion`, en el sembrado de Administrador y
  Director (IAM-R11.1).
- **SPEC-007 / AcademicCalendar:**
  - `SchoolYearLookup` gana `startOf`, `nextOf` y `lastTermClosed`;
  - punto de extensión `YearClosingGuard` en `CloseSchoolYearHandler`;
  - CAL-R02.4 gana la condición de MAT-R16.2.
- **SPEC-008 / AcademicStructure:**
  - `RemovalGuard::hasEnrollments`;
  - consultas públicas `GradesQuery` y `CurriculumQuery`;
  - se activan EAC-R02.6 y R07.3.
- **SPEC-009 / Students:**
  - puntos de extensión `WithdrawalHook`, en `WithdrawStudentHandler` y
    `ApproveTransferHandler`, y `StudentVisibilityRule`, en
    `ListStudentsQuery` y `ShowStudentQuery`;
  - consulta pública `EnrollableStudentsQuery`;
  - EST-R05.1 y R05.2 se limitan para Docente y Coordinador.
- **SPEC-013 / Workspace:** entrada de menú «Inscripciones»; el bloque de
  inscritos por sección usa `EnrolledCountsQuery`.

## Riesgos y plan de rollback

- **Tests de SPEC-009 que esperan que el docente vea a todos.** MAT-R08 los
  contradice. Es un cambio expreso de esta spec: se cambia su preparación
  (el docente con una sección e inscripciones) y, donde esperen ver a
  todos, su aserción. Se anotará test por test, como en SPEC-015
  (excepción expresa a Q-03).
- **Un punto de extensión que falla deja de retirar.** Es lo que pide
  MAT-R05.5, pero un error de Enrollment bloquearía el retiro. Mitigación:
  tests de integración con el módulo real y un mensaje de dominio claro.
- **El bloqueo del calendario en el lote.** Seiscientos estudiantes en una
  transacción con el bloqueo puesto. Mitigación: inserción por lotes y
  NF04; si no basta, troceo por grado con la misma revalidación.
- **Edades configuradas con errores.** `ConfigAgeRules` valida al arrancar
  (`min ≤ regular ≤ max`, grados existentes) y falla en el despliegue, no
  en una inscripción.
- **Rollback:**
  - las dos migraciones son nuevas, sin migración de datos: `down` borra las
    tablas y quita los permisos;
  - los puntos de extensión, sin Enrollment registrado, dejan los otros
    módulos como hoy;
  - revertir el PR deja a SPEC-007, 008 y 009 con el comportamiento
    anterior. Lo único que se pierde son los datos de inscripción.

## Notas de implementación

### PR A

- **MAT-R11.3, concesión persona por persona** (P-04, aprobado el
  2026-10-28). No existe una gestión de permisos por rol, así que el
  Administrador concede `gestionar-inscripciones` a un miembro concreto,
  como `editar-notas-lapso-cerrado`.
  - Los dos casos de uso comparten `DirectPermissionGrant`, y
    `SetClosedTermGradeEditingHandler` lo usa sin cambiar su
    comportamiento.
  - En la ficha del miembro hay una casilla nueva. Sus tests no cambian.
- **`end_note`.** Columna nueva de `enrollments`: el motivo de un cambio de
  sección o de grado queda en la inscripción que termina (MAT-R04.6).
- **Ids de cuenta sin clave foránea.** `registered_by`, `age_exception_by`,
  `annulled_by` y `outcome_recorded_by` son historia, como en la bitácora.
- **La ruta de una sección lleva el año:**
  `/inscripciones/{year}/secciones/{section}`. Las secciones se piden por
  año (`SectionsOfYearQuery`).
- **`YearOverviewQuery` sin año** abre el activo o, si no hay, el más
  reciente, con la lista de años para elegir (`CalendarFacts::years()`,
  sobre `ListSchoolYearsHandler`).
- **El aviso de edad** (MAT-R03.2) va en el mensaje de éxito: «Se inscribió
  al estudiante. Tiene 7 años; la edad regular de 3.er grado es 8.».
- **`EnrollmentConcurrencyTest`** (`tests/Isolation`) usa dos conexiones
  reales. Mientras una inscripción tiene el bloqueo del calendario, la otra
  no lo obtiene. Tras el commit lo obtiene y encuentra la sección llena.
  Usa una bitácora en memoria, porque la real es inmutable y estos tests
  confirman de verdad.
- **Riesgo pendiente:** borrar un año planificado que ya tenga inscripciones
  (p. ej. del lote del PR B) choca con la clave foránea `RESTRICT`. El PR B
  lo resuelve con el mismo punto de extensión que el cierre del año.

### PR B

- **`SchoolYearGuard` en lugar de `YearClosingGuard`.** Un solo punto de
  extensión del calendario para el cierre del año (MAT-R16.2) y para el
  borrado de un año planificado con inscripciones. Este segundo caso es el
  riesgo que dejó pendiente el PR A: ahora da un mensaje, no un error 500.
- **Resultado en la inscripción.** Columnas `outcome`, `outcome_recorded_at`
  y `outcome_recorded_by`; las materias pendientes van en
  `enrollment_pending_subjects`. Se reescriben al guardar, y una inscripción
  nueva sin resultado no las toca.
- **`PromotionRules::allowedOutcomes()`** da las opciones de la pantalla por
  nivel. «Perdió por inasistencia» no cabe en Educación Inicial (Q7, sin
  decidir), así que allí solo se ofrece «promovido».
- **`PromotionSheetQuery`** es la hoja de prosecución de una sección.
  Depende de `Subjects` (Catalogs, capa Application) para los nombres de las
  materias del pensum.
- **El lote:**
  - **Fecha:** la del día del lote, porque una inscripción no puede ser
    posterior a hoy (MAT-R01.6). Se hace antes de que empiece el año
    siguiente.
  - **Bitácora:** una sola entrada `enrollment.batch.enrolled` por lote, con
    cuántos estudiantes y en qué secciones (MAT-R12.1), en lugar de una por
    estudiante.
  - **Todo o nada sin depender de la base:** valida todas las inscripciones
    antes de guardar ninguna.
  - **Rendimiento:** quién ya está inscrito en el año siguiente se lee con
    una sola consulta. 600 estudiantes tardan unos 7 s, preparación incluida
    (MAT-NF04).

### PR C

- **Visibilidad:**
  - `StudentVisibilityRule` (Students) la implementa
    `SectionScopedVisibility`.
  - Sin nadie en sesión (consola, cola), la regla no restringe: lo que
    protege es el permiso del caso de uso, y no hay secciones con las que
    acotar.
  - `SectionRosterQuery` y `YearOverviewQuery` aplican la misma visibilidad.
  - `StudentVisibility` calcula una vez por instancia, que vive lo que la
    consulta que la usa.
- **MAT-NF05:**
  - el primer cálculo en caliente tarda unos 10 ms y, después, preguntar
    no cuesta nada;
  - la prueba exige menos de 50 ms (p95) para calcular, menos de 10 ms
    (p95) para preguntar y menos de 300 ms (p95) para la lista de un
    docente con 10 secciones;
  - descarta una llamada de calentamiento, porque la primera del proceso
    de tests carga clases.
- **«Inscritos» en el inicio:** está en las cifras de la estructura (ver
  SPEC-013).

### SPEC-011 (evaluación), 2026-10-28

- **Consulta pública `EnrollmentPeriodsQuery`:**
  - los periodos (sección, grado, desde y hasta) de los estudiantes de un
    año, sin las anuladas;
  - `ofEnrollment()`, de quién es una inscripción, en qué año y cuándo;
  - Evaluation decide con ellos a qué actividades aplica cada estudiante.
- **Punto de extensión `OutcomeProposer`** (ADR 0012), lo implementa
  Evaluation:
  - `PromotionSheetQuery` da la propuesta de cada estudiante, o lo que falta
    para proponerla;
  - «Prosecución» la muestra con «Confirmar propuesta», que guarda con
    `RecordResultHandler` sin cambios (EVA-R08.4).

  `OutcomeProposers` se enlaza en el contenedor, como las demás colecciones
  de puntos de extensión.
- **MAT-R14.3 se activa con las notas.** Evaluation registra
  `GradedEnrollmentCheck` bajo `GradedEnrollment::TAG`:
  - en Media cuenta una nota o NP en actividades de la sección mientras
    duró la inscripción;
  - en Primaria e Inicial, un literal o informe del año que no sea el
    «incompleto» del cierre.

### SPEC-016 (revisión y pendientes), 2026-10-30

- **`EnrollmentPeriodsQuery::withResult()`**, pública: quién ya tiene el
  resultado del año. Con él registrado, la revisión no cambia (RVP-R01.7).
- **Consulta pública `PendingSubjectsQuery`:**
  - las pendientes de los resultados de años anteriores, con el grado donde
    se reprobó cada una;
  - de los estudiantes inscritos en el año o pendientes de egresar y
    activos;
  - `EnrollmentList` gana `withPendingSubjects()` para ella.
