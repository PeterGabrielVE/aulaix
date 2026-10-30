---
spec: 016-revision-pendientes
prefijo: RVP
estado: aprobada
fecha: 2026-10-30
aprobada: 2026-10-30
backlog: F2-01, F2-02
módulo: Evaluation
depende-de: 007-anio-escolar (lapsos), 008-estructura-academica (pensum, docentes), 010-inscripcion (resultado de prosecución, pendiente de egresar), 011-evaluacion (definitivas, planes, notas, propuesta del resultado)
la-usan: 012-documentos (definitiva tras revisión, título retenido), portal del representante (F2-07)
---

# Requisitos — Revisión y materias pendientes

## Contexto

SPEC-011 deja cada materia de Media General con su definitiva, y SPEC-010
registra el resultado del año:
- con 1 o 2 materias reprobadas, el estudiante es promovido con esas
  materias pendientes;
- con 3 o más, repite.

Hoy faltan dos pasos que el plantel da antes y después de ese resultado:
- **Revisión (F2-01):** al terminar el año, quien reprobó una materia
  presenta una prueba de revisión. Si la aprueba, la materia deja de estar
  reprobada, y eso cambia el resultado de prosecución.
- **Materias pendientes (F2-02):** el año siguiente, el estudiante cursa
  cada materia pendiente en un grupo de pendientes, con su docente, su plan
  y sus notas por lapso. Si no la aprueba, ni en revisión, repite el grado
  que cursa.

Al aprobar su última pendiente, el estudiante de 5.º año que quedó
«pendiente de egresar» (SPEC-010) egresa.

Backlog: F2-01 y F2-02.

## Alcance

- **Incluye:**
  - la prueba de revisión de cada definitiva reprobada de Media General,
    también la de una materia pendiente;
  - la definitiva tras la revisión, y su efecto en la propuesta del
    resultado de prosecución (SPEC-011);
  - los grupos de pendientes del año: uno por materia y grado de origen, su
    docente, su plan de actividades y sus notas por lapso;
  - la aprobación de una materia pendiente, el egreso de quien estaba
    pendiente de egresar y la propuesta de «repite» al reprobarla;
  - las materias pendientes de cada estudiante con su estado, en su ficha;
  - la bitácora, los permisos y el aislamiento por plantel.
- **Excluye:**
  - revisión en Primaria e Inicial, que no tienen notas numéricas;
  - reclamos o correcciones de notas pedidos por el representante o el
    estudiante;
  - retener el título o emitir la certificación con la definitiva tras
    revisión (SPEC-012);
  - que el representante vea las pendientes de su representado en el portal
    (F2-07).

## Reglas decididas (2026-10-30)

| Tema | Decisión |
|------|----------|
| Qué es la revisión | Una prueba al terminar el año, para cada definitiva reprobada de Media General. |
| Nota de la revisión | De 1 a 20, con hasta dos decimales, o NP. Con 10 o más, la materia queda aprobada con la nota de la revisión. Con menos, o NP, sigue reprobada. |
| Cuándo y quién | Con el tercer lapso cerrado y antes de registrar el resultado de prosecución. La carga el docente que dictó la materia en la sección. |
| Cómo se cursa una pendiente | En un grupo de pendientes por materia y grado de origen (p. ej. Matemática de 3.er año), con un docente asignado, su plan de actividades y sus notas por lapso, como una materia más (SPEC-011). |
| Quién carga sus notas | Solo el docente asignado al grupo. |
| Cómo se aprueba | Con una definitiva de 10 o más. Si queda reprobada, tiene su prueba de revisión. |
| Si no la aprueba | Repite el grado que cursa en el año en curso. |

## Glosario

