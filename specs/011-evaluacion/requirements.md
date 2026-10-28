---
spec: 011-evaluacion
prefijo: EVA
estado: aprobada
fecha: 2026-10-28
aprobada: 2026-10-28
backlog: F1-28 – F1-31
módulo: Evaluation
depende-de: 005-auditoria, 007-anio-escolar (lapsos, TermEditabilityQuery), 008-estructura-academica (pensum, TeachingQuery), 010-inscripcion (quién está en cada sección, prosecución)
la-usan: 010-inscripcion (propuesta del resultado, inscripciones con notas), 012-documentos (boletas y certificaciones), portal del representante (F2-07), revisión y pendientes (F2-01, F2-02)
---

# Requisitos — Evaluación

## Contexto

Con los estudiantes inscritos en sus secciones (SPEC-010) y el pensum de
cada grado (SPEC-008), falta lo que da sentido al año: evaluar.
- **Media General:** cada docente planifica sus actividades por lapso, carga
  las notas y obtiene la nota del lapso y la definitiva de su materia.
- **Primaria:** el docente guía pone un literal por lapso y uno final.
- **Educación Inicial:** el docente guía escribe un informe descriptivo.

Con esas notas el sistema propone el resultado de prosecución que hoy indica
a mano el gestor (SPEC-010), y la estructura y la inscripción saben qué ya
tiene notas.

Backlog: F1-28 – F1-31. Resuelve la parte de F0-03 que afecta a las notas
con las decisiones de abajo.

## Alcance

- **Incluye:**
  - el plan de evaluación de cada materia y lapso en Media General:
    actividades con porcentaje que suman 100 %;
  - la carga de notas por actividad, con «no presentó»;
  - la nota del lapso y la definitiva del año por materia en Media General;
  - el literal por lapso y el final en Primaria, con su descripción;
  - el informe descriptivo por lapso en Educación Inicial;
  - el bloqueo de las notas de un lapso cerrado (SPEC-007), salvo con el
    permiso especial;
  - el cierre del lapso: no se cierra con notas pendientes salvo por
    excepción de un directivo, con justificación;
  - «no aplica» (NA) para las actividades anteriores a la incorporación del
    estudiante, con el porcentaje repartido entre las demás;
  - la propuesta del resultado de prosecución para SPEC-010;
  - qué inscripciones y materias tienen notas, para SPEC-010 y SPEC-008;
  - quién carga y quién consulta, la bitácora y el aislamiento por plantel.
- **Excluye:**
  - la revisión de notas y las materias pendientes (F2-01, F2-02);
  - las boletas, constancias y certificaciones en PDF (SPEC-012);
  - lo que ve el representante en su portal (F2-07);
  - la asistencia (F2-03, F2-04);
  - importar notas desde una hoja de cálculo;
  - escalas propias de cada plantel;
  - la aprobación del plan de evaluación por la coordinación (ver Q4).

## Decisiones de producto incorporadas (2026-10-28)

