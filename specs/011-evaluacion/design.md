---
spec: 011-evaluacion
estado: implementada
fecha: 2026-10-28
aprobada: 2026-10-28
requisitos: requirements.md
adr: 0012 (puntos de extensión síncronos)
---

# Diseño — Evaluación

## Resumen

- **Módulo nuevo `Evaluation`.** Tres agregados:
  - `EvaluationPlan`: las actividades de una materia, sección y lapso, con
    su porcentaje;
  - `ActivityGrades`: las notas de una actividad;
  - `StudentMarks`: los literales y los informes de un estudiante en un
    año.

  El cálculo de la nota del lapso y de la definitiva es un servicio de
  dominio con aritmética entera exacta, en centésimas.
- **Qué actividades cuentan para un estudiante.** Las de la sección en que
  estaba inscrito en la fecha de cada actividad (SPEC-010). Las demás son
  NA. Así se resuelven a la vez la incorporación a mitad de lapso
  (EVA-R02.8) y el cambio de sección (EVA-R02.7) sin guardar NA.
- **Cómo se conecta con otros módulos** (ADR 0012, puntos de extensión):
  - SPEC-010 pregunta si una inscripción tiene notas y recibe el resultado
    propuesto;
  - SPEC-008 pregunta qué materias tienen notas;
  - SPEC-007 no cierra un lapso con notas pendientes, salvo por excepción.

  Evaluation depende de esos módulos; ninguno depende de Evaluation.

## Módulo y capas

```
src/Evaluation/
├── Domain/
│   ├── EvaluationPlan.php          agregado: actividades de (sección, materia, lapso)
│   ├── Activity.php · ActivityId.php
│   ├── Weight.php                  porcentaje en centésimas (0 < w ≤ 10000)
│   ├── Score.php                   nota en centésimas, 100 – 2000
│   ├── GradeMark.php               Score | NotPresented | Incomplete
│   ├── ActivityGrades.php          agregado: notas de una actividad por estudiante
│   ├── TermScore.php               servicio: nota del lapso (ponderación, NA, redondeo)
│   ├── FinalScore.php              servicio: definitiva (promedio de lapsos)
│   ├── Rounding.php                entero más cercano, ,5 hacia arriba, sobre racionales
│   ├── Literal.php                 A – E
│   ├── StudentMarks.php            agregado: literales (3 + final) e informes (3) de un estudiante en un año
│   ├── OutcomeProposal.php         servicio: resultado propuesto por nivel (EVA-R08)
│   └── Exceptions: PlanNotValid, GradeNotValid, NotEditable, NoPlan, …
├── Application/
│   ├── SavePlan/ · SaveActivityGrades/ · SaveLiteral/ · SaveFinalLiteral/ · SaveReport/
│   ├── Gradebook/ (GradebookQuery, MarksSheetQuery, PendingGradesQuery)
│   ├── Hooks/ GradedEnrollmentCheck, SubjectsWithGrades, PendingGradesGuard, GradeOutcomeProposer
│   ├── Port/ EvaluationPermissions, EnrollmentFacts, TeachingFacts, CalendarFacts
│   └── EvaluationAccess.php · EvaluationAudit.php
└── Infrastructure/
    ├── EvaluationServiceProvider.php
    ├── GateEvaluationPermissions.php
    ├── EnrollmentEnrollmentFacts.php · AcademicStructureTeachingFacts.php · AcademicCalendarFacts.php
    └── Persistence/ Eloquent…Repository, EloquentGradebook
```

## Modelo de dominio

