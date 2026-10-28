---
spec: 011-evaluacion
estado: implementada
fecha: 2026-10-28
aprobada: 2026-10-28
diseño: design.md
depende-de: 005, 007, 008, 010 (implementadas); ADR 0012
---

# Tareas — Evaluación

Regla transversal:
- al cerrar cada tarea, `php artisan test --compact` sigue en verde,
  incluidos `--group=rls` y la suite `Architecture`;
- TDD (Q-02): primero el test en rojo, por el motivo correcto;
- al cerrar una tarea, sus tests se añaden a la matriz de
  `requirements.md`.

Tres PRs, cada uno usable por sí mismo:

- **PR A (fases 1 – 4):** Media General: plan, notas, nota del lapso,
  definitiva, quién carga y ve, lapso cerrado y planilla.
- **PR B (fases 5 – 6):** Primaria e Inicial (literales e informes) y cierre
  del lapso con pendientes.
- **PR C (fases 7 – 8):** prosecución propuesta, notas que bloquean la
  anulación y el pensum, cambio de grado y cierre de la spec.

## PR A — Media General

### Fase 1 — Dominio

- [x] **T-01** — `Rounding`, `Score`, `Weight` y `GradeMark` (enteros en
  centésimas).
  - Criterios: EVA-R02.2, R02.3, R03.1, NF03
  - Test: `tests/Unit/Evaluation/Domain/RoundingTest.php`:
    - tabla de bordes: 9,49 → 9; 9,5 → 10; 19,5 → 20; 0,5 → 1;
    - fracciones periódicas.

    `tests/Unit/Evaluation/Domain/ScoreTest.php`:
    - de 1 a 20 con hasta dos decimales;
    - rechaza 0, 20,01, 9,999 y textos;
    - NP vale 1.
- [x] **T-02** — Agregado `EvaluationPlan`.
  - Criterios: EVA-R01.1 – R01.3, R01.5, R01.7
  - Test: `tests/Unit/Evaluation/Domain/EvaluationPlanTest.php`:
    - suma exacta de 100,00 %;
    - nombre y fecha dentro del lapso;
    - una sola actividad del 100 %;
    - quitar actividades con notas pide confirmación.
- [x] **T-03** — Aplicabilidad por periodos, `TermScore` y `FinalScore`.
  - Criterios: EVA-R02.7, R02.8, R03.1 – R03.7, R09.3
  - Test: `tests/Unit/Evaluation/Domain/TermScoreTest.php`:
    - suma ponderada con NP;
    - incompleta;
    - NA con el 100 % repartido;
    - todo NA da «no aplica»;
    - cambio de sección: lo de la anterior más lo de la nueva;
    - cambio de grado: solo consulta.

    `tests/Unit/Evaluation/Domain/FinalScoreTest.php`:
    - promedio redondeado;
    - incompleta;
    - con un lapso «no aplica»;
    - reprobada por debajo de 10.
- [x] **T-04** — Agregado `ActivityGrades`.
  - Criterios: EVA-R02.1 – R02.5, R02.8
  - Test: `tests/Unit/Evaluation/Domain/ActivityGradesTest.php`:
    - solo estudiantes a los que la actividad aplica;
    - sin nota, NP y nota;
    - rechaza la nota de quien tiene NA.

### Fase 2 — Consultas públicas de otros módulos y aplicación

- [x] **T-05** — En otros módulos:
  - `TeachingQuery::guides()` (AcademicStructure);
  - `EnrollmentPeriodsQuery` (Enrollment): sección, grado, desde y hasta
    de los estudiantes de un año.
  - Criterios: apoyo a EVA-R02.7, R02.8, R07.2
  - Test:
    - `tests/Unit/AcademicStructure/Application/TeachingQueryTest.php`
      › «sabe quién guía una sección»;
    - `tests/Unit/Enrollment/Application/EnrollmentPeriodsTest.php`.
- [x] **T-06** — Puertos de Evaluation, `EvaluationAccess`,
  `EvaluationAudit` y los adaptadores en memoria de los tests.
  - Criterios: EVA-R07.1, R07.6, R10.1, NF01
  - Test:
    - `tests/Unit/Evaluation/Application/EvaluationAccessTest.php`;
    - `tests/Unit/Evaluation/Application/EvaluationAuditTest.php` › «el
      plan antes y después, las notas que cambiaron».