| Término | Significado |
|---------|-------------|
| Prueba de revisión | Prueba que presenta, al terminar el año, quien tiene una definitiva reprobada en Media General. |
| Definitiva tras revisión | La definitiva de la materia o, si la revisión se aprobó, la nota de la revisión redondeada al entero. Es la que cuenta para la prosecución. |
| Materia pendiente | Materia reprobada con la que el estudiante fue promovido (SPEC-010). Se cursa el año siguiente en un grupo de pendientes. |
| Grupo de pendientes | Los estudiantes que cursan en un año la misma materia pendiente del mismo grado de origen, con un docente asignado. |
| Grado de origen | El grado en que se reprobó la materia pendiente. |
| Pendiente de egresar | Estudiante de 5.º año promovido con materia pendiente (SPEC-010). |

## Requisitos

### RVP-R01 — Prueba de revisión

**Historia:** Como docente de una materia de Media General, quiero registrar
la prueba de revisión de quien la reprobó, para que su resultado del año
cuente con ella.

**Criterios de aceptación (EARS):**

- **RVP-R01.1** — Cuando el docente que dictó la materia en la sección
  registra la nota de revisión de un estudiante con la definitiva
  reprobada, el sistema deberá guardarla.
- **RVP-R01.2** — Si la nota de revisión no está entre 1 y 20, o tiene más
  de dos decimales, entonces el sistema deberá rechazarla.
- **RVP-R01.3** — El sistema deberá aceptar NP como nota de revisión, y la
  materia seguirá reprobada.
- **RVP-R01.4** — Si la definitiva del estudiante no está reprobada (está
  aprobada, incompleta o no aplica), entonces el sistema deberá rechazar la
  revisión.
- **RVP-R01.5** — Mientras el tercer lapso del año no esté cerrado, el
  sistema deberá rechazar las revisiones.
- **RVP-R01.6** — Mientras el año escolar esté cerrado, el sistema deberá
  rechazar todo cambio en las revisiones.
- **RVP-R01.7** — Si el estudiante ya tiene registrado su resultado de
  prosecución, entonces el sistema deberá rechazar el cambio de su revisión
  e indicar que primero se corrija el resultado (MAT-R06).
- **RVP-R01.8** — Si quien registra la revisión no dictó la materia en la
  sección del estudiante, entonces el sistema deberá rechazarlo.

### RVP-R02 — Definitiva tras revisión y prosecución

**Historia:** Como gestor de inscripciones, quiero que la propuesta del
resultado del año cuente las revisiones, para no calcularlo a mano.

**Criterios de aceptación (EARS):**

- **RVP-R02.1** — Cuando la nota de revisión es 10 o más, el sistema deberá
  dar la materia por aprobada, con la nota de la revisión redondeada al
  entero como definitiva tras revisión.
- **RVP-R02.2** — Cuando la nota de revisión es menor que 10, o NP, el
  sistema deberá mantener la materia reprobada con su definitiva.
- **RVP-R02.3** — El sistema deberá proponer el resultado de prosecución
  (EVA-R08.1) con las definitivas tras revisión.
- **RVP-R02.4** — Mientras alguna definitiva reprobada de un estudiante no
  tenga su revisión registrada, el sistema no deberá proponer su resultado,
  e indicará qué revisiones faltan (EVA-R08.5).
- **RVP-R02.5** — El sistema deberá mostrar en la planilla de la materia,
  junto a cada estudiante, la definitiva, la nota de revisión y la
  definitiva tras revisión.

### RVP-R03 — Grupos de pendientes

**Historia:** Como gestor, quiero ver quién cursa cada materia pendiente en
el año y asignarle un docente, para que se evalúe.

**Criterios de aceptación (EARS):**

- **RVP-R03.1** — El sistema deberá formar en el año activo un grupo de
  pendientes por materia y grado de origen, con los estudiantes cuyo
  resultado del año anterior es «promovido con materia pendiente» en esa
  materia.
- **RVP-R03.2** — El sistema deberá incluir en los grupos solo a quien tenga
  una inscripción vigente en el año activo o esté pendiente de egresar.
- **RVP-R03.3** — Cuando un estudiante es retirado o trasladado (SPEC-009),
  el sistema deberá sacarlo de sus grupos de pendientes y conservar sus
  notas.