| Elemento | Tipo | Invariantes / responsabilidad |
|----------|------|-------------------------------|
| `EvaluationPlan` | Agregado | Una por (año, sección, materia, lapso). Actividades con nombre no vacío, fecha dentro del lapso y `Weight` > 0. La suma es exactamente 10000 (100,00 %) (EVA-R01.1 – R01.3). Una sola actividad con el 100 % es válida (R01.7). `replace()` dice qué actividades con notas se quitarían, y exige confirmarlo (R01.5). |
| `Weight` | Value Object | Centésimas de punto porcentual, entero > 0. Se escribe con hasta dos decimales. |
| `Score` | Value Object | Centésimas, entero de 100 a 2000 (de 1,00 a 20,00), con hasta dos decimales en la entrada (EVA-R02.2). Nunca `float`. |
| `GradeMark` | Value Object | `Score`, `NotPresented` (vale 100 en el cálculo, EVA-R02.3) o `Incomplete`, que solo deja el cierre por excepción en literales e informes. |
| `ActivityGrades` | Agregado | Notas de una actividad por estudiante. Solo acepta estudiantes a los que la actividad aplica (inscritos en esa sección en su fecha, EVA-R02.5, R02.8). Dejar sin nota es posible (R02.4). |
| `TermScore` | Servicio de dominio | Para un estudiante y un lapso, con sus actividades aplicables y sus notas: |
| | | - una aplicable sin nota → incompleta (R03.2); |
| | | - ninguna aplicable → «no aplica» (R03.7); |
| | | - si no, `Σ(nota × peso) / Σ(peso)`, que reparte el 100 % al faltar actividades (R03.6), redondeada con `Rounding` (R03.1). |
| `FinalScore` | Servicio de dominio | Promedio de las notas de lapso que aplican, redondeado. Si falta alguna, incompleta (R03.3, R03.4, R03.7). Reprobada si es menor que 10 (R03.5). |
| `Rounding` | Servicio de dominio | `round(n/d)` con ,5 hacia arriba y enteros: `(2n + d) div (2d)` para n, d > 0. Exacto (EVA-NF03). |
| `StudentMarks` | Agregado | Por estudiante y año: tres literales con descripción, el final y tres informes. Literal A – E (R04.2); el final, solo con el tercer lapso cerrado (R04.4); informe de 1 a 5000 caracteres (R05.2). `incomplete()` para la excepción del cierre (R12.3). |
| `OutcomeProposal` | Servicio de dominio | Media: con todas las definitivas completas, cuenta las reprobadas → promovido, con pendientes (esas materias) o repite. Primaria: literal final E → repite; A – D → promovido. Inicial: promovido. Si falta algo, sin propuesta y lo que falta (EVA-R08.1 – R08.3, R08.5). |
| Aplicabilidad | Regla de dominio | Una actividad de la sección S y fecha F aplica a un estudiante si tenía una inscripción vigente en S en F (periodos de SPEC-010). Las demás, NA. Tras un cambio de grado, las del grado anterior quedan solo para consulta (R09.3). |

## Casos de uso (Application)

Todos los de escritura:
- comprueban el permiso y la asignación (`EvaluationAccess`, S-04);
- comprueban que el lapso sea editable (`TermEditabilityQuery`, CAL-R05) y
  que esté abierto o cerrado, no sin abrir (R06.4);
- abren la transacción, guardan y registran en la bitácora.

| Caso de uso | Entrada | Salida | Criterios |
|-------------|---------|--------|-----------|
| `SavePlanHandler` | año, sección, materia, lapso, actividades, `confirmRemovals` | — | EVA-R01.1 – R01.8, R06.1, R07.1, R10.1 |
| `SaveActivityGradesHandler` | actividad, `{estudiante → nota \| "NP" \| null}` | — | EVA-R02.1 – R02.8, R06.1 – R06.4, R07.1, R10.1, R06.3 |
| `SaveLiteralHandler` · `SaveFinalLiteralHandler` | sección, estudiante, lapso o final, literal, descripción | — | EVA-R04.1 – R04.5, R07.2 |
| `SaveReportHandler` | sección, estudiante, lapso, texto | — | EVA-R05.1 – R05.3, R07.2, R10.2 |
| `GradebookQuery` | año, sección, materia, lapso | planilla: actividades, notas (NA incluidas), nota del lapso y definitivas | EVA-R03.*, R07.3 – R07.6 |
| `MarksSheetQuery` | año, sección | literales o informes de la sección | EVA-R04, R05, R07 |
| `PendingGradesQuery` | año, lapso | pendientes por docente, sección, materia, actividad | EVA-R12.1, R12.5 |
| `PendingGradesGuard` (implementa `TermClosingGuard` de AcademicCalendar) | año, lapso, excepción? | cuenta / rellena NP e «incompleto» | EVA-R12.2 – R12.4 |
| `GradeOutcomeProposer` (implementa `OutcomeProposer` de Enrollment) | inscripciones | propuesta por inscripción | EVA-R08.1 – R08.5 |
| `GradedEnrollmentCheck` (implementa `GradedEnrollment` de Enrollment) | inscripción | ¿tiene notas? | EVA-R09.1 |
| `SubjectsWithGrades` (implementa `RemovalGuard::subjectsInUse` de AcademicStructure) | año, grado, materias | materias con notas | EVA-R09.2 |

