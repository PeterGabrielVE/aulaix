---
adr: 0013
título: El plan de evaluación pertenece a una sección o a un grupo de pendientes
estado: aceptado
fecha: 2026-10-30
spec: 016-revision-pendientes
---

# ADR 0013 — El plan de evaluación pertenece a una sección o a un grupo de pendientes

## Contexto

En SPEC-011 cada plan de actividades pertenece a una sección:
- la tabla `evaluation_plans` es única por (año, sección, materia, lapso);
- a quién aplica una actividad sale de las inscripciones del estudiante en
  esa sección (periodos de SPEC-010).

SPEC-016 (RVP-R04) pide lo mismo para las materias pendientes:
- un plan por lapso, notas de 1 a 20, NP, nota del lapso y definitiva;
- un docente asignado;
- pero para un **grupo de pendientes**, que no es una sección. Reúne a los
  estudiantes de distintas secciones y grados del año que deben la misma
  materia del mismo grado de origen.

Todas las reglas del plan y de las notas (EVA-R01 – R03, R06, R12) tienen
que valer igual en los dos casos.

## Decisión

**El plan tiene un dueño: una sección o un grupo de pendientes.**

- **Dominio:**
  - `PlanOwner`, un value object con dos formas: `section(id)` y
    `pendingGroup(id)`;
  - `EvaluationPlan` guarda su dueño y las reglas del plan no cambian.
- **A quién aplica una actividad** lo responde un `ActivityAudience` según
  el dueño:
  - en una sección, los periodos de inscripción, como hoy (NA incluido);
  - en un grupo, sus miembros: la actividad aplica a todos.

  `ActivityGrades::record()` pregunta a la audiencia en vez de a los
  periodos.
- **Datos:**
  - `evaluation_plans.section_id` pasa a ser nulo y se añade
    `pending_group_id`;
  - un `CHECK` exige exactamente uno de los dos;
  - hay dos índices únicos parciales: (año, sección, materia, lapso) y
    (grupo, lapso).
- **Lo que no cambia:** `activity_grades` (las notas siguen siendo por
  actividad y estudiante), la planilla, los casos de uso de guardar plan y
  notas, y el cierre del lapso. Solo deciden quién puede escribir según el
  dueño.

## Alternativas consideradas

- **Tablas propias para los planes y las notas de pendientes.** Habría que
  duplicar el agregado, sus reglas y sus tests, y el cierre del lapso, la
  propuesta y la planilla tendrían que leer de dos sitios. Descartada.
- **El grupo de pendientes como una sección más en AcademicStructure.** Una
  sección tiene grado, letra y cupo, y sus estudiantes vienen de
  inscripciones. Un grupo no tiene nada de eso, y sus miembros se derivan
  de los resultados de prosecución. Obligaría a inscribir a cada estudiante
  en su grupo y a mantener esa inscripción sincronizada con los resultados.
  Descartada.
- **Un plan por estudiante y pendiente.** Contradice la decisión del
  usuario (grupo por materia y grado de origen) y multiplica los planes.
  Descartada.

## Consecuencias

- **Refactor de SPEC-011 sin cambiar su comportamiento:** el acceso por
  `sectionId()` pasa a `owner()`. Los tests existentes conservan su lógica;
  solo cambia cómo construyen el plan (Q-03).
- **Migración con cambio de restricciones** sobre una tabla con datos. Los
  planes existentes quedan con su sección y nada se mueve. El `down` borra
  primero los planes de grupos.
- **Un tercer dueño** (por ejemplo, cursos extracurriculares) cabría con
  otra forma de `PlanOwner` y su audiencia.