- **RVP-R03.4** — Cuando un gestor asigna un docente a un grupo de
  pendientes, el sistema deberá registrarlo.
- **RVP-R03.5** — Si la persona asignada no es un miembro activo con el rol
  Docente, entonces el sistema deberá rechazarla (como EAC-R05).
- **RVP-R03.6** — El sistema deberá mostrar a los gestores los grupos de
  pendientes del año activo, con su materia, grado de origen, docente y
  estudiantes, y los grupos sin docente señalados.

### RVP-R04 — Plan y notas de una pendiente

**Historia:** Como docente asignado a un grupo de pendientes, quiero
planificar y cargar sus notas como en cualquier materia, para evaluar la
pendiente durante el año.

**Criterios de aceptación (EARS):**

- **RVP-R04.1** — Cuando el docente asignado guarda el plan de actividades
  del grupo en un lapso, el sistema deberá aplicarle las reglas del plan de
  SPEC-011 (EVA-R01: porcentajes que suman 100 %, fechas dentro del lapso,
  confirmación al quitar actividades con notas).
- **RVP-R04.2** — Cuando el docente asignado guarda las notas de una
  actividad del grupo, el sistema deberá aplicarles las reglas de EVA-R02
  (1 a 20, NP o sin nota) para los estudiantes del grupo.
- **RVP-R04.3** — El sistema deberá calcular la nota de cada lapso y la
  definitiva de la pendiente con las reglas de EVA-R03.
- **RVP-R04.4** — El sistema deberá aplicar a los grupos de pendientes las
  reglas del lapso cerrado, del año cerrado y del lapso sin abrir
  (EVA-R06).
- **RVP-R04.5** — Si quien guarda el plan o las notas no es el docente
  asignado al grupo, entonces el sistema deberá rechazarlo.
- **RVP-R04.6** — El sistema deberá mostrar la planilla del grupo al docente
  asignado, al Coordinador del grado de origen y a quien tenga
  `gestionar-estudiantes`. A cualquier otro deberá responderle como si el
  grupo no existiera.
- **RVP-R04.7** — Cuando un gestor va a cerrar un lapso, el sistema deberá
  contar las notas pendientes de los grupos de pendientes con las demás
  (EVA-R12), y la excepción deberá rellenarlas con NP.

### RVP-R05 — Aprobar o reprobar una pendiente

**Historia:** Como gestor, quiero que el sistema sepa qué pendientes quedaron
aprobadas, para que el estudiante prosiga o egrese.

**Criterios de aceptación (EARS):**

- **RVP-R05.1** — Cuando la definitiva de la pendiente queda reprobada con
  el tercer lapso cerrado, el sistema deberá permitir al docente asignado
  registrar su prueba de revisión, con las reglas de RVP-R01 y RVP-R02.
- **RVP-R05.2** — Cuando la definitiva tras revisión de una pendiente es 10
  o más, el sistema deberá darla por aprobada, con el año escolar y la
  nota.
- **RVP-R05.3** — Cuando una pendiente queda reprobada tras su revisión, el
  sistema deberá proponer «repite» como resultado de prosecución del año en
  curso del estudiante, e indicar qué pendiente lo causa.
- **RVP-R05.4** — Mientras una pendiente del estudiante no tenga definitiva
  tras revisión, el sistema no deberá proponer su resultado del año, e
  indicará qué falta (EVA-R08.5).
- **RVP-R05.5** — Cuando un estudiante pendiente de egresar aprueba su
  última materia pendiente, el sistema deberá mostrarlo como egresado de
  ese año escolar (MAT-R15).

### RVP-R06 — Las pendientes de un estudiante

**Historia:** Como gestor o docente, quiero ver en la ficha de un estudiante
sus materias pendientes y cómo van.

**Criterios de aceptación (EARS):**

- **RVP-R06.1** — El sistema deberá mostrar en la ficha del estudiante
  (SPEC-009) cada materia pendiente con:
  - su grado de origen;
  - el año en que se reprobó;
  - su estado (cursando, aprobada o reprobada);
  - su definitiva tras revisión, cuando la tenga.