- [x] **T-07** — `SavePlanHandler`.
  - Criterios: EVA-R01.1 – R01.8, R06.1, R06.4, R07.1, R10.1
  - Test: `tests/Unit/Evaluation/Application/SavePlanTest.php`:
    - solo quien dicta;
    - materia del pensum;
    - lapso editable;
    - quitar con confirmación queda en la bitácora.
- [x] **T-08** — `SaveActivityGradesHandler`.
  - Criterios: EVA-R02.1 – R02.8, R06.1 – R06.4, R07.1, R10.1, R01.8
  - Test: `tests/Unit/Evaluation/Application/SaveActivityGradesTest.php`:
    - guarda una sección entera;
    - rechaza un estudiante sin inscripción vigente;
    - sin plan;
    - lapso cerrado, con permiso o sin él;
    - año cerrado;
    - lapso sin abrir;
    - bitácora con antes y después.
- [x] **T-09** — `GradebookQuery`, la planilla.
  - Criterios: EVA-R03.1 – R03.7, R07.3 – R07.6
  - Test: `tests/Unit/Evaluation/Application/GradebookTest.php`:
    - docente propio y ajeno;
    - coordinador por grados;
    - gestor;
    - NA e incompletas en la planilla.

### Fase 3 — Infraestructura

- [x] **T-10** — Migración `…_create_evaluation_tables`, en su propia tarea:
  - `evaluation_activities`, `activity_grades` y `student_marks`, con RLS;
  - los `CHECK` y los índices.
  - Criterios: EVA-NF02, R11.1
  - Test:
    - `tests/Feature/Evaluation/Infrastructure/EvaluationSchemaTest.php`;
    - `TenantTableTest`;
    - `up`, `down` y `up`.
- [x] **T-11** — Adaptadores, repositorios, `EloquentGradebook`, permisos
  por Gate, `…Facts` y `EvaluationServiceProvider`.
  - Criterios: EVA-R11.1 – R11.3, NF02
  - Test:
    - `tests/Feature/Evaluation/Infrastructure/EvaluationRepositoriesTest.php`;
    - `tests/Feature/Evaluation/EvaluationIsolationTest.php` (`rls`);
    - regla de arquitectura: Evaluation no importa la Infrastructure ni el
      Domain de otros módulos, y nadie importa Evaluation.

### Fase 4 — HTTP y pantallas

- [x] **T-12** — «Notas» y la planilla:
  - rutas, controladores y FormRequests (hasta 60 estudiantes por
    guardado);
  - errores;
  - entrada de menú «Notas»;
  - enlaces desde «Mis asignaciones».
  - Criterios: EVA-R01, R02, R03, R06, R07, NF01
  - Test:
    - `tests/Feature/Evaluation/GradesHttpTest.php`;
    - `tests/Feature/Evaluation/GradesPermissionsTest.php` (docente ajeno,
      coordinador, Representante con `ver-calificaciones`);
    - `MenuParityTest`.
- [x] **T-13** — Rendimiento y accesibilidad de la planilla.
  - Criterios: EVA-NF04, NF05
  - Test:
    - `tests/Feature/Evaluation/GradebookPerformanceTest.php` (`perf`):
      guardar 40 notas en menos de 500 ms (p95) y la planilla 40 × 10 en
      menos de 300 ms (p95);
    - `tests/Browser/GradebookTest.php`: axe, 360 px y teclado.

## PR B — Primaria, Inicial y cierre del lapso

### Fase 5 — Literales e informes

- [x] **T-14** — `Literal` y el agregado `StudentMarks`.
  - Criterios: EVA-R04.1 – R04.4, R05.1, R05.2
  - Test: `tests/Unit/Evaluation/Domain/StudentMarksTest.php`.
- [x] **T-15** — `SaveLiteralHandler`, `SaveFinalLiteralHandler`,
  `SaveReportHandler` y `MarksSheetQuery`.
  - Criterios: EVA-R04.1 – R04.5, R05.1 – R05.3, R06.1, R07.2, R10.1, R10.2
  - Test: `tests/Unit/Evaluation/Application/MarksTest.php`:
    - solo el guía;
    - el final, con el tercer lapso cerrado;
    - el informe sin su texto en la bitácora.
- [x] **T-16** — Pantallas de literales e informes de una sección.
  - Criterios: EVA-R04, R05, NF05
  - Test:
    - `tests/Feature/Evaluation/MarksHttpTest.php`;
    - `tests/Browser/MarksTest.php`.

