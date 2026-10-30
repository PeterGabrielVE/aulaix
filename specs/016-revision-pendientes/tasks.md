---
spec: 016-revision-pendientes
estado: implementando
fecha: 2026-10-30
aprobada: 2026-10-30
diseño: design.md
depende-de: 007, 008, 010, 011 (implementadas); ADR 0012, ADR 0013
---

# Tareas — Revisión y materias pendientes

Regla transversal:
- al cerrar cada tarea, `php artisan test --compact` sigue en verde,
  incluidos `--group=rls` y la suite `Architecture`;
- TDD (Q-02): primero el test en rojo, por el motivo correcto;
- al cerrar una tarea, sus tests se añaden a la matriz de
  `requirements.md`;
- los tests existentes de SPEC-011 conservan su lógica; solo cambia cómo
  construyen un plan (Q-03).

Tres PRs, cada uno usable por sí mismo:

- **PR A (fases 1 – 3):** el plan con dueño (ADR 0013), la migración y la
  prueba de revisión con su efecto en la propuesta de prosecución.
- **PR B (fases 4 – 6):** grupos de pendientes: miembros, docente, plan,
  notas, revisión y cierre del lapso.
- **PR C (fases 7 – 8):** la propuesta con las pendientes, el egreso, las
  pendientes en la ficha, rendimiento y cierre de la spec.

## PR A — Plan con dueño y prueba de revisión

### Fase 1 — El plan con dueño (refactor, ADR 0013)

- [x] **T-01** — `PlanOwner` (sección o grupo) y `ActivityAudience`
  (`SectionAudience`, `GroupAudience`):
  - `EvaluationPlan` guarda su dueño;
  - `ActivityGrades::record()` pregunta a la audiencia.

  No cambia ningún comportamiento de SPEC-011.
  - Criterios: — (refactor; base de RVP-R04)
  - Test:
    - `tests/Unit/Evaluation/Domain/PlanOwnerTest.php`;
    - `tests/Unit/Evaluation/Domain/ActivityAudienceTest.php`;
    - toda la suite de SPEC-011 en verde, con su lógica intacta.
- [x] **T-02** — Migración `…_create_revision_and_pending_tables`, en su
  propia tarea:
  - `pending_groups` y `grade_revisions`, con RLS;
  - `evaluation_plans`: `section_id` nulo, `pending_group_id`, el `CHECK`
    de un solo dueño y los índices únicos parciales.
  - Criterios: RVP-NF02, R08.1
  - Test:
    - `tests/Feature/Evaluation/Infrastructure/RevisionSchemaTest.php`
      (`CHECK`, índices, `up`, `down` con planes de grupo y `up`);
    - `TenantTableTest`.
- [x] **T-03** — `EloquentEvaluationPlanRepository` con dueño: lee y guarda
  los planes de sección y de grupo.
  - Criterios: RVP-R04.1 (base)
  - Test: `tests/Feature/Evaluation/Infrastructure/EvaluationRepositoriesTest.php`
    › «saves and reads the plan of a pending group» (nuevo); los demás sin
    cambios de lógica.

### Fase 2 — La revisión

- [x] **T-04** — `Revision` (agregado) y `FinalAfterRevision`.
  - Criterios: RVP-R01.2 – R01.5, R02.1, R02.2, NF03
  - Test:
    - `tests/Unit/Evaluation/Domain/RevisionTest.php`;
    - `tests/Unit/Evaluation/Domain/FinalAfterRevisionTest.php`, con una
      tabla de casos: 10, 9,99, 9,5 que redondea a 10, NP, sin revisión,
      incompleta y aprobada.
- [x] **T-05** — `SaveRevisionHandler` (dueño sección):
  - el docente que dicta la materia en la sección;
  - el tercer lapso cerrado y el año abierto;
  - el resultado aún sin registrar (`EnrollmentFacts::resultsRecorded`);
  - la bitácora.
  - Criterios: RVP-R01.1 – R01.8, R07.1, R07.2, NF01
  - Test: `tests/Unit/Evaluation/Application/RevisionTest.php`.
- [x] **T-06** — La propuesta del resultado con las definitivas tras
  revisión, que espera las revisiones que faltan (`OutcomeProposal` y
  `GradeOutcomeProposer`).
  - Criterios: RVP-R02.3, R02.4
  - Test:
    - `tests/Unit/Evaluation/Domain/OutcomeProposalTest.php` (casos nuevos);
    - `tests/Feature/Enrollment/PromotionProposalTest.php` › «counts the
      revisions» (nuevo).

### Fase 3 — Infraestructura y pantalla de la revisión