| Pregunta | Decisión |
|----------|----------|
| Alcance | Plan, notas, nota del lapso, definitiva y propuesta del resultado de prosecución. Revisión y pendientes, en otra spec. |
| Escala de Media General | Notas de 1 a 20; se aprueba con 10. |
| Redondeo | Las notas de las actividades admiten decimales. La nota del lapso y la definitiva se redondean al entero más cercano, con ,5 hacia arriba (9,5 → 10). |
| Definitiva | Promedio simple de las tres notas de lapso, redondeado. |
| Plan de evaluación | Por materia, sección y lapso, el docente define actividades con nombre, fecha y porcentaje; suman 100 %. La nota del lapso es la suma ponderada. |
| Primaria | El docente guía pone un literal de la A a la E por lapso, con una descripción, y uno final. El final lo elige el guía; el sistema no lo calcula. |
| Educación Inicial | Un informe descriptivo por lapso, sin literal ni nota. |
| Quién carga | Cada docente, las notas de las materias y secciones que dicta (SPEC-008); en Primaria e Inicial, el guía de la sección. |
| Quién consulta | Administrador y Director, todas; Coordinador, las de sus grados; cada docente, las suyas. |
| No presentó | Se registra «NP», que cuenta como 1 en el cálculo y queda distinguido de un 1. |
| Cambiar el plan con notas | Sí, si sigue sumando 100 % y el lapso está abierto. Quitar una actividad con notas pide confirmación y queda en la bitácora. |
| Cierre del lapso con notas pendientes | Se bloquea. Antes de cerrar se muestra la lista de pendientes por docente, sección, materia y actividad, con cuántos estudiantes. Un directivo puede cerrar por excepción, con justificación en la bitácora: las notas vacías de Media General quedan NP, y los literales e informes que falten quedan «incompleto». |
| Estudiante incorporado a mitad de lapso | Las actividades con fecha anterior a su inscripción quedan «no aplica» (NA), nunca NP. Su nota del lapso reparte el 100 % entre las actividades restantes. |
| Materia con una sola nota | El plan sigue siendo obligatorio; vale un plan de una sola actividad con el 100 %. |
| Prosecución | 011 propone el resultado y el gestor lo confirma o lo cambia en «Prosecución» (SPEC-010). Media General: 0 materias reprobadas → promovido; 1 o 2 → promovido con esas materias pendientes; 3 o más → repite. Primaria: literal final E → repite; A – D → promovido. |

## Glosario

| Término | Significado |
|---------|-------------|
| Plan de evaluación | Las actividades de una materia, en una sección y un lapso, con su porcentaje. |
| Actividad | Una evaluación del plan: nombre, fecha y porcentaje. |
| Nota | La calificación de un estudiante en una actividad: de 1 a 20, con decimales, o «NP». |
| NP | «No presentó»: cuenta como 1 en el cálculo. |
| NA | «No aplica»: actividad anterior a la incorporación del estudiante. No cuenta, y su porcentaje se reparte entre las demás. |
| Nota pendiente | Casilla sin nota, ni NP ni NA, de un estudiante con inscripción vigente; o literal o informe que falta. |
| Nota del lapso | Suma de las notas de las actividades ponderadas por su porcentaje (repartido sin las NA), redondeada al entero. |
| Definitiva | Promedio de las tres notas de lapso de una materia, redondeado al entero. |
| Materia reprobada | Materia de Media General con definitiva menor que 10. |
| Literal | Calificación de Primaria: A, B, C, D o E. |
| Informe descriptivo | Texto con que se evalúa un lapso en Educación Inicial. |
| Docente de la materia | Quien dicta esa materia en esa sección (SPEC-008). |
| Lapso editable | Lapso abierto; uno cerrado solo lo es para quien tiene `editar-notas-lapso-cerrado`, nunca con el año cerrado (CAL-R05). |

## Requisitos

### EVA-R01 — Plan de evaluación (Media General)

**Historia:** Como docente de Media General, quiero definir las actividades
de mi materia en cada lapso con su porcentaje para que la nota del lapso
salga de ellas.

**Criterios de aceptación (EARS):**

- **EVA-R01.1** — Cuando el docente de la materia guarda el plan de una
  materia, sección y lapso con actividades de nombre, fecha y porcentaje, el
  sistema deberá registrarlo.
- **EVA-R01.2** — Si los porcentajes no suman 100 %, entonces el sistema
  deberá rechazar el plan e indicar cuánto suman.
- **EVA-R01.3** — Si una actividad no tiene nombre, o su porcentaje no es
  mayor que 0, o su fecha no cae dentro del lapso, entonces el sistema
  deberá rechazar el plan e indicar qué actividad.
- **EVA-R01.4** — Si el lapso no es editable, entonces el sistema deberá
  rechazar el cambio del plan.
- **EVA-R01.5** — Cuando el docente quita del plan una actividad con notas,
  el sistema deberá pedir confirmación y, al confirmarla, quitarla con sus
  notas y dejarlo en la bitácora.
