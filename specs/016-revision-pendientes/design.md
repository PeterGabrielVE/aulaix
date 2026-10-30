---
spec: 016-revision-pendientes
estado: aprobada
fecha: 2026-10-30
aprobada: 2026-10-30
requisitos: requirements.md
adr: 0012 (puntos de extensión), 0013 (dueño del plan, nuevo)
---

# Diseño — Revisión y materias pendientes

## Resumen

Todo va en el módulo `Evaluation`, sobre lo construido en SPEC-011.

- **La revisión** es una nota más (`GradeMark`: nota o NP) por estudiante,
  materia y año. `FinalAfterRevision` la combina con la definitiva, y la
  propuesta del resultado (EVA-R08) pasa a usar ese resultado.
- **Los grupos de pendientes** guardan solo su docente: (año, materia,
  grado de origen). Sus miembros se derivan cada vez de los resultados de
  prosecución (SPEC-010) y de lo que ya se aprobó, así que corregir un
  resultado o retirar a un estudiante no exige sincronizar nada.
- **El plan de un grupo** reutiliza el agregado de SPEC-011. El plan pasa a
  tener un dueño, sección o grupo (ADR 0013).
- **Enrollment** necesita dos cosas:
  - pregunta a Evaluation si un estudiante aún debe pendientes, para el
    egreso, por un punto de extensión nuevo (ADR 0012);
  - expone una consulta pública con las pendientes registradas en los
    resultados.

## Módulo y capas

```
src/Evaluation/
├── Domain/
│   ├── PlanOwner.php                VO: sección o grupo de pendientes (ADR 0013)
│   ├── ActivityAudience.php         interfaz: a quién aplica una actividad y quién está hoy
│   ├── SectionAudience.php · GroupAudience.php
│   ├── PendingGroup.php             agregado: (año, materia, grado de origen) y su docente
│   ├── Revision.php                 agregado: la revisión de un estudiante en una materia y año
│   ├── FinalAfterRevision.php       servicio: definitiva + revisión → aprobada / reprobada / falta revisión
│   ├── OwedPending.php              VO: (estudiante, materia, grado de origen, año de origen)
│   ├── PendingStanding.php          servicio: cursando / aprobada / reprobada de una pendiente
│   └── RevisionNotAllowed.php · PendingGroupNotFound.php · TeacherNotEligible.php
├── Application/
│   ├── Revision/ SaveRevisionHandler
│   ├── PendingGroups/ PendingGroupsQuery · AssignPendingTeacherHandler · PendingGroupSheetQuery
│   ├── PendingGroups/ PendingMembers (servicio: miembros derivados de un grupo)
│   ├── StudentPendings/ StudentPendingsQuery
│   ├── Hooks/ OwedPendingsCheck (implementa PendingSubjectsCheck de Enrollment)
│   └── (se amplían) SavePlanHandler · SaveActivityGradesHandler · GradebookQuery
│       · StudentScores · GradeOutcomeProposer · PendingGrades · SheetsQuery
└── Infrastructure/
    └── Persistence/ EloquentPendingGroupRepository · EloquentRevisionRepository

src/Enrollment/Application/
├── Port/PendingSubjectsCheck.php    punto de extensión nuevo (ADR 0012)
└── Roster/PendingSubjectsQuery.php  consulta pública: pendientes registradas en los resultados
```

## Modelo de dominio