- [x] **T-07** — `EloquentRevisionRepository` y `resultsRecorded` en
  `EnrollmentEnrollmentFacts`, sobre una consulta pública de Enrollment.
  - Criterios: RVP-R08.1, R08.3, NF02
  - Test:
    - `tests/Feature/Evaluation/Infrastructure/RevisionRepositoryTest.php`;
    - `tests/Feature/Evaluation/RevisionIsolationTest.php` (`rls`).
- [x] **T-08** — La planilla de la sección:
  - la columna «Revisión» con el tercer lapso cerrado;
  - `GradebookQuery` con la definitiva, la revisión y la definitiva tras
    revisión;
  - `RevisionController@section`, con su FormRequest de hasta 60 notas.
  - Criterios: RVP-R01, R02.5, NF05
  - Test:
    - `tests/Feature/Evaluation/RevisionHttpTest.php` (mensajes de cada
      regla, docente ajeno);
    - `tests/Browser/GradebookTest.php` (axe con la columna).

## PR B — Grupos de pendientes

### Fase 4 — Dominio y miembros

- [ ] **T-09** — `PendingGroup` (agregado), `OwedPending` y
  `PendingStanding` (cursando, aprobada o reprobada).
  - Criterios: RVP-R03.4, R05.2
  - Test:
    - `tests/Unit/Evaluation/Domain/PendingGroupTest.php`;
    - `tests/Unit/Evaluation/Domain/PendingStandingTest.php`.
- [ ] **T-10** — Consulta pública `PendingSubjectsQuery` de Enrollment (las
  pendientes de los resultados, de estudiantes con inscripción vigente o
  pendientes de egresar) y `EnrollmentFacts::owedPendings`.
  - Criterios: RVP-R03.1 – R03.3
  - Test: `tests/Feature/Enrollment/Infrastructure/PendingSubjectsQueryTest.php`:
    - retirado o trasladado, fuera;
    - pendiente de egresar, dentro;
    - inscripción anulada, fuera.
- [ ] **T-11** — `PendingMembers`, `PendingGroupsQuery` y
  `AssignPendingTeacherHandler`:
  - `gestionar-estructura` (Q1);
  - `TeachingFacts::isActiveTeacher`;
  - una pendiente aprobada en un año anterior deja el grupo;
  - una reprobada sigue en el del año siguiente (decisión 2).
  - Criterios: RVP-R03.1 – R03.6, R07.1, NF01
  - Test: `tests/Unit/Evaluation/Application/PendingGroupsTest.php`.

### Fase 5 — Plan, notas y revisión del grupo

- [ ] **T-12** — `SavePlanHandler`, `SaveActivityGradesHandler` y
  `SaveRevisionHandler` con dueño grupo:
  - solo el docente asignado;
  - los miembros con `GroupAudience`, sin NA (decisión 3);
  - el lapso cerrado, el año cerrado y el lapso sin abrir;
  - la bitácora.
  - Criterios: RVP-R04.1, R04.2, R04.4, R04.5, R05.1, R07.1, R07.2
  - Test: `tests/Unit/Evaluation/Application/PendingGroupGradesTest.php`.
- [ ] **T-13** — `PendingGroupSheetQuery`:
  - actividades y notas, nota del lapso, definitiva, revisión y estado;
  - visible para el asignado, el coordinador del grado de origen y los
    gestores; para cualquier otro, no encontrado.
  - Criterios: RVP-R04.3, R04.6, R05.2
  - Test: `tests/Unit/Evaluation/Application/PendingGroupSheetTest.php`.
- [ ] **T-14** — `PendingGrades` cuenta los grupos, y la excepción del
  cierre del lapso los rellena con NP.
  - Criterios: RVP-R04.7
  - Test:
    - `tests/Unit/Evaluation/Application/PendingGradesTest.php` (casos
      nuevos);
    - `tests/Feature/Evaluation/Infrastructure/TermClosingIntegrationTest.php`
      › «counts the pending groups» (nuevo).

### Fase 6 — Infraestructura y pantallas de los grupos

- [ ] **T-15** — `EloquentPendingGroupRepository`, `isActiveTeacher` en el
  adaptador, `canAssignPendingTeachers` en `GateEvaluationPermissions` y el
  aislamiento.
  - Criterios: RVP-R08.1 – R08.3, NF02
  - Test:
    - `tests/Feature/Evaluation/Infrastructure/PendingGroupRepositoryTest.php`;
    - `tests/Feature/Evaluation/PendingGroupIsolationTest.php` (`rls`).