- **EVA-R01.6** — Si la materia no está en el pensum del grado de la sección
  (SPEC-008), entonces el sistema deberá rechazar el plan.
- **EVA-R01.7** — El sistema deberá aceptar un plan de una sola actividad con
  el 100 %.
- **EVA-R01.8** — Si la materia no tiene plan en el lapso, entonces el sistema
  deberá rechazar sus notas e indicar que primero se registra el plan.

### EVA-R02 — Notas por actividad (Media General)

**Historia:** Como docente de Media General, quiero cargar las notas de una
actividad para todos los estudiantes de la sección de una vez.

**Criterios de aceptación (EARS):**

- **EVA-R02.1** — Cuando el docente de la materia guarda las notas de una
  actividad, el sistema deberá registrarlas para los estudiantes con
  inscripción vigente en la sección.
- **EVA-R02.2** — Si una nota no está entre 1 y 20, o tiene más de dos
  decimales, entonces el sistema deberá rechazarla e indicar de qué
  estudiante.
- **EVA-R02.3** — Cuando el docente marca a un estudiante como «no presentó»,
  el sistema deberá registrar NP para esa actividad.
- **EVA-R02.4** — El sistema deberá permitir dejar sin nota a un estudiante
  en una actividad.
- **EVA-R02.5** — Si el estudiante no tiene inscripción vigente en la
  sección, entonces el sistema deberá rechazar su nota.
- **EVA-R02.6** — Si el lapso no es editable, entonces el sistema deberá
  rechazar las notas.
- **EVA-R02.7** — Cuando un estudiante cambia de sección dentro de su grado
  (MAT-R04.1), el sistema deberá conservar sus notas del año en esa materia.
  En la nueva sección, el docente de la materia las ve y las completa.
- **EVA-R02.8** — Cuando un estudiante se incorpora al grado después de la
  fecha de una actividad, el sistema deberá registrar NA en esa actividad y
  no permitir que se le ponga nota ni NP.

### EVA-R03 — Nota del lapso y definitiva (Media General)

**Historia:** Como docente o directivo, quiero ver la nota del lapso y la
definitiva calculadas con las reglas oficiales para no hacerlo a mano.

**Criterios de aceptación (EARS):**

- **EVA-R03.1** — El sistema deberá calcular la nota del lapso de cada
  estudiante como la suma de sus notas ponderadas por el porcentaje de cada
  actividad, con NP como 1, redondeada al entero con ,5 hacia arriba.
- **EVA-R03.2** — Mientras a un estudiante le falte la nota de alguna
  actividad del plan, el sistema deberá mostrar su nota del lapso como
  incompleta, sin número.
- **EVA-R03.3** — El sistema deberá calcular la definitiva de cada estudiante
  en una materia como el promedio de sus tres notas de lapso, redondeado al
  entero con ,5 hacia arriba.
- **EVA-R03.4** — Mientras falte alguna de las tres notas de lapso, el
  sistema deberá mostrar la definitiva como incompleta.
- **EVA-R03.5** — El sistema deberá marcar como reprobada la materia con
  definitiva menor que 10.
- **EVA-R03.6** — Cuando un estudiante tiene actividades NA, el sistema
  deberá repartir el 100 % entre sus demás actividades en proporción a sus
  porcentajes.
- **EVA-R03.7** — Si todas las actividades de un estudiante en un lapso son
  NA, entonces el sistema deberá mostrar su nota del lapso como «no aplica»
  y calcular la definitiva con los lapsos restantes.

### EVA-R04 — Literales (Primaria)

**Historia:** Como docente guía de Primaria, quiero poner un literal y una
descripción por lapso, y el literal final, a cada estudiante.

**Criterios de aceptación (EARS):**

- **EVA-R04.1** — Cuando el guía de una sección de Primaria guarda el literal
  de un estudiante en un lapso, con su descripción, el sistema deberá
  registrarlo.
- **EVA-R04.2** — Si el literal no es A, B, C, D o E, entonces el sistema
  deberá rechazarlo.