| Elemento | Tipo | Invariantes / responsabilidad |
|----------|------|-------------------------------|
| `PlanOwner` | Value Object | `section(id)` o `pendingGroup(id)`, nunca los dos. Lo guarda `EvaluationPlan` en lugar de `sectionId` (ADR 0013). |
| `ActivityAudience` | Interfaz de dominio | `isCurrent(student, today)` y `applies(student, date)`. `SectionAudience` usa los periodos de inscripción, como en SPEC-011 (NA incluido). `GroupAudience` usa los miembros del grupo: a un miembro le aplica toda actividad. `ActivityGrades::record()` la recibe en vez de los periodos. |
| `PendingGroup` | Agregado | (año, materia, grado de origen de Media General), único. `assignTeacher(account)`: una persona por grupo, o ninguna (RVP-R03.4). |
| `Revision` | Agregado | Una por estudiante, materia, año y dueño (sección o grupo). Su `GradeMark` es una nota de 1 a 20 o NP (RVP-R01.2, R01.3). `record()` exige la definitiva reprobada (R01.4), el tercer lapso cerrado (R01.5) y que el resultado no esté registrado (R01.7). |
| `FinalAfterRevision` | Servicio de dominio | Con una `FinalScore` y una `Revision` opcional: |
| | | - si la definitiva no está reprobada, la misma definitiva; |
| | | - si está reprobada y no hay revisión, «falta revisión»; |
| | | - con revisión de 10 o más, aprobada con la nota de la revisión redondeada (Q3, RVP-R02.1); |
| | | - con menos, o NP, reprobada con su definitiva (R02.2). |
| `OwedPending` | Value Object | Una pendiente de un estudiante: materia, grado de origen y año de origen (el del resultado de SPEC-010). |
| `PendingStanding` | Servicio de dominio | El estado de una pendiente en un año: cursando, aprobada (definitiva tras revisión ≥ 10, RVP-R05.2) o reprobada (tras su revisión, con el tercer lapso cerrado). Una pendiente se debe hasta que se aprueba, aunque el estudiante repita (Q2). |
| `OutcomeProposal` (se amplía) | Servicio de dominio | Recibe las definitivas tras revisión y el estado de las pendientes del año: |
| | | - una revisión que falta, o una pendiente cursando, no deja proponer (R02.4, R05.4); |
| | | - una pendiente reprobada propone «repite» y dice cuál (R05.3); |
| | | - las nuevas reprobadas siguen la regla de 1 o 2 y 3 o más (Q4). |

## Casos de uso (Application)

Todos los de escritura siguen el patrón de SPEC-011:
- comprueban el permiso y quién escribe (`EvaluationAccess`, S-04);
- comprueban que el lapso o el año admitan cambios;
- abren la transacción, guardan y registran en la bitácora.

| Caso de uso | Entrada | Salida | Criterios |
|-------------|---------|--------|-----------|
| `SaveRevisionHandler` | año, dueño (sección o grupo), materia, `{estudiante → nota \| "NP" \| null}` | — | RVP-R01.1 – R01.8, R02.1, R02.2, R05.1, R07.1, R07.2 |
| `GradebookQuery` (se amplía) | — | por estudiante: definitiva, revisión y definitiva tras revisión | RVP-R02.5 |
| `GradeOutcomeProposer` (se amplía) | — | propuesta con revisiones y pendientes | RVP-R02.3, R02.4, R05.3, R05.4 |
| `PendingMembers` | año | los miembros de cada grupo: `OwedPending` aún no aprobadas de estudiantes con inscripción vigente en el año o pendientes de egresar | RVP-R03.1 – R03.3 |
| `PendingGroupsQuery` | año | grupos con materia, grado de origen, docente y miembros; los sin docente, señalados | RVP-R03.1, R03.2, R03.6 |
| `AssignPendingTeacherHandler` | año, materia, grado de origen, cuenta | — (crea el grupo si no existe) | RVP-R03.4, R03.5, R07.1 |
| `SavePlanHandler` · `SaveActivityGradesHandler` (se amplían) | dueño grupo | — | RVP-R04.1, R04.2, R04.4, R04.5, R07.1 |
| `PendingGroupSheetQuery` | grupo, lapso | planilla del grupo: actividades, notas, nota del lapso, definitiva, revisión y si quedó aprobada | RVP-R04.3, R04.6, R05.1, R05.2 |
| `PendingGrades` (se amplía) | año, lapso | también las notas faltantes de los grupos; la excepción rellena con NP | RVP-R04.7 |
| `OwedPendingsCheck` (implementa `PendingSubjectsCheck` de Enrollment) | estudiante | ¿debe pendientes? | RVP-R05.5 |
| `StudentPendingsQuery` | estudiante | pendientes con grado y año de origen, estado y definitiva tras revisión | RVP-R05.2, R06.1, R06.2 |
| Todos (RLS) | — | solo lo del plantel activo; un id de otro plantel no existe | RVP-R08.1 – R08.3 |
| `SheetsQuery` (se amplía) | — | «Notas» lista los grupos del docente asignado | RVP-R04.6 |

## Puertos y adaptadores