## Puertos y adaptadores

| Puerto (interfaz) | Capa | Adaptador | Notas |
|-------------------|------|-----------|-------|
| `EvaluationPlanRepository` · `ActivityGradesRepository` · `StudentMarksRepository` | Domain | Eloquent | |
| `Gradebook` | Application | `EloquentGradebook` | Lado de lectura (A-08): la planilla en dos consultas. |
| `EvaluationPermissions` | Application | `GateEvaluationPermissions` | `canGrade` (gestionar-calificaciones), `canView` (ver-calificaciones), `seesAll` (gestionar-estudiantes), `canEditClosedTerms`, `currentAccountId`. |
| `TeachingFacts` | Application | `AcademicStructureTeachingFacts` | `TeachingQuery::teaches`, `guides(account, section)` (nuevo, público), grados coordinados, pensum y nivel de la sección. |
| `EnrollmentFacts` | Application | `EnrollmentEnrollmentFacts` | Consulta pública nueva de Enrollment, `EnrollmentPeriodsQuery`: los periodos (sección, grado, desde, hasta) de los estudiantes de un año. |
| `CalendarFacts` | Application | `AcademicCalendarFacts` | `TermEditabilityQuery`, fechas y estado de cada lapso. |
| `TermClosingGuard` (nuevo) | Application de AcademicCalendar (etiqueta) | `PendingGradesGuard` | ADR 0012. `CloseTermHandler` lo consulta tras el bloqueo; con excepción le pide rellenar. |
| `OutcomeProposer` (nuevo) | Application de Enrollment (etiqueta) | `GradeOutcomeProposer` | ADR 0012. `PromotionSheetQuery` muestra la propuesta; el gestor confirma con `RecordResultHandler`, sin cambios. |
| `GradedEnrollment` (existe) · `RemovalGuard` (existe) | Enrollment · AcademicStructure | `GradedEnrollmentCheck` · `SubjectsWithGrades` | |

## Adaptadores de entrada (`app/`)

| Ruta | Controlador | Permiso | Caso de uso |
|------|-------------|---------|-------------|
| `GET /notas` | `GradesController@index` | `ver-calificaciones` | Lista de mis planillas o, para gestores, de todo el año |
| `GET /notas/{year}/secciones/{section}/materias/{subject}/lapsos/{term}` | `GradesController@sheet` | `ver-calificaciones` | `GradebookQuery` |
| `PUT /notas/.../plan` | `GradesController@plan` | `gestionar-calificaciones` | `SavePlanHandler` |
| `PUT /notas/actividades/{activity}` | `GradesController@grades` | `gestionar-calificaciones` | `SaveActivityGradesHandler` |
| `GET /notas/{year}/secciones/{section}/literales` | `MarksController@show` | `ver-calificaciones` | `MarksSheetQuery` |
| `PUT /notas/{year}/secciones/{section}/estudiantes/{student}` | `MarksController@update` | `gestionar-calificaciones` | `SaveLiteral…` / `SaveReport…` |

Además:
- **Cierre del lapso:** `TermController@close` recibe `exception_justification`.
  El diálogo de cierre de «Año escolar» recibe `pendingGrades`
  (`PendingGradesQuery`).