- **EVA-R04.3** — Cuando el guía guarda el literal final de un estudiante, el
  sistema deberá registrarlo.
- **EVA-R04.4** — Si el tercer lapso no está cerrado, entonces el sistema
  deberá rechazar el literal final.
- **EVA-R04.5** — Si el lapso no es editable, entonces el sistema deberá
  rechazar el literal del lapso.

### EVA-R05 — Informe descriptivo (Educación Inicial)

**Historia:** Como docente guía de Maternal o Preescolar, quiero escribir el
informe de cada estudiante en cada lapso.

**Criterios de aceptación (EARS):**

- **EVA-R05.1** — Cuando el guía de una sección de Educación Inicial guarda el
  informe de un estudiante en un lapso, el sistema deberá registrarlo.
- **EVA-R05.2** — Si el informe está vacío o pasa de 5000 caracteres,
  entonces el sistema deberá rechazarlo.
- **EVA-R05.3** — Si el lapso no es editable, entonces el sistema deberá
  rechazar el informe.

### EVA-R06 — Lapso cerrado

**Historia:** Como directivo, quiero que las notas de un lapso cerrado no
cambien salvo con un permiso especial para que lo entregado sea firme.

**Criterios de aceptación (EARS):**

- **EVA-R06.1** — Mientras un lapso esté cerrado, el sistema deberá rechazar
  cambios en sus planes, notas, literales e informes, salvo para quien tenga
  `editar-notas-lapso-cerrado` (CAL-R05.2).
- **EVA-R06.2** — Mientras el año escolar esté cerrado, el sistema deberá
  rechazar todo cambio en sus notas, también con ese permiso (CAL-R05.3).
- **EVA-R06.3** — Cuando alguien con el permiso cambia una nota de un lapso
  cerrado, el sistema deberá registrar en la bitácora el valor anterior, el
  nuevo y quién lo cambió.
- **EVA-R06.4** — Mientras un lapso no se haya abierto, el sistema deberá
  rechazar sus notas.

### EVA-R07 — Quién carga y quién consulta

**Historia:** Como directivo, quiero que cada docente cargue solo lo suyo y
que cada uno vea lo que le corresponde.

**Criterios de aceptación (EARS):**

- **EVA-R07.1** — El sistema deberá permitir cargar planes y notas de una
  materia y sección solo a quien la dicta (SPEC-008) con
  `gestionar-calificaciones`.
- **EVA-R07.2** — El sistema deberá permitir cargar literales e informes de
  una sección solo a su docente guía con `gestionar-calificaciones`.
- **EVA-R07.3** — El sistema deberá mostrar a cada docente las notas de las
  materias y secciones que dicta o guía.
- **EVA-R07.4** — El sistema deberá mostrar a quien tenga `ver-calificaciones`
  y gestione estudiantes (Administrador, Director) todas las notas del
  plantel.
- **EVA-R07.5** — El sistema deberá mostrar al Coordinador las notas de los
  grados que coordina.
- **EVA-R07.6** — Si alguien intenta cargar o consultar notas que no le
  corresponden, entonces el sistema deberá rechazarlo sin cambiar nada, y en
  la consulta responder como si no existieran.

### EVA-R08 — Prosecución (SPEC-010)

**Historia:** Como gestor de inscripciones, quiero que el sistema proponga el
resultado de cada estudiante a partir de sus notas para confirmarlo en vez
de calcularlo.

**Criterios de aceptación (EARS):**

- **EVA-R08.1** — Cuando todas las definitivas de un estudiante de Media
  General están completas, el sistema deberá proponer:
  - promovido, sin materias reprobadas;
  - promovido con materia pendiente, con 1 o 2 reprobadas (esas materias);
  - repite, con 3 o más.
- **EVA-R08.2** — Cuando un estudiante de Primaria tiene literal final, el
  sistema deberá proponer «repite» con E y «promovido» con A – D.
- **EVA-R08.3** — El sistema deberá proponer «promovido» para Educación
  Inicial.