- **RVP-R06.2** — El sistema deberá mostrar esa información solo a quien
  puede ver la ficha del estudiante (EST, MAT-R08).

### RVP-R07 — Bitácora

- **RVP-R07.1** — El sistema deberá registrar en la bitácora del plantel:
  - cada nota de revisión registrada o cambiada, con su valor antes y
    después;
  - cada docente asignado a un grupo de pendientes, o cambiado;
  - cada plan y cada guardado de notas de un grupo, como en EVA-R10.1;
  - cada pendiente aprobada o reprobada y cada egreso por aprobarlas.
- **RVP-R07.2** — Cuando alguien con `editar-notas-lapso-cerrado` cambia una
  revisión o una nota de un lapso cerrado, el sistema deberá registrar
  quién lo hizo (EVA-R06.3).

### RVP-R08 — Aislamiento por plantel

- **RVP-R08.1** — El sistema deberá mostrar y modificar solo las revisiones,
  los grupos de pendientes y sus notas del plantel activo (S-01).
- **RVP-R08.2** — El sistema deberá tomar el plantel únicamente del
  subdominio (S-02).
- **RVP-R08.3** — Si se intenta usar un grupo, una actividad, una revisión o
  un estudiante de otro plantel, entonces el sistema deberá responder como
  si no existieran.

## Requisitos no funcionales

- **RVP-NF01** (seguridad) — Toda carga exige `gestionar-calificaciones` en
  la ruta y, en el caso de uso, que la persona sea el docente de la materia
  en la sección (revisión) o el asignado al grupo (pendiente) (S-04).
  Asignar docentes exige el permiso de gestor que se decida en Q1.
- **RVP-NF02** (seguridad) — Las revisiones y los grupos de pendientes viven
  en tablas con RLS (S-01). Lo verifican los tests de aislamiento y
  `TenantTableTest`.
- **RVP-NF03** (exactitud) — La definitiva tras revisión y las notas de las
  pendientes se calculan con aritmética entera exacta, como EVA-NF03.
- **RVP-NF04** (rendimiento):
  - la planilla de un grupo de 40 estudiantes con 10 actividades responde en
    menos de 300 ms (p95);
  - la propuesta del resultado de una sección de 40 estudiantes, con
    revisiones y pendientes, en menos de 500 ms (p95).
- **RVP-NF05** (accesibilidad) — Las pantallas de revisión y de grupos de
  pendientes pasan axe y funcionan a 360 px, como las de SPEC-011.

## Cambios en otras specs

- **SPEC-010:**
  - la propuesta de «repite» por una pendiente reprobada (RVP-R05.3) se
    confirma como cualquier resultado (MAT-R06);
  - el egreso de quien estaba pendiente de egresar (RVP-R05.5) completa
    MAT-R15;
  - la inscripción del estudiante pendiente de egresar es su lugar en los
    grupos de pendientes (MAT-R15.4).
- **SPEC-011:**
  - la propuesta del resultado (EVA-R08) usa las definitivas tras revisión
    y las pendientes;
  - el cierre del lapso (EVA-R12) cuenta los grupos de pendientes;
  - «Notas» lista los grupos del docente asignado.
- **SPEC-012:** la certificación usa la definitiva tras revisión, y el
  título sigue retenido hasta aprobar las pendientes.

## Fuera de alcance y preguntas abiertas

**Preguntas resueltas:**

- [x] **Q1 — Quién asigna el docente de un grupo de pendientes.**
  *Decidido (2026-10-30):* quien tiene `gestionar-estructura`
  (Administrador y Director), como las asignaciones docentes de SPEC-008.
- [x] **Q2 — Pendiente de egresar que reprueba una pendiente tras la
  revisión.** No cursa ningún grado que pueda repetir. *Decidido
  (2026-10-30):* sigue pendiente de egresar y vuelve a cursar esa materia
  en el grupo del año siguiente.