- **Prosecución:** la pantalla de SPEC-010 muestra la propuesta y un botón
  «Confirmar propuesta».
- **«Mis asignaciones»** enlaza cada clase a su planilla.
- **Menú:** entrada «Notas» con `gestionar-calificaciones` o
  `gestionar-estudiantes`. El Representante también tiene
  `ver-calificaciones`, pero no tendrá entrada: su vista es el portal
  (F2-07).
- **La planilla** es una tabla con un campo por casilla, recorrible con
  Tab y las flechas, que guarda por actividad (EVA-NF05).

## Datos

Migración `create_evaluation_tables`, con RLS en todas las tablas (S-01):

- **`evaluation_activities`**
  - **Columnas:**
    - `id`, `belongsToInstitution()`;
    - `school_year_id`, `section_id` y `subject_id`, las tres con `RESTRICT`;
    - `term` (1 – 3);
    - `name`, `date`, `weight` (entero, centésimas), `position`.
  - **Restricciones:** `CHECK weight > 0`.
  - **Índices:** `(section_id, subject_id, term)`.
- **`activity_grades`**
  - **Columnas:**
    - `id`, `belongsToInstitution()`;
    - `activity_id` (`CASCADE`), `student_id` (`RESTRICT`);
    - `score` (entero, centésimas, nulo) y `not_presented` (bool).
  - **Restricciones:**
    - único `(activity_id, student_id)`;
    - `CHECK (not_presented AND score IS NULL) OR (NOT not_presented AND score BETWEEN 100 AND 2000)`.
- **`student_marks`**
  - **Columnas:**
    - `id`, `belongsToInstitution()`;
    - `student_id`, `school_year_id`;
    - `period` (`'1'`, `'2'`, `'3'` o `'final'`) y `kind` (`literal` o `report`);
    - `literal` (char(1), nulo), `description` (texto, nulo) e `incomplete` (bool).
  - **Restricciones:**
    - único `(student_id, school_year_id, period, kind)`;
    - `CHECK` de literal A – E;
    - `CHECK` de longitud ≤ 5000.
- **Permisos:** `gestionar-calificaciones` y `ver-calificaciones` ya existen.
  No hace falta migración de permisos.

> **Cambio en la implementación (P-04), aprobado el 2026-10-28:** tabla
> `evaluation_plans`.
>
> - **Columnas:** `id`, `belongsToInstitution()`, `school_year_id`,
>   `section_id` y `subject_id` (`RESTRICT`), `term`, `timestamps`.
> - **Restricción:** único `(school_year_id, section_id, subject_id, term)`.
> - **Cambio en `evaluation_activities`:** gana `plan_id` (`CASCADE`) en
>   lugar de repetir año, sección, materia y lapso.
>
> Motivos:
>
> 1. **Concurrencia.** Sin una fila por plan, dos primeros guardados
>    simultáneos del mismo plan no ven ninguno, y ambos insertan sus
>    actividades: el plan sumaría 200 %. Con el índice único, el segundo
>    choca y responde 409, como las inscripciones (MAT-NF03). Al editar, el
>    repositorio bloquea la fila del plan (`FOR UPDATE`).
> 2. **Identidad del plan.** La bitácora (`evaluation_plan`, EVA-R11.2)
>    necesita un id estable, y hoy no hay ninguno: el de una actividad
>    cambia cuando se quita.
>
> Fuera de esto, el dominio, los casos de uso y los tests no cambian:
> `EvaluationPlan` ya tiene `id`.

**Por qué no se guarda NA:** se deriva de los periodos de inscripción. Si
una inscripción cambia (anulación, cambio de sección), NA se recalcula sin
sincronizar nada.

## Patrones aplicados