| Puerto (interfaz) | Capa | Adaptador | Notas |
|-------------------|------|-----------|-------|
| `PendingGroupRepository` · `RevisionRepository` | Domain | Eloquent | RLS (S-01). |
| `EvaluationPlanRepository` (se amplía) | Domain | Eloquent | `planOf(owner, materia, lapso)`; los planes de un grupo. |
| `EnrollmentFacts` (se amplía) | Application | `EnrollmentEnrollmentFacts` | `owedPendings(año)`, sobre `PendingSubjectsQuery` de Enrollment (nueva, pública): las pendientes de los resultados, de estudiantes con inscripción vigente en el año o pendientes de egresar. `resultsRecorded(año, estudiantes)` (R01.7). |
| `TeachingFacts` (se amplía) | Application | `AcademicStructureTeachingFacts` | `isActiveTeacher(cuenta)` (R03.5), sobre `StaffDirectory` de AcademicStructure, que ya valida las asignaciones de SPEC-008. |
| `EvaluationPermissions` (se amplía) | Application | `GateEvaluationPermissions` | `canAssignPendingTeachers()`: `gestionar-estructura` (Q1). |
| `PendingSubjectsCheck` (nuevo) | Application de Enrollment (etiqueta) | `OwedPendingsCheck` | ADR 0012. `Enrollment::waitsToGraduate()` y `graduates()` lo consultan mediante los casos de uso: quien estaba pendiente de egresar y ya no debe nada aparece como egresado. |

## Adaptadores de entrada (`app/`)

| Ruta | Controlador | Permiso | Caso de uso |
|------|-------------|---------|-------------|
| `PUT /notas/{year}/secciones/{section}/materias/{subject}/revisiones` | `RevisionController@section` | `gestionar-calificaciones` | `SaveRevisionHandler` |
| `PUT /notas/pendientes/{group}/revisiones` | `RevisionController@group` | `gestionar-calificaciones` | `SaveRevisionHandler` |
| `GET /notas/pendientes` | `PendingGroupController@index` | `gestionar-estructura` | `PendingGroupsQuery` |
| `PUT /notas/pendientes/docente` | `PendingGroupController@teacher` | `gestionar-estructura` | `AssignPendingTeacherHandler` |
| `GET /notas/pendientes/{group}/lapsos/{term}` | `PendingGroupController@sheet` | `ver-calificaciones` | `PendingGroupSheetQuery` |
| `PUT /notas/pendientes/{group}/lapsos/{term}/plan` | `PendingGroupController@plan` | `gestionar-calificaciones` | `SavePlanHandler` |
| `PUT /notas/actividades/{activity}` (existe) | `GradesController@grades` | `gestionar-calificaciones` | `SaveActivityGradesHandler` (el dueño sale de la actividad) |

Además:
- **La planilla de la sección**, en el tercer lapso cerrado, gana la columna
  «Revisión» para las definitivas reprobadas, editable por su docente.
- **La ficha del estudiante** (SPEC-009) recibe `pendings`
  (`StudentPendingsQuery`). El controlador ya comprobó que la persona puede
  ver la ficha (R06.2).
- **«Prosecución»** muestra la propuesta con las revisiones, sin cambios en
  su pantalla.
- **«Notas»** muestra los grupos del docente asignado y, a los gestores, el
  enlace «Grupos de pendientes».

## Datos

Migración `create_revision_and_pending_tables`, con RLS en las tablas
nuevas (S-01):

- **`pending_groups`**
  - **Columnas:**
    - `id`, `belongsToInstitution()`;
    - `school_year_id` y `subject_id`, con `RESTRICT`;
    - `origin_grade`;
    - `teacher_account_id`, nulo y con clave foránea a `users`, como
      `teaching_assignments`.
  - **Restricciones:**
    - único `(school_year_id, subject_id, origin_grade)`;
    - `CHECK origin_grade IN ('media_1' … 'media_5')`.
- **`evaluation_plans`** (cambia, ADR 0013):
  - `section_id` pasa a nulo;
  - `pending_group_id`, nulo y con `CASCADE`;
  - `CHECK num_nonnulls(section_id, pending_group_id) = 1`;
  - el único `evaluation_plans_one_per_sheet` pasa a parcial, `WHERE
    section_id IS NOT NULL`, y se añade `(pending_group_id, term) WHERE
    pending_group_id IS NOT NULL`.
- **`grade_revisions`**
  - **Columnas:**
    - `id`, `belongsToInstitution()`;
    - `school_year_id`, `subject_id` y `student_id`, con `RESTRICT`;
    - `section_id` o `pending_group_id`, exactamente uno;
    - `score` (centésimas, nulo) y `not_presented`.
  - **Restricciones:**
    - el mismo `CHECK` de nota o NP que `activity_grades`, con `score IS
      NOT NULL`;
    - único `(school_year_id, subject_id, student_id) WHERE section_id IS
      NOT NULL`;
    - único `(pending_group_id, student_id) WHERE pending_group_id IS NOT
      NULL`.

**Lo que no se guarda:**
- los miembros de un grupo: se derivan de los resultados (SPEC-010) menos
  lo ya aprobado;
- el estado de una pendiente: se deriva de la definitiva y la revisión del
  grupo de cada año.

## Patrones aplicados