### Fase 6 — Cierre del lapso

- [x] **T-17** — Punto de extensión `TermClosingGuard` en AcademicCalendar.
  `CloseTermHandler` acepta una justificación de excepción.
  - Criterios: EVA-R12.2, R12.4
  - Test: `tests/Unit/AcademicCalendar/Application/TermClosingGuardTest.php`.
    Los tests de cierre existentes no cambian su lógica.
- [x] **T-18** — `PendingGradesQuery` y `PendingGradesGuard`: cuenta, y
  rellena NP e «incompleto» por excepción.
  - Criterios: EVA-R12.1 – R12.5
  - Test:
    - `tests/Unit/Evaluation/Application/PendingGradesTest.php`;
    - `tests/Feature/Evaluation/Infrastructure/TermClosingIntegrationTest.php`.
- [x] **T-19** — El diálogo de cierre del lapso muestra los pendientes y
  pide la justificación para la excepción.
  - Criterios: EVA-R12.1, R12.3
  - Test: `tests/Feature/AcademicCalendar/CloseTermPendingGradesTest.php`.

## PR C — Prosecución y otros módulos

### Fase 7 — Lo que otros módulos preguntan

- [x] **T-20** — `OutcomeProposal` (dominio), punto de extensión
  `OutcomeProposer` en Enrollment y `GradeOutcomeProposer`. La propuesta
  aparece en «Prosecución» con «Confirmar propuesta».
  - Criterios: EVA-R08.1 – R08.5
  - Test:
    - `tests/Unit/Evaluation/Domain/OutcomeProposalTest.php`;
    - `tests/Feature/Enrollment/PromotionProposalTest.php`.
- [x] **T-21** — `GradedEnrollmentCheck`, `SubjectsWithGrades` y la regla del
  cambio de grado.
  - Criterios: EVA-R09.1 – R09.3
  - Test: `tests/Feature/Evaluation/Infrastructure/EvaluationHooksIntegrationTest.php`:
    - «una inscripción con notas solo la anula un directivo»;
    - «una materia con notas no sale del pensum del año activo»;
    - «tras cambiar de grado, las notas anteriores son de solo lectura».

### Fase 8 — Cierre

- [x] **T-22** — Cambios en otras specs:
  - SPEC-007: CAL-R04 y el punto de extensión;
  - SPEC-008: `guides()` y EAC-R07.3;
  - SPEC-010: `EnrollmentPeriodsQuery`, `OutcomeProposer` y MAT-R14.3;
  - SPEC-013: el menú y «Mis asignaciones»;
  - roadmap.

  Se aplica en el PR que cambia cada comportamiento (P-04): A para 013; B
  para 007; C para 008 y 010.
  - Criterios: —
  - Test: `tests/Architecture/SpecsTest.php`

## Verificación final

- [x] `php artisan test --compact` en verde (incluidos `--group=rls`,
  `--group=perf` y `tests/Isolation`), y tests de navegador en verde.
- [x] Tests de arquitectura en verde.
- [x] `vendor/bin/pint --dirty` sin cambios; `npm run build` sin errores.
- [x] Migración: `up`, `down` y `up`.
- [x] Matriz de trazabilidad completa.
- [ ] `/security-review` de cada PR (permisos y tenancy; S-12).
- [x] `estado` de requirements/design/tasks actualizado.

## Cobertura de criterios

| Grupo | Tareas |
|-------|--------|
| EVA-R01 | T-02, T-07, T-08, T-12 |
| EVA-R02 | T-01, T-03, T-04, T-05, T-08, T-12 |
| EVA-R03 | T-01, T-03, T-09, T-12 |
| EVA-R04 | T-14, T-15, T-16 |
| EVA-R05 | T-14, T-15, T-16 |
| EVA-R06 | T-07, T-08, T-12, T-15 |
| EVA-R07 | T-05, T-06, T-07, T-08, T-09, T-12, T-15 |
| EVA-R08 | T-20 |
| EVA-R09 | T-03, T-21 |
| EVA-R10 | T-06, T-07, T-08, T-15 |
| EVA-R11 | T-10, T-11 |
| EVA-R12 | T-17, T-18, T-19 |
| EVA-NF01 | T-06, T-12 |
| EVA-NF02 | T-10, T-11 |
| EVA-NF03 | T-01 |
| EVA-NF04 | T-13 |
| EVA-NF05 | T-13, T-16 |