| Patrón | Fuerza que resuelve (C-05) |
|--------|----------------------------|
| Aggregate (`EvaluationPlan`, `ActivityGrades`, `StudentMarks`) | La suma del 100 % se valida junta. Las notas de una actividad se guardan juntas, como la planilla. Los literales e informes de un estudiante, por separado de las notas de Media. |
| Value Object (`Score`, `Weight`, `GradeMark`) | Enteros en centésimas: el redondeo exacto (NF03) no se puede romper desde fuera. |
| Domain Service (`TermScore`, `FinalScore`, `OutcomeProposal`, `Rounding`) | Reglas que cruzan varios agregados (plan, notas, periodos) y que se prueban solas con tablas de casos. |
| Puntos de extensión (ADR 0012) | Cierre del lapso, anulación, pensum y prosecución, sin que otros módulos dependan de Evaluation. |
| Lado de lectura (A-08) | La planilla de 40 × 10 en dos consultas, sin hidratar agregados (NF04). |

## Modelo de amenazas (STRIDE)

| Amenaza | Vector | Mitigación | Verificado por |
|---------|--------|------------|----------------|
| **S**poofing | Cargar notas como otro docente. | La cuenta es la de la sesión; la asignación se comprueba con ella en el caso de uso. | `SaveActivityGradesTest` › «solo quien dicta la materia» |
| **T**ampering | Un docente cambia notas de una materia que no dicta, o de otra sección, por id. | `TeachingFacts::teaches`/`guides` en cada guardado (S-04, EVA-NF01). La actividad fija sección y materia; el estudiante debe aplicar. | `GradesPermissionsTest` |
| **T**ampering | Cambiar una nota de un lapso cerrado o de un año cerrado. | `TermEditabilityQuery` en cada escritura. El permiso especial nunca vale con el año cerrado. | `TermEditabilityTest` (Evaluation); `EVA-R06` feature |
| **T**ampering | Redondeo manipulable por coma flotante (9,5 → 9). | Enteros en centésimas; `CHECK` en base de datos. | `RoundingTest` (tabla de casos de borde) |
| **R**epudiation | Negar un cambio de nota o un cierre por excepción. | Bitácora con antes y después por nota y con justificación (EVA-R10, R12.3). | `EvaluationAuditTest` |
| **I**nformation disclosure | Un docente o un coordinador ve notas ajenas. | Visibilidad por asignación o por grados coordinados; lo ajeno responde 404 (R07.6). | `GradesVisibilityTest` |
| **I**nformation disclosure | El texto de los informes en la bitácora. | Solo «cambió» (R10.2). | `EvaluationAuditTest` |
| **I**nformation disclosure | Notas de otro plantel. | RLS en las tres tablas (S-01, NF02); el plantel sale solo del subdominio (S-02, EVA-R11.1, R11.2). | `EvaluationIsolationTest` (`rls`); `TenantTableTest` |
| **D**enial of service | Guardar planillas enormes. | Hasta 60 estudiantes por guardado, validado en el FormRequest; `throttle`. | FormRequest |
| **E**levation of privilege | Un Representante con `ver-calificaciones` entra a `/notas`. | El caso de uso exige asignación, coordinación o `gestionar-estudiantes`; si no, una lista vacía y 404 en las planillas. | `GradesPermissionsTest` |
| **E**levation of privilege | Cerrar un lapso por excepción sin ser directivo. | Solo `gestionar-calendario` cierra (ruta y caso de uso), y la justificación es obligatoria. | `TermClosingTest` |

## Errores

| Excepción de dominio | HTTP | Mensaje al usuario |
|----------------------|------|--------------------|
| `PlanNotValid` (BusinessRuleViolation, campo `activities`) | 422 | «Los porcentajes suman {x} %: deben sumar 100 %.» / «La actividad {n} …» |
| `NoPlan` (BusinessRuleViolation) | 422 | «Registra primero el plan de evaluación del lapso.» |
| `GradeNotValid` (BusinessRuleViolation, campo `grades.{id}`) | 422 | «La nota debe estar entre 1 y 20, con hasta dos decimales.» |
| `NotEditable` (BusinessRuleViolation) | 422 | «El lapso está cerrado: sus notas no cambian.» / «El año está cerrado.» / «El lapso aún no se abre.» |
| `RemovalNeedsConfirmation` (BusinessRuleViolation) | 422 | «Quitarás {n} actividades con notas: confirma.» |
| `AuthorizationDenied` | 403 | «No dictas esa materia en esa sección.» |
| `NotFound` | 404 | Planilla, actividad o estudiante ajenos (R07.6, R11.3). |
| `BusinessRuleViolation` del cierre del lapso (AcademicCalendar) | 422 | «Hay {n} notas pendientes: complétalas o cierra por excepción con una justificación.» |