| Patrón | Fuerza que resuelve (C-05) |
|--------|----------------------------|
| Value Object `PlanOwner` + `ActivityAudience` (Strategy) | Las reglas del plan y de las notas son las mismas para sección y grupo. Solo cambia a quién aplica una actividad (ADR 0013). |
| Agregado `PendingGroup` con miembros derivados | El docente es lo único que el grupo decide. Derivar a los miembros evita sincronizarlos al corregir un resultado o al retirar a un estudiante (R03.3). |
| Servicio de dominio `FinalAfterRevision` y `PendingStanding` | Reglas que combinan dos agregados (notas y revisión) y se prueban solas con tablas de casos. |
| Punto de extensión `PendingSubjectsCheck` (ADR 0012) | Enrollment decide el egreso, pero lo que se aprobó lo sabe Evaluation, que ya depende de Enrollment. |

## Modelo de amenazas (STRIDE)

| Amenaza | Vector | Mitigación | Verificado por |
|---------|--------|------------|----------------|
| **S**poofing | Registrar una revisión o notas de un grupo como otro docente. | La cuenta es la de la sesión; el caso de uso compara con el docente de la sección o el asignado al grupo. | `RevisionTest`, `PendingGroupGradesTest` |
| **T**ampering | Un docente carga la revisión de una materia ajena, o notas en un grupo que no tiene asignado, por id. | El dueño sale del plan o de la ruta resuelta bajo RLS; `ensureTeaches` o `ensureAssigned` en cada guardado (RVP-NF01). | `RevisionPermissionsTest` |
| **T**ampering | Cambiar una revisión después de registrar el resultado, para «aprobar» sin que el gestor lo vea. | R01.7: con el resultado registrado, la revisión no cambia; primero se corrige el resultado (MAT-R06). | `RevisionTest` |
| **R**epudiation | Negar haber cambiado una revisión o el docente de un grupo. | Bitácora con antes y después y quién (R07). | `RevisionAuditTest` |
| **I**nformation disclosure | Un docente ve las pendientes o revisiones de estudiantes ajenos. | Planilla del grupo solo para el asignado, el coordinador del grado de origen y los gestores; lo demás, 404. Pendientes en la ficha solo si se puede ver la ficha. | `PendingGroupVisibilityTest` |
| **D**enial of service | Guardar revisiones o notas de grupos enormes. | Hasta 60 por guardado (FormRequest) y `throttle`, como SPEC-011. | FormRequest |
| **E**levation of privilege | Un docente se asigna a sí mismo un grupo. | Asignar exige `gestionar-estructura` en la ruta y en el caso de uso (Q1). | `PendingGroupPermissionsTest` |

## Errores

| Excepción de dominio | HTTP | Mensaje al usuario |
|----------------------|------|--------------------|
| `RevisionNotAllowed::notFailed` | 422 | «La definitiva no está reprobada: no lleva revisión.» |
| `RevisionNotAllowed::tooEarly` | 422 | «La revisión se registra con el tercer lapso cerrado.» |
| `RevisionNotAllowed::resultRecorded` | 422 | «El estudiante ya tiene su resultado del año: corrígelo primero en Prosecución.» |
| `GradeNotValid` (existe) | 422 | «La nota debe estar entre 1 y 20, con hasta dos decimales.» |
| `NotEditable` (existe) | 422 | «El año escolar está cerrado: sus notas no cambian.» |
| `TeacherNotEligible` | 422 | «Solo se asigna a un miembro activo con el rol Docente.» |
| `PendingGroupNotFound` | 404 | «No encontramos ese grupo de pendientes.» |
| `AuthorizationDenied` (existe) | 403 | «No dictas esa materia en esa sección.» / «No tienes asignado ese grupo de pendientes.» |

## Estrategia de pruebas

- **Dominio (unit):**
  - `FinalAfterRevision` y `PendingStanding`, con tablas de casos (10, 9,99,
    NP, sin revisión, incompleta);
  - `OutcomeProposal` con revisiones, pendientes cursando, aprobadas y
    reprobadas, y las nuevas reprobadas (Q4);
  - `GroupAudience`, `SectionAudience` y `PlanOwner`.
- **Aplicación (unit, adaptadores en memoria):**
  - `SaveRevisionHandler`: quién, cuándo, resultado registrado, bitácora;
  - `PendingMembers`: retirados, pendientes de egresar y aprobadas en un año
    anterior;
  - `AssignPendingTeacherHandler`;
  - planes y notas con dueño grupo;
  - la propuesta.