- [ ] **T-16** — «Grupos de pendientes» y su planilla:
  - `PendingGroupController` (lista, asignación, planilla, plan y
    revisión);
  - FormRequests;
  - «Notas» lista los grupos del docente asignado y enlaza a los gestores
    a «Grupos de pendientes».
  - Criterios: RVP-R03.6, R04, R05.1, NF05
  - Test:
    - `tests/Feature/Evaluation/PendingGroupHttpTest.php`;
    - `tests/Feature/Evaluation/PendingGroupPermissionsTest.php` (docente
      ajeno, coordinador, gestor, docente que se asigna a sí mismo);
    - `tests/Browser/PendingGroupTest.php` (axe a 360 px).

## PR C — Prosecución, egreso y ficha

### Fase 7 — Lo que deciden las pendientes

- [ ] **T-17** — La propuesta con las pendientes:
  - una pendiente cursando o sin revisión no deja proponer;
  - una reprobada propone «repite» y dice cuál;
  - las nuevas reprobadas siguen la regla de siempre (Q4).
  - Criterios: RVP-R05.3, R05.4
  - Test:
    - `tests/Unit/Evaluation/Domain/OutcomeProposalTest.php` (casos
      nuevos);
    - `tests/Feature/Enrollment/PromotionProposalTest.php` › «proposes to
      repeat for a failed pending subject» (nuevo).
- [ ] **T-18** — Punto de extensión `PendingSubjectsCheck` en Enrollment y
  `OwedPendingsCheck` en Evaluation: quien estaba pendiente de egresar y ya
  no debe nada aparece como egresado.
  - Criterios: RVP-R05.5
  - Test: `tests/Feature/Evaluation/Infrastructure/GraduationIntegrationTest.php`;
    regla de arquitectura: solo el provider de Evaluation registra sus
    hooks.
- [ ] **T-19** — `StudentPendingsQuery` y las pendientes en la ficha del
  estudiante, compuestas en `app/` para quien puede verla.
  - Criterios: RVP-R05.2, R06.1, R06.2
  - Test:
    - `tests/Unit/Evaluation/Application/StudentPendingsTest.php`;
    - `tests/Feature/Students/StudentPendingsHttpTest.php`.

### Fase 8 — Rendimiento y cierre

- [ ] **T-20** — Rendimiento:
  - la planilla de un grupo de 40 estudiantes con 10 actividades por
    debajo de 300 ms (p95);
  - la propuesta de una sección de 40, con revisiones y pendientes, por
    debajo de 500 ms (p95).
  - Criterios: RVP-NF04
  - Test: `tests/Feature/Evaluation/PendingGroupPerformanceTest.php` (`perf`).
- [ ] **T-21** — Cambios en otras specs. Se aplica en el PR que cambia cada
  comportamiento (P-04):
  - SPEC-011: plan con dueño, revisión y propuesta (A); cierre del lapso
    (B);
  - SPEC-010: `PendingSubjectsQuery` (B); `PendingSubjectsCheck` y egreso
    (C);
  - SPEC-009: pendientes en la ficha (C);
  - SPEC-013: «Grupos de pendientes» desde «Notas» (B);
  - roadmap (C).
  - Criterios: —
  - Test: `tests/Architecture/SpecsTest.php`

## Verificación final

- [ ] `php artisan test --compact` en verde (incluidos `--group=rls`,
  `--group=perf` y `tests/Isolation`), y tests de navegador en verde.
- [ ] Tests de arquitectura en verde.
- [ ] `vendor/bin/pint --dirty` sin cambios; `npm run build` sin errores.
- [ ] Migración: `up`, `down` y `up`.
- [ ] Matriz de trazabilidad completa.
- [ ] `/security-review` de cada PR (permisos y tenancy; S-12).
- [ ] `estado` de requirements/design/tasks actualizado.

## Cobertura de criterios

| Criterio | Tareas |
|----------|--------|
| RVP-R01 | T-04, T-05, T-08 |
| RVP-R02 | T-04, T-06, T-08 |
| RVP-R03 | T-09, T-10, T-11, T-16 |
| RVP-R04 | T-01, T-03, T-12, T-13, T-14, T-16 |
| RVP-R05 | T-09, T-12, T-13, T-17, T-18, T-19 |
| RVP-R06 | T-19 |
| RVP-R07 | T-05, T-11, T-12 |
| RVP-R08 | T-02, T-07, T-15 |
| RVP-NF01 | T-05, T-11 |
| RVP-NF02 | T-02, T-07, T-15 |
| RVP-NF03 | T-04 |
| RVP-NF04 | T-20 |
| RVP-NF05 | T-08, T-16 |