## Estrategia de pruebas

- **Dominio (unit):**
  - `Rounding` con tabla de bordes (9,495; 9,5; 19,999…);
  - `Score` y `Weight`;
  - `EvaluationPlan`: suma, fechas y quitar actividades;
  - `TermScore` con NP, NA, incompletas, una sola actividad y todo NA;
  - `FinalScore`;
  - `OutcomeProposal` por nivel;
  - `StudentMarks`;
  - la aplicabilidad por periodos (cambio de sección, incorporación tardía
    y cambio de grado).
- **Aplicación (unit, en memoria):**
  - cada handler, con permisos, asignación y editabilidad;
  - los cuatro puntos de extensión;
  - `PendingGradesQuery`.
- **Infraestructura (Postgres):**
  - repositorios y `EloquentGradebook`;
  - los `CHECK`;
  - el aislamiento (`rls`);
  - migración `up`, `down` y `up`;
  - integración con 007, 008 y 010: cierre bloqueado, materia con notas que
    no se quita, inscripción con notas que solo anula un directivo,
    propuesta en «Prosecución».
- **Feature:** rutas con permiso y sin él, docente frente a ajeno,
  coordinador por grados y la tabla de errores.
- **Navegador:** planilla y literales con axe a 360 px, y recorrido con el
  teclado (NF05).
- **Rendimiento (`perf`):** guardar 40 notas en menos de 500 ms (p95) y la
  planilla 40 × 10 en menos de 300 ms (p95) (NF04).

## Decisiones y alternativas

| Decisión | Alternativa descartada | Motivo |
|----------|------------------------|--------|
| 1. Enteros en centésimas y redondeo con enteros | `float`; `bcmath` | Exacto y sin dependencias en el dominio (A-02). `bcmath` también es exacto, pero cada operación es una cadena. |
| 2. Actividades aplicables por periodo de inscripción (NA derivado) | Guardar NA; plan por grado en vez de por sección | Resuelve la incorporación tardía, el cambio de sección y la anulación sin sincronizar nada. El plan por sección respeta que cada docente planifica lo suyo. |
| 3. Tres agregados | Un agregado «boletín» por estudiante | La planilla guarda por actividad (40 estudiantes a la vez). Un agregado por estudiante obligaría a cargar 40 agregados por guardado. |
| 4. Puntos de extensión para cierre, anulación, pensum y prosecución (ADR 0012) | Que 007, 008 y 010 llamen a Evaluation | Dependencias en un solo sentido; mismo patrón que SPEC-010. |
| 5. El directivo de la excepción de cierre es quien cierra (`gestionar-calendario`: Administrador y Director) — Q5 | Un permiso propio | Hoy solo ellos cierran lapsos. Un permiso propio puede añadirse sin cambiar el diseño. |
| 6. Aprobación del plan por la coordinación fuera de esta spec — Q4 | Estado «aprobado» antes de cargar notas | No se pidió; el agregado admite añadir un estado después. |
| 7. Menú «Notas» para gestores y docentes, no para el Representante | Mostrarla a todo `ver-calificaciones` | La paridad menú–rutas exige que la entrada abra algo útil. El Representante verá sus notas en el portal (F2-07). |

## Cambios en otras specs y módulos

- **SPEC-007 / AcademicCalendar:**
  - punto de extensión `TermClosingGuard` en `CloseTermHandler`, que acepta
    una justificación de excepción;
  - el diálogo de cierre del lapso muestra los pendientes;
  - CAL-R04 gana la condición de EVA-R12.
- **SPEC-008 / AcademicStructure:**
  - `TeachingQuery::guides()`, público;
  - `RemovalGuard::subjectsInUse` se implementa (EAC-R07.3).