- **Infraestructura (integración, Postgres):**
  - el esquema: `CHECK`, índices parciales, `up`, `down` y `up`;
  - los repositorios;
  - `PendingSubjectsQuery` de Enrollment;
  - aislamiento (`rls`);
  - el egreso con las dos módulos reales.
- **Feature (HTTP):**
  - revisión en la planilla;
  - grupos y asignación;
  - planilla del grupo;
  - permisos (docente ajeno, coordinador, gestor);
  - ficha con pendientes.
- **Navegador:** axe a 360 px en las pantallas nuevas.
- **Rendimiento (`perf`):** planilla del grupo 40 × 10 por debajo de 300 ms y
  propuesta de una sección de 40 por debajo de 500 ms (p95).
- **Refactor (Q-03):** los tests de SPEC-011 no cambian su lógica; solo cómo
  se construye un plan (con `PlanOwner::section`).

## Decisiones y alternativas

| Decisión | Alternativa descartada | Motivo |
|----------|------------------------|--------|
| 1. El plan tiene un dueño, sección o grupo (ADR 0013) | Tablas propias para pendientes; el grupo como sección | Mismas reglas sin duplicarlas; un grupo no tiene grado, letra ni cupo. |
| 2. Miembros del grupo derivados | Guardar a los miembros al registrar el resultado | Corregir un resultado o retirar a un estudiante no exige sincronizar. El coste es una consulta por planilla. |
| 3. Estado de una pendiente derivado | Guardar «aprobada» al cerrar el año | Mismo motivo. La aprobación sale de las notas y la revisión, que ya están guardadas. |
| 4. Una pendiente se debe hasta aprobarla, aunque el estudiante repita (Q2 extendida) | Borrar la pendiente al repetir | Con la regla de Q2, el pendiente de egresar la vuelve a cursar. Aplicar lo mismo a todos evita un caso especial. *Aprobada (2026-10-30).* |
| 5. A un miembro le aplica toda actividad del grupo (sin NA) | Aplicar por fecha de inscripción, como en la sección | Pertenecer al grupo no depende de una inscripción con fecha. Quien llega tarde al año tiene sus actividades anteriores sin nota, que el docente puede dejar vacías o poner NP. *Aprobada (2026-10-30).* |
| 6. La revisión de la sección la carga el docente que dicta la materia **hoy** en esa sección | El que la dictaba al cerrarse el tercer lapso | Las asignaciones de SPEC-008 no guardan historia. |
| 7. La bitácora de una pendiente aprobada o reprobada, y de un egreso, son las entradas de las notas y la revisión que lo producen (RVP-R07.1) | Un evento propio «pendiente aprobada» o «egresó» | El estado se deriva, así que no hay una escritura propia que registrar. Cada nota y revisión ya queda con su antes, su después y quién. El egreso del pendiente de egresar queda en la de la revisión o la nota que aprobó su última pendiente. *Aprobada (2026-10-30).* |

## Cambios en otras specs y módulos

- **SPEC-010 / Enrollment:**
  - consulta pública `PendingSubjectsQuery`;
  - punto de extensión `PendingSubjectsCheck`: el pendiente de egresar que
    ya no debe nada se muestra como egresado (MAT-R15).
- **SPEC-011 / Evaluation:**
  - `PlanOwner`, `ActivityAudience` y la revisión en la planilla;
  - la propuesta (EVA-R08) y el cierre del lapso (EVA-R12) cuentan
    revisiones y grupos.
- **SPEC-009 / ficha:** las pendientes del estudiante, compuestas en `app/`.
- **SPEC-013 / Workspace:** «Grupos de pendientes» como acceso desde
  «Notas». No es una entrada nueva del menú.

## Riesgos y plan de rollback

- **Refactor del plan** (`sectionId` → `owner`) en un módulo recién
  terminado. *Mitigación:* se hace primero, en su propia tarea, con la suite
  de SPEC-011 en verde sin cambiar su lógica.
- **Migración sobre `evaluation_plans` con datos.** Solo cambia
  restricciones e índices, y añade una columna nula; ningún dato se mueve.
  *Rollback:* el `down` borra los planes de grupos (con sus actividades y
  notas, por `CASCADE`), recrea el índice único completo y devuelve `NOT
  NULL` a `section_id`. Se prueba `up`, `down` y `up`.
- **Coste de derivar** miembros y estados de pendientes en cada planilla y
  propuesta. *Mitigación:* una consulta de Enrollment por año y una lectura
  de notas por grupo; está cubierto por los tests de rendimiento (NF04).
- **Revisión cambiada tras el resultado.** La bloquea R01.7. Si el gestor
  corrige el resultado, la propuesta vuelve a calcularse.