- [x] **Q3 — Redondeo de la nota de revisión.** *Decidido (2026-10-30):*
  la definitiva tras revisión es la nota de la revisión redondeada al
  entero, con ,5 hacia arriba, como las definitivas (EVA-R03.1).
- [x] **Q4 — Más de una pendiente y nuevas reprobadas.** Un estudiante puede
  aprobar sus pendientes y, a la vez, reprobar materias del grado en curso.
  *Decidido (2026-10-30):* las pendientes aprobadas no cuentan, y las
  nuevas reprobadas siguen la regla de 1 o 2 (pendiente) y 3 o más
  (repite).

**Fuera de alcance:** ver «Excluye» en el alcance.

## Matriz de trazabilidad

| Criterio | Test(s) |
|----------|---------|
| RVP-R01.1 | `tests/Unit/Evaluation/Application/RevisionTest.php` › «the teacher of the subject records the revision of a failed final, recorded with what changed»; `tests/Feature/Evaluation/RevisionHttpTest.php` › «records a revision and shows the final grade that counts»; `tests/Feature/Evaluation/Infrastructure/RevisionRepositoryTest.php` › «saves, changes and clears the revisions of a section, and reads them by student» |
| RVP-R01.2 | `tests/Unit/Evaluation/Application/RevisionTest.php` › «refuses a revision out of the scale, of a final that passed, or of whoever left the section»; `tests/Feature/Evaluation/RevisionHttpTest.php` › «says what is wrong with a revision» |
| RVP-R01.3 | `tests/Unit/Evaluation/Application/RevisionTest.php` › «takes NP as a revision»; `tests/Unit/Evaluation/Domain/FinalAfterRevisionTest.php` › «decides the final grade with its revision» |
| RVP-R01.4 | `tests/Unit/Evaluation/Domain/RevisionTest.php` › «refuses a revision of a final that is not failed»; `tests/Unit/Evaluation/Application/RevisionTest.php` › «refuses a revision out of the scale, of a final that passed, or of whoever left the section»; `tests/Feature/Evaluation/RevisionHttpTest.php` › «says what is wrong with a revision» |
| RVP-R01.5 | `tests/Unit/Evaluation/Domain/RevisionTest.php` › «refuses a revision before the third term is closed, or once the result of the year is recorded»; `tests/Unit/Evaluation/Application/RevisionTest.php` › «refuses revisions before the third term is closed, in a closed year, and once the result is recorded»; `tests/Feature/Evaluation/RevisionHttpTest.php` › «says what is wrong with a revision» |
| RVP-R01.6 | `tests/Unit/Evaluation/Application/RevisionTest.php` › «refuses revisions before the third term is closed, in a closed year, and once the result is recorded» |
| RVP-R01.7 | `tests/Unit/Evaluation/Domain/RevisionTest.php` › «refuses a revision before the third term is closed, or once the result of the year is recorded»; `tests/Unit/Evaluation/Application/RevisionTest.php` › «refuses revisions before the third term is closed, in a closed year, and once the result is recorded»; `tests/Feature/Evaluation/Infrastructure/RevisionRepositoryTest.php` › «tells who already has the result of the year recorded» |
| RVP-R01.8 | `tests/Unit/Evaluation/Application/RevisionTest.php` › «only whoever teaches the subject in the section records its revisions»; `tests/Feature/Evaluation/RevisionHttpTest.php` › «only the teacher of the subject records its revisions» |
| RVP-R02.1 | `tests/Unit/Evaluation/Domain/FinalAfterRevisionTest.php` › «decides the final grade with its revision»; `tests/Unit/Evaluation/Domain/FinalAfterRevisionTest.php` › «tells whether the subject passed or failed in the end» |
| RVP-R02.2 | `tests/Unit/Evaluation/Domain/FinalAfterRevisionTest.php` › «decides the final grade with its revision» |
| RVP-R02.3 | `tests/Unit/Evaluation/Domain/OutcomeProposalTest.php` › «counts a subject passed in its revision, and waits for the revisions still missing»; `tests/Feature/Enrollment/PromotionProposalTest.php` › «counts the revisions, and waits for the one still missing» |
| RVP-R02.4 | `tests/Unit/Evaluation/Domain/OutcomeProposalTest.php` › «counts a subject passed in its revision, and waits for the revisions still missing»; `tests/Feature/Enrollment/PromotionProposalTest.php` › «counts the revisions, and waits for the one still missing» |
| RVP-R02.5 | `tests/Feature/Evaluation/RevisionHttpTest.php` › «shows the failed final grades waiting for their revision»; `tests/Feature/Evaluation/RevisionHttpTest.php` › «records a revision and shows the final grade that counts»; `tests/Browser/GradebookTest.php` › «the sheet with the column of revisions is accessible» |
| RVP-R03.1 | `tests/Unit/Evaluation/Application/PendingGroupsTest.php` › «gathers the pending subjects of the year by subject and grade of origin, without who approved them in a year before»; `tests/Feature/Enrollment/Infrastructure/PendingSubjectsQueryTest.php` › «gives the pending subjects of the students enrolled this year and of those waiting to graduate» |
| RVP-R03.2 | `tests/Feature/Enrollment/Infrastructure/PendingSubjectsQueryTest.php` › «gives the pending subjects of the students enrolled this year and of those waiting to graduate»; `tests/Feature/Enrollment/Infrastructure/PendingSubjectsQueryTest.php` › «leaves out the results of the year itself» |
| RVP-R03.3 | `tests/Feature/Enrollment/Infrastructure/PendingSubjectsQueryTest.php` › «gives the pending subjects of the students enrolled this year and of those waiting to graduate» |
| RVP-R03.4 | `tests/Unit/Evaluation/Domain/PendingGroupTest.php` › «takes one teacher, changes it or leaves it without one, and tells who was before»; `tests/Unit/Evaluation/Application/PendingGroupsTest.php` › «a manager assigns a teacher to a group, recorded with who was before»; `tests/Feature/Evaluation/Infrastructure/PendingGroupRepositoryTest.php` › «opens a group, changes its teacher and finds it by year, subject and grade of origin» |
| RVP-R03.5 | `tests/Unit/Evaluation/Application/PendingGroupsTest.php` › «assigns only an active member with the role Docente, to a group someone owes»; `tests/Feature/Evaluation/Infrastructure/PendingGroupRepositoryTest.php` › «names the active members with the role Docente, and nobody else» |
| RVP-R03.6 | `tests/Feature/Evaluation/PendingGroupHttpTest.php` › «lists the groups of the year with their members, and assigns their teacher»; `tests/Unit/Evaluation/Application/PendingGroupsTest.php` › «gathers the pending subjects of the year by subject and grade of origin, without who approved them in a year before» |
| RVP-R04.1 | `tests/Unit/Evaluation/Application/PendingGroupGradesTest.php` › «the assigned teacher plans the group with the rules of a plan, recorded»; `tests/Feature/Evaluation/PendingGroupHttpTest.php` › «the assigned teacher finds the group in Notas, plans it and grades it» |
| RVP-R04.2 | `tests/Unit/Evaluation/Application/PendingGroupGradesTest.php` › «the assigned teacher grades the members, every activity applying to each, and nobody else»; `tests/Feature/Evaluation/PendingGroupHttpTest.php` › «the assigned teacher finds the group in Notas, plans it and grades it» |
| RVP-R04.3 | `tests/Unit/Evaluation/Application/PendingGroupSheetTest.php` › «shows the members with their grades, final grade, revision and how they stand» |
| RVP-R04.4 | `tests/Unit/Evaluation/Application/PendingGroupGradesTest.php` › «keeps the rules of the closed term, the closed year and the term not opened» |
| RVP-R04.5 | `tests/Unit/Evaluation/Application/PendingGroupGradesTest.php` › «only the teacher assigned to the group plans and grades it»; `tests/Feature/Evaluation/PendingGroupPermissionsTest.php` › «another teacher does not see the group, and can not plan nor grade it» |
| RVP-R04.6 | `tests/Unit/Evaluation/Application/PendingGroupSheetTest.php` › «shows the sheet to a coordinator of the grade of origin and to a manager, and to nobody else»; `tests/Feature/Evaluation/PendingGroupPermissionsTest.php` › «a coordinator of the grade of origin and a manager see the group, and grade none of it» |
| RVP-R04.7 | `tests/Unit/Evaluation/Application/PendingGradesTest.php` › «counts what the groups of pending subjects lack, with their teacher»; `tests/Unit/Evaluation/Application/PendingGradesTest.php` › «fills with NP the grades a group lacks, closing by exception»; `tests/Feature/Evaluation/Infrastructure/TermClosingIntegrationTest.php` › «counts the pending groups» |
| RVP-R05.1 | `tests/Unit/Evaluation/Application/PendingGroupGradesTest.php` › «the assigned teacher records the revision of a member who failed the pending subject»; `tests/Feature/Evaluation/PendingGroupHttpTest.php` › «records the revision of a member who failed the pending subject» |
| RVP-R05.2 | `tests/Unit/Evaluation/Domain/PendingStandingTest.php` › «stands by the final grade after revision once the third term is closed»; `tests/Unit/Evaluation/Application/PendingGroupSheetTest.php` › «shows the members with their grades, final grade, revision and how they stand» |
| RVP-R05.3 | |
| RVP-R05.4 | |
| RVP-R05.5 | |
| RVP-R06.1 | |
| RVP-R06.2 | |
| RVP-R07.1 | `tests/Unit/Evaluation/Application/RevisionTest.php` › «the teacher of the subject records the revision of a failed final, recorded with what changed»; `tests/Unit/Evaluation/Application/PendingGroupsTest.php` › «a manager assigns a teacher to a group, recorded with who was before»; `tests/Unit/Evaluation/Application/PendingGroupGradesTest.php` › «the assigned teacher plans the group with the rules of a plan, recorded» |
| RVP-R07.2 | |
| RVP-R08.1 | `tests/Feature/Evaluation/RevisionIsolationTest.php` › «another institution sees none of its revisions nor groups of pending subjects»; `tests/Feature/Evaluation/PendingGroupIsolationTest.php` › «another institution does not find its group of pending subjects» |
| RVP-R08.2 | `tests/Feature/Evaluation/RevisionIsolationTest.php` › «another institution sees none of its revisions nor groups of pending subjects» |
| RVP-R08.3 | `tests/Feature/Evaluation/RevisionIsolationTest.php` › «another institution can not record a revision in its section»; `tests/Feature/Evaluation/PendingGroupIsolationTest.php` › «another institution can not plan its group of pending subjects» |
| RVP-NF01 | `tests/Unit/Evaluation/Application/RevisionTest.php` › «only whoever teaches the subject in the section records its revisions»; `tests/Feature/Evaluation/PendingGroupPermissionsTest.php` › «a teacher does not assign itself a group, nor sees the groups» |
| RVP-NF02 | `tests/Feature/Evaluation/Infrastructure/RevisionSchemaTest.php` › «keeps a revision as a grade between 1 and 20 or NP, of a section or a group, once per student»; `tests/Feature/Evaluation/Infrastructure/RevisionSchemaTest.php` › «keeps a plan of a section or of a group, never both nor neither»; `tests/Feature/Evaluation/RevisionIsolationTest.php` › «another institution sees none of its revisions nor groups of pending subjects»; `tests/Feature/Evaluation/PendingGroupIsolationTest.php` › «another institution does not find its group of pending subjects» |
| RVP-NF03 | `tests/Unit/Evaluation/Domain/FinalAfterRevisionTest.php` › «decides the final grade with its revision» |
| RVP-NF04 | |
| RVP-NF05 | `tests/Browser/GradebookTest.php` › «the sheet with the column of revisions is accessible»; `tests/Browser/PendingGroupTest.php` › «the groups of pending subjects and the sheet of a group are accessible» |