- **SPEC-010 / Enrollment:**
  - consulta pública `EnrollmentPeriodsQuery`;
  - punto de extensión `OutcomeProposer` en `PromotionSheetQuery` y botón
    «Confirmar propuesta»;
  - `GradedEnrollment` se implementa (MAT-R14.3).
- **SPEC-013 / Workspace:**
  - entrada «Notas» y paridad;
  - «Mis asignaciones» enlaza a las planillas.

## Riesgos y plan de rollback

- **Cálculo distinto del que espera el plantel** (casos de borde de
  redondeo, NA). Mitigación: tabla de casos en los tests de dominio,
  revisada con el usuario antes de implementar.
- **Rendimiento de la planilla con la aplicabilidad.** Los periodos de 40
  estudiantes se leen en una sola consulta por planilla (NF04).
- **Cierre por excepción, que escribe notas en otro módulo** dentro de la
  transacción del calendario. Es lo que pide EVA-R12.3. Se prueba con los
  módulos reales.
- **Rollback:**
  - migración nueva, sin datos previos: `down` borra las tablas;
  - sin Evaluation registrado, los puntos de extensión dejan 007, 008 y 010
    como hoy.

## Notas de implementación

### PR A

- **`evaluation_plans`** (P-04, aprobado el 2026-10-28): una fila por
  planilla, con índice único. Ver «Datos». Un segundo primer guardado, o la
  edición de un plan que cambió entretanto, responde 409 (`PlanChanged`).
- **CHECK de `activity_grades`** con `score IS NOT NULL`: `NULL BETWEEN …` da
  NULL, y un CHECK deja pasar NULL. Así, una fila sin nota y sin NP no entra.
- **Sin puerto `Gradebook` ni `EloquentGradebook`.** `GradebookQuery` lee con
  los repositorios y con `StudentScores`, que la propuesta de prosecución
  (PR C) reutilizará. La planilla de 40 × 10 cumple EVA-NF04 sin un lado de
  lectura aparte (`GradebookPerformanceTest`).
- **`ActivityGradesRepository::saveGrades`**, y no `save`, porque la clase en
  memoria de los tests implementa los dos repositorios.
- **`GradeMark` no tiene «incompleto».** En Media General el cierre por
  excepción pone NP (EVA-R12.3). «Incompleto» es solo de los literales e
  informes, y lo guarda `StudentMarks` (PR B).
- **«Vigente en la sección»** se comprueba en la fecha de hoy
  (`EnrollmentPeriod::currentIn`), con el fin excluido, como `covers`.
- **`GET /notas` con `SheetsQuery`**, que el diseño no nombraba:
  - las planillas de Media General del año activo: las que dicta, las de los
    grados que coordina, o todas con `gestionar-estudiantes`;
  - se abre en el lapso abierto o, si no hay, en el último cerrado;
  - para listar, `TeachingFacts` gana `taughtClasses`, `classesOf` y
    `subjectNames`, y `CalendarFacts` gana `activeYear`.
- **Permiso de `GET /notas`:** `gestionar-calificaciones` o
  `gestionar-estudiantes`, como la entrada del menú (decisión 7 y paridad
  NAV-R03.3), y no `ver-calificaciones`. El Representante recibe 403 en la
  lista; en las planillas, 404 (R07.6).
- **La planilla responde 404** para una materia fuera del pensum o una
  sección que no es de Media General.
- **Límites de los formularios:** hasta 30 actividades por plan y 60 notas
  por guardado (S-12).
- **«Mis asignaciones»:** cada clase lleva su nivel (`MyAssignmentsQuery`), y
  las de Media General enlazan a su planilla en el lapso abierto.
- **Menú:** la entrada `grades` («Notas») va en «Académico», tras «Mis
  asignaciones». Workspace pregunta además por `gestionar-calificaciones`.
  Los tests que enumeran las entradas del menú se amplían con ella.

### PR B