- **EVA-R08.4** — El sistema deberá mostrar la propuesta junto a cada
  estudiante en la pantalla «Prosecución» (SPEC-010), y el gestor deberá
  poder confirmarla o registrar otro resultado permitido (MAT-R06).
- **EVA-R08.5** — Mientras falten notas para proponer, el sistema deberá
  mostrar al estudiante sin propuesta e indicar qué falta.

### EVA-R09 — Lo que otros módulos preguntan

- **EVA-R09.1** — El sistema deberá permitir a SPEC-010 saber si una
  inscripción tiene notas, literales o informes (MAT-R14.3).
- **EVA-R09.2** — El sistema deberá permitir a SPEC-008 saber qué materias de
  un grado tienen notas en un año (EAC-R07.3).
- **EVA-R09.3** — Cuando un estudiante cambia de grado (MAT-R04.7), el sistema
  deberá conservar sus notas del grado anterior solo para consulta y aceptar
  las del nuevo grado.

### EVA-R10 — Bitácora

- **EVA-R10.1** — El sistema deberá registrar en la bitácora del plantel:
  - cada cambio del plan de evaluación, con las actividades antes y
    después;
  - cada guardado de notas de una actividad, con las notas que cambiaron,
    antes y después;
  - cada literal, literal final e informe registrado o cambiado;
  - cada cambio en un lapso cerrado, con quién lo hizo (EVA-R06.3).
- **EVA-R10.2** — El sistema no deberá registrar en la bitácora el texto de
  los informes descriptivos, solo que cambiaron.

### EVA-R11 — Aislamiento por plantel

- **EVA-R11.1** — El sistema deberá mostrar y modificar solo los planes y
  notas del plantel abierto.
- **EVA-R11.2** — El sistema deberá tomar el plantel únicamente del
  subdominio (S-02).
- **EVA-R11.3** — Si se intenta usar una sección, una materia, una actividad o
  un estudiante de otro plantel, entonces el sistema deberá responder como si
  no existieran.

### EVA-R12 — Cierre del lapso

**Historia:** Como directivo, quiero que un lapso no se cierre con notas
pendientes salvo por una excepción justificada, para que lo entregado esté
completo.

**Criterios de aceptación (EARS):**

- **EVA-R12.1** — Cuando un gestor va a cerrar un lapso, el sistema deberá
  mostrar las notas pendientes por docente, sección, materia y actividad (o
  literal o informe), con cuántos estudiantes.
- **EVA-R12.2** — Si quedan notas pendientes y no se pide la excepción,
  entonces el sistema deberá rechazar el cierre del lapso (SPEC-007) e
  indicar cuántas.
- **EVA-R12.3** — Cuando un directivo cierra el lapso por excepción con una
  justificación, el sistema deberá registrar NP en las notas vacías de Media
  General, «incompleto» en los literales e informes que falten, cerrar el
  lapso y dejar la justificación en la bitácora.
- **EVA-R12.4** — Si quien pide la excepción no es directivo, o falta la
  justificación, entonces el sistema deberá rechazar el cierre.
- **EVA-R12.5** — El sistema no deberá contar como pendientes las
  actividades NA ni a los estudiantes sin inscripción vigente.

## Requisitos no funcionales

- **EVA-NF01** (seguridad) — Toda carga exige `gestionar-calificaciones` en
  la ruta y, en el caso de uso, que la persona dicte esa materia o guíe esa
  sección (S-04).
- **EVA-NF02** (seguridad) — Los planes, notas, literales e informes viven en
  tablas con RLS por plantel (S-01).
- **EVA-NF03** (exactitud) — La nota del lapso y la definitiva se calculan
  con aritmética decimal exacta, nunca de coma flotante: 9,5 siempre da 10.
- **EVA-NF04** (rendimiento) — Guardar las notas de una actividad para una
  sección de 40 estudiantes tarda menos de 500 ms (p95). La planilla de una
  materia y sección con 40 estudiantes, 10 actividades y sus notas de lapso
  carga en menos de 300 ms (p95).