- **Un `GuideMarks` común** para los tres casos de uso de literales e
  informes. Comprueba:
  - el nivel de la sección (literal en Primaria, informe en Inicial);
  - el guía;
  - la inscripción vigente;
  - el lapso editable.

  El literal final no pide el permiso especial con el tercer lapso cerrado,
  pero nunca entra con el año cerrado.
- **La descripción del literal es opcional**, de hasta 5000 caracteres. En
  la bitácora queda solo «escrita», nunca su texto, como el informe
  (EVA-R10.2).
- **`StudentMarksRepository::saveMarks`**, por la misma razón que
  `saveGrades`. Una marca nunca se borra: se reemplaza.
- **Una sola ruta para guardar,**
  `PUT /notas/{year}/secciones/{section}/estudiantes/{student}`, con `kind`
  (`literal` o `report`) y `period` (`1` – `3` o `final`). La planilla está
  en `GET /notas/{year}/secciones/{section}/literales`, para literales e
  informes.
- **«Notas»** lista también las secciones de Primaria e Inicial: las que
  guía, las de los grados que coordina, o todas con
  `gestionar-estudiantes`. «Mis asignaciones» enlaza cada sección guiada a
  sus literales o informes. `TeachingFacts` gana `sectionsOf` y
  `staffNames`.
- **Pendientes del lapso** (`PendingGrades`, sin permisos):
  - los usan `PendingGradesQuery` (con `ver-calificaciones` y
    `gestionar-estudiantes`) y `PendingGradesGuard`;
  - una materia de Media sin plan cuenta como pendiente, con todos sus
    estudiantes (EVA-R01.8);
  - la excepción no tiene nada que rellenar en ella: su lapso queda «no
    aplica».
- **`TermClosingGuard`:**
  - `pendingGrades` y `fillPending`, bajo el bloqueo y dentro de la
    transacción del cierre;
  - sin la excepción, el error va en `term`; sin justificación, en
    `exception_justification`;
  - la bitácora del plantel recibe `evaluation.term.closed_by_exception`
    (lapso, justificación y cuántas rellenó), además de cada NP e
    «incompleto».
- **«Año escolar»** recibe `pendingGrades` del lapso abierto. Si la persona
  no tiene `gestionar-estudiantes`, recibe `null`: el cierre igual se
  rechaza, pero sin la lista.

### PR C

- **`OutcomeProposal`** (dominio) devuelve el resultado en los valores de
  SPEC-010, o lo que falta para proponerlo.
  - En Media, una definitiva «no aplica» (ninguna actividad le aplicó)
    también falta: sin ella no hay propuesta.
- **La nota de un estudiante cuenta solo las actividades de su grado**
  (`StudentScores` filtra los periodos por el grado de la planilla).
  - Las materias del catálogo son las mismas en todos los grados de un
    nivel. Sin el filtro, tras un cambio de grado las notas del grado
    anterior entrarían en la del nuevo.
  - Esas notas quedan para consulta (EVA-R09.3). Guardar en la sección
    anterior ya se rechaza, porque el estudiante no está inscrito en ella.
- **`GradedEnrollment` sin cambiar su firma.** `EnrollmentFacts` gana
  `enrollment(id)`, sobre la consulta pública
  `EnrollmentPeriodsQuery::ofEnrollment()`.
- **`ActivityGradesRepository`** gana `gradedWithin` y `subjectsWithGrades`
  (una consulta cada uno).
- **`SubjectsWithGrades`** implementa `RemovalGuard`: responde 0 y «sin
  inscripciones» a lo que no es suyo.

### SPEC-016 (revisión), 2026-10-30

- **El plan tiene un dueño, sección o grupo de pendientes** (ADR 0013).
  Las firmas de SPEC-011 aceptan el id de la sección como antes, y sus
  tests no cambian su lógica.
- **La propuesta del resultado (EVA-R08)** usa las definitivas tras
  revisión, y una definitiva reprobada sin revisión no deja proponer.
- **La planilla** muestra la revisión y la definitiva que cuenta (columnas
  «Revisión» y «Cuenta»), editable con el tercer lapso cerrado.