- **EVA-NF05** (usabilidad) — La carga de notas funciona como una planilla:
  se recorre con el teclado, a 360 px de ancho, y pasa axe sin
  infracciones graves.

## Cambios en otras specs

- **SPEC-007:**
  - las notas aplican CAL-R05 a través de `TermEditabilityQuery`;
  - el cierre del lapso gana una condición: sin notas pendientes, salvo la
    excepción de un directivo (EVA-R12).
- **SPEC-008:** EAC-R07.3 (materias con notas) se activa con EVA-R09.2.
- **SPEC-010:**
  - MAT-R14.3 («ya tiene notas») se activa con EVA-R09.1;
  - la pantalla «Prosecución» muestra la propuesta (EVA-R08.4);
  - MAT-R04.7 (cambio de grado) se completa con EVA-R09.3.

## Fuera de alcance y preguntas abiertas

Resueltas el 2026-10-28 (ver Decisiones): cierre del lapso con notas
pendientes, estudiante incorporado a mitad de lapso y materia con una sola
nota.

- [x] **Q4 — Aprobación del plan por la coordinación.** La normativa pide que
  el plan esté aprobado por la coordinación antes de cargar notas. ¿Lo exige
  el sistema (el Coordinador aprueba y hasta entonces no se cargan notas) o
  queda fuera por ahora? *Decidido con el diseño (2026-10-28): queda fuera;
  el agregado admite un estado de aprobación después.*
- [x] **Q5 — Quién es el directivo de la excepción.** EVA-R12.3 la deja a un
  directivo. ¿Basta con quien cierra el lapso (`gestionar-calendario`:
  Administrador y Director) o hace falta un permiso propio? *Decidido con el
  diseño (2026-10-28): quien cierra lapsos, con justificación obligatoria.*

**Fuera de alcance:** ver «Excluye» en el alcance.

## Matriz de trazabilidad

| Criterio | Test(s) |
|----------|---------|
| EVA-R01.1 | |
| EVA-R01.2 | |
| EVA-R01.3 | |
| EVA-R01.4 | |
| EVA-R01.5 | |
| EVA-R01.6 | |
| EVA-R01.7 | |
| EVA-R01.8 | |
| EVA-R02.1 | |
| EVA-R02.2 | |
| EVA-R02.3 | |
| EVA-R02.4 | |
| EVA-R02.5 | |
| EVA-R02.6 | |
| EVA-R02.7 | |
| EVA-R02.8 | |
| EVA-R03.1 | |
| EVA-R03.2 | |
| EVA-R03.3 | |
| EVA-R03.4 | |
| EVA-R03.5 | |
| EVA-R03.6 | |
| EVA-R03.7 | |
| EVA-R04.1 | |
| EVA-R04.2 | |
| EVA-R04.3 | |
| EVA-R04.4 | |
| EVA-R04.5 | |
| EVA-R05.1 | |
| EVA-R05.2 | |
| EVA-R05.3 | |
| EVA-R06.1 | |
| EVA-R06.2 | |
| EVA-R06.3 | |
| EVA-R06.4 | |
| EVA-R07.1 | |
| EVA-R07.2 | |
| EVA-R07.3 | |
| EVA-R07.4 | |
| EVA-R07.5 | |
| EVA-R07.6 | |
| EVA-R08.1 | |
| EVA-R08.2 | |
| EVA-R08.3 | |
| EVA-R08.4 | |
| EVA-R08.5 | |
| EVA-R09.1 | |
| EVA-R09.2 | |
| EVA-R09.3 | |
| EVA-R10.1 | |
| EVA-R10.2 | |
| EVA-R11.1 | |
| EVA-R11.2 | |
| EVA-R11.3 | |
| EVA-R12.1 | |
| EVA-R12.2 | |
| EVA-R12.3 | |
| EVA-R12.4 | |
| EVA-R12.5 | |
| EVA-NF01 | |
| EVA-NF02 | |
| EVA-NF03 | |
| EVA-NF04 | |
| EVA-NF05 | |
