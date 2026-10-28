---
spec: 010-inscripcion
prefijo: MAT
estado: aprobada
fecha: 2026-10-27
aprobada: 2026-10-27
backlog: F1-25 – F1-27
módulo: Enrollment
depende-de: 005-auditoria, 007-anio-escolar, 008-estructura-academica (grados, secciones, cupos, pensum, docentes), 009-estudiantes (ficha, retiro, traslado)
la-usan: 011-evaluacion (quién está en cada sección), 012-documentos (constancias de inscripción y prosecución), 013-layout-menu (inscritos por sección)
---

# Requisitos — Inscripción y prosecución

## Contexto

Con la estructura del año (SPEC-008) y las fichas de los estudiantes
(SPEC-009), falta lo que las une: en qué grado y sección está cada estudiante
en cada año escolar. Sin eso no se pueden cargar notas (SPEC-011), emitir
constancias (SPEC-012) ni limitar lo que ve cada docente.

Al terminar el año, cada estudiante es promovido, repite o pasa con materias
pendientes, y se inscribe en el año siguiente según ese resultado. Hasta que
exista SPEC-011, el gestor indica el resultado; las reglas de cada nivel
limitan lo que puede elegir.

Backlog: F1-25 – F1-27. Desbloquea la parte de F0-03 que afecta a la
prosecución con las decisiones de abajo.

## Alcance

- **Incluye:**
  - inscribir a un estudiante en un año escolar, un grado y una sección;
  - superar el cupo de una sección indicando un motivo;
  - la edad esperada de cada grado: aviso fuera de la edad regular y
    excepción aprobada por un directivo fuera del rango permitido;
  - el cambio de sección durante el año, con fecha, motivo e historial;
  - el cambio de grado durante el año, excepcional y aprobado por un
    directivo, conservando el historial;
  - la anulación de una inscripción hecha por error, sin borrarla;
  - el fin de la inscripción por retiro o traslado (SPEC-009);
  - el resultado de prosecución de cada estudiante al terminar el año
    (promovido, repite, promovido con materia pendiente, perdió por
    inasistencia), limitado por las reglas de su nivel;
  - el egreso de quien aprueba el último año, y el estudiante de 5.º año
    pendiente de egresar;
  - la lista previa al cierre del año: no se cierra con estudiantes sin
    resultado;
  - la inscripción en lote en el año siguiente según ese resultado;
  - que Docente y Coordinador vean solo a los estudiantes de sus secciones o
    grados;
  - la bitácora, los permisos y el aislamiento por plantel.
- **Excluye:**
  - calcular el resultado a partir de las notas (SPEC-011): aquí lo indica el
    gestor;
  - el seguimiento y la aprobación de las materias pendientes, incluida la
    inscripción del estudiante de 5.º año en sus materias pendientes
    (F2-02, módulo `Evaluation`);
  - congelar las notas del grado anterior en un cambio de grado y habilitar
    las del nuevo (SPEC-011);
  - retener el título de quien está pendiente de egresar, e invalidar la
    constancia de una inscripción anulada (SPEC-012);
  - edades esperadas distintas por plantel;
  - los recaudos de la inscripción (partida de nacimiento, fotos,
    constancias): podrían añadirse en una spec posterior;
  - las constancias de inscripción y de prosecución en PDF (SPEC-012);
  - la preinscripción o solicitud de cupo por parte de los representantes;
  - el cobro de la inscripción (`Billing`, F2-15 y siguientes);
  - importar inscripciones en lote desde el SIGE o una hoja de cálculo.

## Decisiones de producto incorporadas (2026-10-27)

| Pregunta | Decisión |
|----------|----------|
| Alcance | Inscripción y prosecución manual: el gestor indica el resultado; SPEC-011 lo propondrá a partir de las notas. |
| Quién inscribe | Permiso nuevo `gestionar-inscripciones`, para Administrador y Director; el Administrador puede concederlo a otros miembros, persona por persona (p. ej. la secretaria). |
| Sección llena | Se permite inscribir por encima del cupo indicando un motivo, que queda en la bitácora. |
| Prosecución en Educación Inicial | Maternal y Preescolar no se repiten: el estudiante siempre es promovido. |
| Prosecución en Primaria | Promovido o repite. Con SPEC-011, repite quien tenga literal E; con A – D es promovido. |
| Prosecución en Media General | Se aprueba una materia con 10/20. Con 1 o 2 materias reprobadas, promovido con materia pendiente; con 3 o más, repite. |
| Las reglas en 010 | Limitan lo que el gestor puede elegir: en Inicial solo «promovido»; en Media, «materia pendiente» exige 1 o 2 materias del pensum, y con 3 o más debe elegir «repite». |
| Edad y grado | La edad se calcula al 31 de diciembre del año escolar en curso. Fuera de la edad regular pero dentro del rango permitido: aviso, sin bloqueo. Fuera del rango: solo con una excepción aprobada por un directivo, con justificación. Las edades y la fecha de corte se pueden cambiar sin tocar el código. |
| Edades por grado (Primaria y Media) | 1.º – 6.º grado: regular de 6 a 11 años, con un rango de un año menos a un año más (1.º grado: 5 – 7). 1.º – 4.º año: regular de 12 a 15, con el mismo rango. 5.º año: regular 16, rango 15 – 18. |
| 5.º año con materia pendiente | No egresa: queda «pendiente de egresar» hasta aprobar todas las materias. No se inscribe en un año nuevo, sino solo en sus materias pendientes (F2-02); el título queda retenido (SPEC-012). |
| Cierre del año | No se cierra mientras haya estudiantes activos sin resultado. Antes del cierre se muestra la lista; a quien abandonó se le registra el retiro (SPEC-009) o «perdió por inasistencia». |
| Inscripción por error | Se anula sin borrarla, con motivo (error de digitación, doble registro), y queda en el historial y la bitácora. Hasta 48 horas después de registrarla y sin notas, la anula el gestor; después, o con notas, solo un directivo. |
| Cambio de grado durante el año | Excepcional, aprobado por un directivo, con motivo y fecha; conserva el historial. Las notas del grado anterior se congelan (SPEC-011). |
| Directivo | Quien tiene el permiso nuevo `aprobar-excepciones-inscripcion`: Administrador y Director. |
| Recaudos | Fuera de alcance. |
| Visibilidad de Docente y Coordinador | Solo los estudiantes de sus secciones (docente por materia o guía) o de los grados que coordinan. Pendiente desde SPEC-008 y SPEC-009. |
| Cambio de sección | Durante el año, con fecha y motivo, conservando el historial. |
| Retiro | El retiro (SPEC-009) termina la inscripción; al reincorporar al estudiante hay que inscribirlo de nuevo. |
| Inscripción en lote | Al pasar de año, se inscribe de una vez a los estudiantes en el grado que les corresponde por su resultado. |

## Glosario

| Término | Significado |
|---------|-------------|
| Inscripción | Un estudiante en un año escolar, un grado y una sección, con su fecha. Como máximo una vigente por estudiante y año. |
| Inscripción vigente | La que no ha terminado por retiro, traslado o cambio de sección. |
| Gestor de inscripciones | Quien tiene `gestionar-inscripciones` en el plantel abierto. |
| Sobrecupo | Inscribir en una sección que ya tiene tantos estudiantes como su cupo. |
| Prosecución | El paso de un estudiante de un año escolar al siguiente según su resultado. |
| Resultado de prosecución | Promovido, repite, promovido con materia pendiente o perdió por inasistencia. |
| Perdió por inasistencia | Resultado de quien no concluyó el año por inasistencia; como «repite», vuelve a cursar el mismo grado. |
| Egresado | Quien es promovido del último año de Media General sin materias pendientes. |
| Pendiente de egresar | Estudiante de 5.º año promovido con materia pendiente: no egresa hasta aprobarlas. |
| Anulación | Fin de una inscripción hecha por error: no cuenta para nada, pero se conserva con su motivo. |
| Directivo | Quien tiene `aprobar-excepciones-inscripcion` en el plantel abierto. |
| Materia pendiente | Materia del pensum de Media General reprobada con la que el estudiante es promovido; se aprueba después (F2-02). |
| Grado siguiente | El que sigue en el orden oficial de SPEC-008: Maternal I – III, Preescolar 1.º – 3.er nivel, Primaria 1.º – 6.º grado, Media General 1.º – 5.º año. |
| Edad regular | La edad que corresponde a un grado, calculada al 31 de diciembre del año escolar en curso. |
| Rango permitido | Las edades mínima y máxima con las que se inscribe en un grado sin excepción. |

## Requisitos

### MAT-R01 — Inscribir a un estudiante

**Historia:** Como gestor de inscripciones, quiero inscribir a un estudiante
en un grado y una sección del año escolar para que conste dónde estudia.

**Criterios de aceptación (EARS):**

- **MAT-R01.1** — Cuando un gestor inscribe a un estudiante activo en una
  sección de un año escolar planificado o activo, el sistema deberá
  registrar la inscripción con su fecha, el grado y la sección.
- **MAT-R01.2** — Si el estudiante ya tiene una inscripción vigente en ese
  año escolar, entonces el sistema deberá rechazar la inscripción e indicar
  en qué grado y sección está.
- **MAT-R01.3** — Si el estudiante está retirado, entonces el sistema deberá
  rechazar la inscripción e indicar que primero hay que reincorporarlo.
- **MAT-R01.4** — Si el año escolar está cerrado, entonces el sistema deberá
  rechazar la inscripción.
- **MAT-R01.5** — Si la sección no es de ese año escolar, entonces el sistema
  deberá rechazar la inscripción.
- **MAT-R01.6** — Si la fecha de inscripción es posterior a hoy, entonces el
  sistema deberá rechazarla.

### MAT-R02 — Cupo

**Historia:** Como gestor de inscripciones, quiero poder superar el cupo de
una sección con un motivo para atender casos excepcionales sin perder el
control.

**Criterios de aceptación (EARS):**

- **MAT-R02.1** — Si la sección ya tiene tantas inscripciones vigentes como
  su cupo y no se indica un motivo, entonces el sistema deberá rechazar la
  inscripción e indicar el cupo y los inscritos.
- **MAT-R02.2** — Cuando un gestor inscribe por encima del cupo indicando un
  motivo, el sistema deberá registrar la inscripción con su motivo.
- **MAT-R02.3** — El sistema deberá mostrar en cada sección su cupo, sus
  inscritos vigentes y si está en sobrecupo.
- **MAT-R02.4** — Si dos gestores inscriben a la vez en el último lugar de
  una sección, sin motivo, entonces el sistema deberá aceptar solo una de
  las dos inscripciones.

### MAT-R03 — Edad y grado

**Historia:** Como gestor de inscripciones, quiero un aviso cuando la edad
del estudiante no corresponde al grado, y que los casos fuera de rango los
apruebe un directivo, para evitar errores sin impedir los casos legítimos.

**Criterios de aceptación (EARS):**

- **MAT-R03.1** — El sistema deberá calcular la edad del estudiante al 31 de
  diciembre del año escolar en curso.
- **MAT-R03.2** — Si la edad no es la regular del grado pero está dentro del
  rango permitido, entonces el sistema deberá inscribirlo y mostrar un aviso
  con su edad y la regular.
- **MAT-R03.3** — Si la edad está fuera del rango permitido y no se indica
  una excepción, entonces el sistema deberá rechazar la inscripción e
  indicar la edad del estudiante y el rango.
- **MAT-R03.4** — Cuando un directivo inscribe fuera del rango permitido con
  una justificación, el sistema deberá registrar la inscripción con la
  excepción, la justificación y quién la aprobó.
- **MAT-R03.5** — Si quien indica la excepción no es directivo, o falta la
  justificación, entonces el sistema deberá rechazar la inscripción.
- **MAT-R03.6** — El sistema deberá tomar la edad regular, el rango de cada
  grado y la fecha de corte de una configuración de la plataforma, que se
  puede cambiar sin modificar el código.
- **MAT-R03.7** — Mientras un grado no tenga edades configuradas (p. ej. los
  de Educación Inicial), el sistema deberá inscribir sin comprobar la edad.

### MAT-R04 — Cambio de sección

**Historia:** Como gestor de inscripciones, quiero cambiar a un estudiante de
sección durante el año para reorganizar los grupos sin perder su historial.

**Criterios de aceptación (EARS):**

- **MAT-R04.1** — Cuando un gestor cambia a un estudiante a otra sección del
  mismo grado y año, con fecha y motivo, el sistema deberá terminar la
  inscripción anterior en esa fecha y abrir la nueva desde ella.
- **MAT-R04.2** — Si falta la fecha o el motivo, o la fecha es anterior al
  inicio de la inscripción vigente o posterior a hoy, entonces el sistema
  deberá rechazar el cambio.
- **MAT-R04.3** — Si la sección destino es de otro año escolar, entonces el
  sistema deberá rechazar el cambio.
- **MAT-R04.4** — El sistema deberá aplicar al cambio de sección las reglas
  de cupo de MAT-R02.
- **MAT-R04.5** — Si el año escolar está cerrado, entonces el sistema deberá
  rechazar el cambio.
- **MAT-R04.6** — El sistema deberá mostrar en la ficha del estudiante el
  historial de sus inscripciones: año, grado, sección, desde, hasta y motivo
  del fin (cambio de sección, cambio de grado, retiro, traslado o
  anulación).
- **MAT-R04.7** — Cuando un directivo cambia a un estudiante a una sección de
  otro grado del mismo año, con fecha y motivo, el sistema deberá terminar
  la inscripción anterior en esa fecha, abrir la nueva desde ella y
  registrar quién lo aprobó.
- **MAT-R04.8** — Si quien cambia de grado a un estudiante no es directivo,
  entonces el sistema deberá rechazar el cambio.
- **MAT-R04.9** — El sistema deberá aplicar al cambio de grado las reglas de
  edad de MAT-R03.

### MAT-R05 — Retiro, reincorporación y traslado

**Historia:** Como gestor, quiero que retirar o trasladar a un estudiante
termine su inscripción para que no siga contando en su sección.

**Criterios de aceptación (EARS):**

- **MAT-R05.1** — Cuando un estudiante con una inscripción vigente es
  retirado (EST-R06.1), el sistema deberá terminar su inscripción en la
  fecha del retiro, con el retiro como motivo.
- **MAT-R05.2** — Cuando un traslado se aprueba (EST-R07), el sistema deberá
  terminar la inscripción vigente del estudiante en el plantel origen en la
  fecha del traslado.
- **MAT-R05.3** — Cuando un estudiante es reincorporado (EST-R06.4), el
  sistema deberá dejarlo sin inscripción vigente hasta que un gestor lo
  inscriba.
- **MAT-R05.4** — Cuando un estudiante llega por traslado al plantel
  destino, el sistema deberá dejarlo sin inscripción hasta que un gestor del
  destino lo inscriba.
- **MAT-R05.5** — Si el retiro o el traslado no pueden terminar la
  inscripción, entonces el sistema deberá deshacer también el retiro o el
  traslado.

### MAT-R06 — Resultado de prosecución

**Historia:** Como gestor de inscripciones, quiero registrar el resultado de
cada estudiante al terminar el año para inscribirlo en el grado que le
corresponde.

**Criterios de aceptación (EARS):**

- **MAT-R06.1** — Mientras el año escolar está activo y su tercer lapso
  está cerrado, el sistema deberá permitir registrar el resultado de
  prosecución de cada estudiante con inscripción vigente.
- **MAT-R06.2** — Si el tercer lapso del año escolar no está cerrado,
  entonces el sistema deberá rechazar el resultado.
- **MAT-R06.3** — Si el grado es de Educación Inicial (Maternal o
  Preescolar) y el resultado no es «promovido», entonces el sistema deberá
  rechazarlo.
- **MAT-R06.4** — Si el grado es de Primaria y el resultado es «promovido
  con materia pendiente», entonces el sistema deberá rechazarlo.
- **MAT-R06.5** — Cuando el resultado de un estudiante de Media General es
  «promovido con materia pendiente», el sistema deberá exigir entre 1 y 2
  materias del pensum de su grado y registrarlas con el resultado.
- **MAT-R06.6** — Si se indican 3 o más materias pendientes, entonces el
  sistema deberá rechazar el resultado e indicar que con 3 o más materias
  reprobadas el estudiante repite.
- **MAT-R06.7** — Si alguna materia pendiente no está en el pensum del grado
  del estudiante, entonces el sistema deberá rechazar el resultado.
- **MAT-R06.8** — Cuando un gestor corrige el resultado de un estudiante
  antes de inscribirlo en el año siguiente, el sistema deberá sustituirlo y
  dejar el anterior en la bitácora.
- **MAT-R06.9** — Si el estudiante ya está inscrito en el año siguiente según
  ese resultado, entonces el sistema deberá rechazar la corrección del
  resultado.
- **MAT-R06.10** — El sistema deberá mostrar, por sección, qué estudiantes
  tienen resultado y cuáles no.

### MAT-R07 — Inscripción en lote en el año siguiente

**Historia:** Como gestor de inscripciones, quiero inscribir de una vez a los
estudiantes del año que termina en el año siguiente para no hacerlo uno a
uno.

**Criterios de aceptación (EARS):**

- **MAT-R07.1** — Cuando un gestor pide la inscripción en lote desde un año
  escolar hacia el siguiente, el sistema deberá proponer para cada
  estudiante con resultado el grado que le corresponde: el siguiente si fue
  promovido (con o sin materia pendiente) y el mismo si repite o perdió por
  inasistencia.
- **MAT-R07.2** — El sistema deberá dejar fuera de la propuesta a los
  estudiantes sin resultado, retirados o ya inscritos en el año siguiente,
  e indicar cuántos y por qué.
- **MAT-R07.3** — Si el plantel no ofrece el grado que le corresponde a un
  estudiante (p. ej. promovido del último grado que ofrece), entonces el
  sistema deberá dejarlo fuera de la propuesta e indicarlo.
- **MAT-R07.4** — Cuando el gestor confirma la propuesta, eligiendo la
  sección de cada estudiante entre las de su grado en el año siguiente, el
  sistema deberá inscribir a todos o a ninguno.
- **MAT-R07.5** — El sistema deberá aplicar a cada inscripción en lote las
  reglas de cupo de MAT-R02, con un motivo por sección cuando se supere.
- **MAT-R07.6** — Si el año siguiente está cerrado o no existe, entonces el
  sistema deberá rechazar la inscripción en lote.
- **MAT-R07.7** — Si la edad de un estudiante de la propuesta queda fuera
  del rango permitido de su grado, entonces el sistema deberá dejarlo fuera
  de la propuesta e indicarlo, para inscribirlo uno a uno con la excepción
  de MAT-R03.

### MAT-R08 — Qué estudiantes ve cada docente y coordinador

**Historia:** Como docente o coordinador, quiero ver a los estudiantes de mis
secciones o grados, y solo a ellos, para trabajar con mis grupos sin
exponer datos de los demás.

**Criterios de aceptación (EARS):**

- **MAT-R08.1** — Mientras una persona no tenga `gestionar-estudiantes` ni
  `gestionar-inscripciones`, el sistema deberá mostrarle solo los
  estudiantes con inscripción vigente, en el año escolar activo, en las
  secciones donde dicta alguna materia o es docente guía, o en los grados
  que coordina.
- **MAT-R08.2** — Si una persona así abre la ficha de un estudiante que no
  está en sus secciones ni en sus grados, entonces el sistema deberá
  responder como si no existiera.
- **MAT-R08.3** — Cuando una persona deja de dictar en una sección o de
  coordinar un grado, el sistema deberá dejar de mostrarle esos estudiantes.
- **MAT-R08.4** — El sistema deberá mostrar a quien tenga
  `gestionar-estudiantes` o `gestionar-inscripciones` a todos los
  estudiantes del plantel, como hasta ahora (EST-R05).

### MAT-R09 — Consulta

**Historia:** Como gestor o directivo, quiero ver quién está inscrito en cada
sección para organizar el año.

**Criterios de aceptación (EARS):**

- **MAT-R09.1** — El sistema deberá mostrar a quien tenga `ver-estudiantes`
  la lista de estudiantes con inscripción vigente de cada sección que
  pueda ver (MAT-R08), con su identificación y si hay sobrecupo.
- **MAT-R09.2** — El sistema deberá mostrar los estudiantes activos sin
  inscripción vigente en el año escolar activo.
- **MAT-R09.3** — El sistema deberá permitir a otras funcionalidades
  consultar los estudiantes con inscripción vigente de una sección
  (SPEC-011, SPEC-012) y el número de inscritos por sección (SPEC-008,
  SPEC-013).

### MAT-R10 — Integridad con la estructura académica

**Historia:** Como gestor, quiero que la estructura del año no pueda
contradecir las inscripciones para no dejar estudiantes en secciones
inexistentes.

**Criterios de aceptación (EARS):**

- **MAT-R10.1** — Si se intenta quitar una sección con inscripciones (de
  cualquier estado) en su año escolar, entonces el sistema deberá rechazarlo
  (EAC-R07.3).
- **MAT-R10.2** — Si se intenta bajar el cupo de una sección por debajo de
  sus inscripciones vigentes, entonces el sistema deberá rechazarlo
  (EAC-R02.6).

### MAT-R11 — Permisos

- **MAT-R11.1** — El sistema deberá permitir inscribir, cambiar de sección,
  registrar resultados e inscribir en lote solo a quien tenga
  `gestionar-inscripciones`.
- **MAT-R11.2** — El sistema deberá incluir `gestionar-inscripciones` en los
  roles Administrador y Director, también en los planteles existentes.
- **MAT-R11.3** — El sistema deberá permitir al Administrador conceder y
  retirar `gestionar-inscripciones` a otros miembros del plantel, persona
  por persona, como `editar-notas-lapso-cerrado` (CAL-R05.4). *Cambiado en
  la implementación (P-04, 2026-10-28): no existe una gestión de permisos
  por rol.*
- **MAT-R11.4** — Si alguien sin el permiso intenta una de esas acciones,
  entonces el sistema deberá rechazarla sin cambiar nada.
- **MAT-R11.5** — El sistema deberá incluir `aprobar-excepciones-inscripcion`
  en los roles Administrador y Director, también en los planteles
  existentes. Lo exigen MAT-R03.4, MAT-R04.7 y MAT-R14.3.

### MAT-R12 — Bitácora

- **MAT-R12.1** — El sistema deberá registrar en la bitácora del plantel:
  - cada inscripción, con el motivo del sobrecupo y la excepción de edad
    con su justificación y quién la aprobó, si los hubo;
  - cada cambio de sección o de grado, con su fecha, motivo y, en el de
    grado, quién lo aprobó;
  - cada anulación, con su motivo;
  - el fin de una inscripción por retiro o traslado;
  - cada resultado de prosecución y sus correcciones, con las materias
    pendientes;
  - cada inscripción en lote, con el número de estudiantes y las secciones.
- **MAT-R12.2** — El sistema no deberá registrar en la bitácora datos de
  salud del estudiante.

### MAT-R13 — Aislamiento por plantel

- **MAT-R13.1** — El sistema deberá mostrar y modificar solo las
  inscripciones y resultados del plantel abierto.
- **MAT-R13.2** — El sistema deberá tomar el plantel únicamente del
  subdominio (S-02).
- **MAT-R13.3** — Si se intenta inscribir a un estudiante o usar una sección
  de otro plantel, entonces el sistema deberá responder como si no
  existieran.

### MAT-R14 — Anulación de una inscripción hecha por error

**Historia:** Como gestor de inscripciones, quiero anular una inscripción
hecha por error sin borrarla para corregirla dejando constancia.

**Criterios de aceptación (EARS):**

- **MAT-R14.1** — Cuando un gestor anula una inscripción con un motivo
  (error de digitación, doble registro u otro con observación), el sistema
  deberá marcarla como anulada, sin borrarla.
- **MAT-R14.2** — El sistema no deberá contar una inscripción anulada en el
  cupo, en los inscritos ni en lo que ven los docentes, y deberá permitir
  inscribir de nuevo al estudiante.
- **MAT-R14.3** — Si han pasado más de 48 horas desde que se registró la
  inscripción, o ya tiene notas (SPEC-011), y quien la anula no es
  directivo, entonces el sistema deberá rechazar la anulación.
- **MAT-R14.4** — Si falta el motivo, entonces el sistema deberá rechazar la
  anulación.
- **MAT-R14.5** — Si la inscripción ya tiene un resultado de prosecución,
  entonces el sistema deberá rechazar la anulación.
- **MAT-R14.6** — El sistema deberá mostrar la inscripción anulada en el
  historial del estudiante, con su motivo, quién la anuló y cuándo.

### MAT-R15 — Egreso y pendiente de egresar

**Historia:** Como gestor de inscripciones, quiero que quien termina el
último año egrese, y que quien tiene materias pendientes no egrese, para
reflejar su situación real.

**Criterios de aceptación (EARS):**

- **MAT-R15.1** — Cuando un estudiante del último año de Media General es
  promovido sin materias pendientes, el sistema deberá mostrarlo como
  egresado de ese año escolar.
- **MAT-R15.2** — Cuando un estudiante de 5.º año es promovido con materia
  pendiente, el sistema deberá mostrarlo como pendiente de egresar, con sus
  materias pendientes.
- **MAT-R15.3** — El sistema deberá dejar fuera de la inscripción en lote a
  los egresados y a los pendientes de egresar, e indicarlo.
- **MAT-R15.4** — Si se intenta inscribir en un grado a un estudiante
  pendiente de egresar, entonces el sistema deberá rechazarlo e indicar que
  solo cursa sus materias pendientes (F2-02).

### MAT-R16 — Antes del cierre del año

**Historia:** Como gestor del calendario, quiero ver antes de cerrar el año
qué estudiantes no tienen resultado para resolverlos y no cerrar con datos
incompletos.

**Criterios de aceptación (EARS):**

- **MAT-R16.1** — Cuando un gestor va a cerrar el año escolar, el sistema
  deberá mostrar la lista de estudiantes con inscripción vigente sin
  resultado de prosecución, por sección.
- **MAT-R16.2** — Si quedan estudiantes con inscripción vigente sin
  resultado, entonces el sistema deberá rechazar el cierre del año
  (CAL-R02.4) e indicar cuántos.
- **MAT-R16.3** — Cuando un gestor registra «perdió por inasistencia» para un
  estudiante que no concluyó el año, el sistema deberá guardarlo como su
  resultado.
- **MAT-R16.4** — Cuando un estudiante que abandonó es retirado (EST-R06.1)
  antes del cierre, el sistema deberá terminar su inscripción (MAT-R05.1) y
  dejar de exigirle resultado.

## Requisitos no funcionales

- **MAT-NF01** (seguridad) — Toda modificación exige
  `gestionar-inscripciones` en la ruta y en el caso de uso (S-04).
- **MAT-NF02** (seguridad) — Las inscripciones y los resultados viven en
  tablas con RLS por plantel (S-01); `TenantTableTest` y la suite `rls` lo
  comprueban.
- **MAT-NF03** (consistencia) — El cupo sin motivo nunca se supera, tampoco
  con inscripciones simultáneas (MAT-R02.4), y nunca hay dos inscripciones
  vigentes del mismo estudiante en el mismo año.
- **MAT-NF04** (rendimiento) — La inscripción en lote de 600 estudiantes
  termina en menos de 10 s.
- **MAT-NF05** (rendimiento) — La consulta «¿puede esta persona ver a este
  estudiante?» (MAT-R08) responde en menos de 10 ms (p95), y la lista de
  estudiantes de un docente con 10 secciones carga en menos de 300 ms
  (p95).
- **MAT-NF06** (usabilidad) — Las pantallas funcionan en 360 px de ancho
  (F1-11), usan la terminología «plantel» (IDG-NF08) y pasan axe sin
  infracciones graves.

## Cambios en otras specs

- **SPEC-002 (IAM-R11.1):** `gestionar-inscripciones` para Administrador y
  Director.
- **SPEC-007:** la prosecución al cerrar el año la hace esta spec
  (MAT-R06, MAT-R07). CAL-R02.4 gana una condición: el año no se cierra con
  estudiantes con inscripción vigente sin resultado (MAT-R16.2).
- **SPEC-008:** EAC-R02.6 y EAC-R07.3 se activan con MAT-R10. La consulta
  de inscritos por sección (MAT-R09.3) alimenta EAC-R02.5.
- **SPEC-009:** EST-R05.1 y EST-R05.2 se limitan por MAT-R08 para Docente y
  Coordinador. El retiro y el traslado terminan la inscripción (MAT-R05).
- **SPEC-011:** al cambiar de grado (MAT-R04.7), congela las notas del grado
  anterior y habilita las del nuevo; las notas cargadas impiden que el
  gestor anule la inscripción (MAT-R14.3). F2-02 inscribe al pendiente de
  egresar en sus materias pendientes.
- **SPEC-012:** retiene el título de quien está pendiente de egresar y no
  emite constancias de inscripciones anuladas.
- **SPEC-013:** el bloque «inscritos por sección» del inicio usa MAT-R09.3.

## Fuera de alcance y preguntas abiertas

Resueltas el 2026-10-27 (ver Decisiones): edad por grado y fecha de corte,
5.º año con materia pendiente, cierre del año sin resultados, anulación por
error y cambio de grado durante el año.

- [ ] **Q6 — Edades de Educación Inicial.** No hay edades para Maternal ni
  Preescolar: hasta definirlas, esos grados no comprueban la edad
  (MAT-R03.7). No bloquea la aprobación.
- [ ] **Q7 — «Perdió por inasistencia» en Educación Inicial.** En Inicial
  solo cabe «promovido» (MAT-R06.3). Hasta decidirlo, a quien abandona en
  Inicial se le registra el retiro. No bloquea la aprobación.

**Fuera de alcance:** ver «Excluye» en el alcance.

## Matriz de trazabilidad

| Criterio | Test(s) |
|----------|---------|
| MAT-R01.1 | `tests/Unit/Enrollment/Domain/EnrollmentTest.php` › «enrolls a student in a section of a school year, from a date»; `tests/Unit/Enrollment/Application/EnrollStudentTest.php` › «enrolls a student in a section of the year, under the calendar lock, recorded inside the transaction»; `tests/Feature/Enrollment/EnrollmentHttpTest.php` › «enrolls a student, and says when the age is not the regular one» |
| MAT-R01.2 | `tests/Unit/Enrollment/Application/EnrollStudentTest.php` › «refuses a second current enrollment of the student in the year, saying where it is»; `tests/Feature/Enrollment/Infrastructure/EnrollmentSchemaTest.php` › «keeps one current enrollment per student and year»; `tests/Feature/Enrollment/Infrastructure/EloquentEnrollmentRepositoryTest.php` › «turns a second current enrollment in the year into a conflict» |
| MAT-R01.3 | `tests/Unit/Enrollment/Application/EnrollStudentTest.php` › «refuses a withdrawn student, a closed year, a section of another year and a date after today»; `tests/Feature/Enrollment/EnrollmentHttpTest.php` › «says what each rule asks for» |
| MAT-R01.4 | `tests/Unit/Enrollment/Application/EnrollStudentTest.php` › «refuses a withdrawn student, a closed year, a section of another year and a date after today» |
| MAT-R01.5 | `tests/Unit/Enrollment/Application/EnrollStudentTest.php` › «refuses a withdrawn student, a closed year, a section of another year and a date after today» |
| MAT-R01.6 | `tests/Unit/Enrollment/Domain/EnrollmentTest.php` › «is not enrolled from a date after today»; `tests/Unit/Enrollment/Application/EnrollStudentTest.php` › «refuses a withdrawn student, a closed year, a section of another year and a date after today» |
| MAT-R02.1 | `tests/Unit/Enrollment/Domain/OccupancyTest.php` › «over the capacity asks for a reason, and keeps it»; `tests/Unit/Enrollment/Application/EnrollStudentTest.php` › «over the capacity, enrolls only with a reason, and keeps it»; `tests/Feature/Enrollment/EnrollmentHttpTest.php` › «says what each rule asks for» |
| MAT-R02.2 | `tests/Unit/Enrollment/Domain/OccupancyTest.php` › «over the capacity asks for a reason, and keeps it»; `tests/Unit/Enrollment/Application/EnrollStudentTest.php` › «over the capacity, enrolls only with a reason, and keeps it» |
| MAT-R02.3 | `tests/Unit/Enrollment/Domain/OccupancyTest.php` › «knows whether the section is over its capacity»; `tests/Unit/Enrollment/Application/RosterQueriesTest.php` › «shows each section of the year with its capacity, its current enrollments and whether it is over capacity»; `tests/Feature/Enrollment/EnrollmentHttpTest.php` › «shows the sections of the active year with their figures, and the students left to enroll» |
| MAT-R02.4 | `tests/Isolation/EnrollmentConcurrencyTest.php` › «while one enrollment holds the last place, the other waits; then it finds the section full» |
| MAT-R03.1 | `tests/Unit/Enrollment/Domain/AgeCheckTest.php` › «takes the age on December 31 of the year the school year starts»; `tests/Unit/Enrollment/Domain/AgeCheckTest.php` › «counts a birthday on February 29» |
| MAT-R03.2 | `tests/Unit/Enrollment/Domain/AgeCheckTest.php` › «is regular at the regular age, a warning inside the range, and out of range beyond it»; `tests/Unit/Enrollment/Application/EnrollStudentTest.php` › «warns when the age is not the regular one of the grade, inside the range»; `tests/Feature/Enrollment/EnrollmentHttpTest.php` › «enrolls a student, and says when the age is not the regular one» |
| MAT-R03.3 | `tests/Unit/Enrollment/Domain/AgeCheckTest.php` › «admits inside the range with no exception, and out of it only with one»; `tests/Unit/Enrollment/Application/EnrollStudentTest.php` › «out of the age range, enrolls only with the exception of a directive, who is the person signed in» |
| MAT-R03.4 | `tests/Unit/Enrollment/Application/EnrollStudentTest.php` › «out of the age range, enrolls only with the exception of a directive, who is the person signed in»; `tests/Feature/Enrollment/Infrastructure/EloquentEnrollmentRepositoryTest.php` › «saves an enrollment and reads it back whole» |
| MAT-R03.5 | `tests/Unit/Enrollment/Domain/AgeCheckTest.php` › «takes an exception only from a directive, with a justification»; `tests/Unit/Enrollment/Application/EnrollStudentTest.php` › «out of the age range, enrolls only with the exception of a directive, who is the person signed in» |
| MAT-R03.6 | `tests/Unit/Enrollment/Infrastructure/ConfigAgeRulesTest.php` › «reads the ages of each grade and the cutoff»; `tests/Unit/Enrollment/Infrastructure/ConfigAgeRulesTest.php` › «refuses a wrong cutoff, an unknown grade or ages out of order»; `tests/Unit/Enrollment/Infrastructure/ConfigAgeRulesTest.php` › «the configuration of the platform has the ages decided for Primaria and Media General» |
| MAT-R03.7 | `tests/Unit/Enrollment/Domain/AgeCheckTest.php` › «does not check a grade with no ages»; `tests/Unit/Enrollment/Infrastructure/ConfigAgeRulesTest.php` › «reads the ages of each grade and the cutoff» |
| MAT-R04.1 | `tests/Unit/Enrollment/Domain/EnrollmentTest.php` › «a change of section ends this enrollment and opens the next one on the same date»; `tests/Unit/Enrollment/Application/ChangePlacementTest.php` › «moves a student to another section of its grade, ending the enrollment and opening the next one on that date»; `tests/Feature/Enrollment/EnrollmentHttpTest.php` › «moves a student to another section, or by a directive to another grade, and annuls an enrollment» |
| MAT-R04.2 | `tests/Unit/Enrollment/Domain/PeriodTest.php` › «does not end before it starts»; `tests/Unit/Enrollment/Domain/EnrollmentTest.php` › «refuses a change without a reason, out of dates or to the same section»; `tests/Unit/Enrollment/Application/ChangePlacementTest.php` › «refuses a change without a reason, out of its dates, to another year or in a closed year» |
| MAT-R04.3 | `tests/Unit/Enrollment/Application/ChangePlacementTest.php` › «refuses a change without a reason, out of its dates, to another year or in a closed year» |
| MAT-R04.4 | `tests/Unit/Enrollment/Application/ChangePlacementTest.php` › «applies the capacity to the section it moves to» |
| MAT-R04.5 | `tests/Unit/Enrollment/Application/ChangePlacementTest.php` › «refuses a change without a reason, out of its dates, to another year or in a closed year» |
| MAT-R04.6 | `tests/Unit/Enrollment/Application/RosterQueriesTest.php` › «shows the history of a student: where, from, until and why it ended, annulments included»; `tests/Feature/Enrollment/StudentHistoryTest.php` › «shows where the student was enrolled, from, until and why each enrollment ended» |
| MAT-R04.7 | `tests/Unit/Enrollment/Domain/EnrollmentTest.php` › «a change of grade needs a directive, and ends as a change of grade»; `tests/Unit/Enrollment/Application/ChangePlacementTest.php` › «a directive changes the grade, recorded with who approved it» |
| MAT-R04.8 | `tests/Unit/Enrollment/Application/ChangePlacementTest.php` › «only a directive changes the grade»; `tests/Feature/Enrollment/EnrollmentPermissionsTest.php` › «a member granted gestionar-inscripciones enrolls, but a change of grade stays a directive's» |
| MAT-R04.9 | `tests/Unit/Enrollment/Application/ChangePlacementTest.php` › «a change of grade goes to another grade, and checks the age there» |
| MAT-R05.1 | `tests/Unit/Students/Application/WithdrawalHookTest.php` › «a withdrawal tells the hooks its date, inside the transaction»; `tests/Unit/Enrollment/Application/EnrollmentHooksTest.php` › «a withdrawal ends the current enrollments on its date; one of a year not started yet, on its start»; `tests/Feature/Enrollment/Infrastructure/EnrollmentHooksIntegrationTest.php` › «withdrawing a student ends its enrollment on that date, recorded» |
| MAT-R05.2 | `tests/Unit/Students/Application/WithdrawalHookTest.php` › «a transfer tells the hooks as a transfer, inside the transaction»; `tests/Unit/Enrollment/Application/EnrollmentHooksTest.php` › «a transfer ends them as a transfer» |
| MAT-R05.3 | `tests/Unit/Enrollment/Application/EnrollmentHooksTest.php` › «a reinstated student, or one that arrived by transfer, has no current enrollment until it is enrolled» |
| MAT-R05.4 | `tests/Unit/Enrollment/Application/EnrollmentHooksTest.php` › «a reinstated student, or one that arrived by transfer, has no current enrollment until it is enrolled» |
| MAT-R05.5 | `tests/Unit/Students/Application/WithdrawalHookTest.php` › «if a hook fails, the withdrawal fails with it»; `tests/Feature/Enrollment/Infrastructure/EnrollmentHooksIntegrationTest.php` › «if the enrollment can not end, the withdrawal does not happen either» |
| MAT-R06.1 | |
| MAT-R06.2 | |
| MAT-R06.3 | |
| MAT-R06.4 | |
| MAT-R06.5 | |
| MAT-R06.6 | |
| MAT-R06.7 | |
| MAT-R06.8 | |
| MAT-R06.9 | |
| MAT-R06.10 | |
| MAT-R07.1 | |
| MAT-R07.2 | |
| MAT-R07.3 | |
| MAT-R07.4 | |
| MAT-R07.5 | |
| MAT-R07.6 | |
| MAT-R07.7 | |
| MAT-R08.1 | |
| MAT-R08.2 | |
| MAT-R08.3 | |
| MAT-R08.4 | |
| MAT-R09.1 | `tests/Unit/Enrollment/Application/RosterQueriesTest.php` › «lists the students of a section by name, with their identification and over capacity reason»; `tests/Feature/Enrollment/EnrollmentHttpTest.php` › «lists the students of a section» |
| MAT-R09.2 | `tests/Unit/Enrollment/Application/RosterQueriesTest.php` › «lists the active students without a current enrollment in the year»; `tests/Feature/Enrollment/EnrollmentHttpTest.php` › «shows the sections of the active year with their figures, and the students left to enroll» |
| MAT-R09.3 | `tests/Unit/Enrollment/Application/RosterQueriesTest.php` › «gives other modules the current enrollments per section» |
| MAT-R10.1 | `tests/Unit/AcademicStructure/Domain/SchoolYearStructureTest.php` › «never removes a section with enrollments, in any state of the year (SPEC-010 MAT-R10.1)»; `tests/Unit/AcademicStructure/Application/SectionsTest.php` › «does not remove a section with enrollments, even if none is current any more (SPEC-010 MAT-R10.1)»; `tests/Unit/Enrollment/Application/EnrollmentHooksTest.php` › «tells the structure the current enrollments of a section, and whether it ever had any»; `tests/Feature/Enrollment/Infrastructure/EnrollmentHooksIntegrationTest.php` › «a section with enrollments is not removed, not even once they ended» |
| MAT-R10.2 | `tests/Unit/Enrollment/Application/EnrollmentHooksTest.php` › «tells the structure the current enrollments of a section, and whether it ever had any»; `tests/Feature/Enrollment/Infrastructure/EnrollmentHooksIntegrationTest.php` › «the capacity of a section does not drop below its current enrollments» |
| MAT-R11.1 | `tests/Unit/Enrollment/Application/EnrollmentAccessTest.php` › «lets manage, approve exceptions and view only with each permission»; `tests/Feature/Enrollment/EnrollmentPermissionsTest.php` › «a teacher sees the enrollments but does not enroll, move nor annul» |
| MAT-R11.2 | `tests/Feature/Enrollment/EnrollmentPermissionsMigrationTest.php` › «existing institutions get gestionar-inscripciones and aprobar-excepciones-inscripcion for Administrador and Director only»; `tests/Feature/Enrollment/EnrollmentPermissionsMigrationTest.php` › «new institutions get them too» |
| MAT-R11.3 | `tests/Feature/IdentityAccess/EnrollmentManagementGrantTest.php` › «an administrador grants and withdraws it to a member, recorded»; `tests/Feature/IdentityAccess/EnrollmentManagementGrantTest.php` › «only an administrador grants it»; `tests/Feature/IdentityAccess/EnrollmentManagementGrantTest.php` › «it only applies in the institution that granted it»; `tests/Feature/Enrollment/EnrollmentPermissionsTest.php` › «a member granted gestionar-inscripciones enrolls, but a change of grade stays a directive's» |
| MAT-R11.4 | `tests/Unit/Enrollment/Application/EnrollStudentTest.php` › «refuses whoever may not manage enrollments»; `tests/Feature/Enrollment/EnrollmentPermissionsTest.php` › «a teacher sees the enrollments but does not enroll, move nor annul» |
| MAT-R11.5 | `tests/Feature/Enrollment/EnrollmentPermissionsMigrationTest.php` › «existing institutions get gestionar-inscripciones and aprobar-excepciones-inscripcion for Administrador and Director only»; `tests/Feature/Enrollment/EnrollmentPermissionsTest.php` › «a director enrolls and approves the exceptions» |
| MAT-R12.1 | `tests/Unit/Enrollment/Application/EnrollmentAuditTest.php` › «records an enrollment with its over capacity reason and its age exception»; `tests/Unit/Enrollment/Application/EnrollmentAuditTest.php` › «records a change of section or grade with its date, reason and who approved it»; `tests/Unit/Enrollment/Application/EnrollmentAuditTest.php` › «records the end of an enrollment by a withdrawal or a transfer, and an annulment with its reason» |
| MAT-R12.2 | `tests/Unit/Enrollment/Application/EnrollmentAuditTest.php` › «never records health data» |
| MAT-R13.1 | `tests/Feature/Enrollment/EnrollmentIsolationTest.php` › «another institution sees none of its enrollments» |
| MAT-R13.2 | `tests/Feature/Enrollment/EnrollmentIsolationTest.php` › «another institution sees none of its enrollments»; `tests/Feature/TenantTableTest.php` |
| MAT-R13.3 | `tests/Feature/Enrollment/EnrollmentIsolationTest.php` › «another institution can not enroll its student nor use its section»; `tests/Feature/Enrollment/EnrollmentIsolationTest.php` › «an enrollment of another institution is not found» |
| MAT-R14.1 | `tests/Unit/Enrollment/Domain/EnrollmentTest.php` › «is annulled with a reason, kept and ended on its start»; `tests/Unit/Enrollment/Application/AnnulEnrollmentTest.php` › «a manager annuls within 48 hours: kept, ended and free to enroll again, recorded» |
| MAT-R14.2 | `tests/Unit/Enrollment/Application/AnnulEnrollmentTest.php` › «a manager annuls within 48 hours: kept, ended and free to enroll again, recorded»; `tests/Unit/Enrollment/Application/RosterQueriesTest.php` › «lists the active students without a current enrollment in the year» |
| MAT-R14.3 | `tests/Unit/Enrollment/Domain/EnrollmentTest.php` › «within 48 hours and without grades anyone who manages annuls; after that, or with grades, only a directive»; `tests/Unit/Enrollment/Application/AnnulEnrollmentTest.php` › «after 48 hours, only a directive annuls»; `tests/Unit/Enrollment/Application/AnnulEnrollmentTest.php` › «with grades, only a directive annuls» |
| MAT-R14.4 | `tests/Unit/Enrollment/Domain/EnrollmentTest.php` › «another reason to annul needs a note»; `tests/Unit/Enrollment/Application/AnnulEnrollmentTest.php` › «needs a reason, and a note for another one» |
| MAT-R14.5 | |
| MAT-R14.6 | `tests/Unit/Enrollment/Application/RosterQueriesTest.php` › «shows the history of a student: where, from, until and why it ended, annulments included»; `tests/Feature/Enrollment/StudentHistoryTest.php` › «shows where the student was enrolled, from, until and why each enrollment ended» |
| MAT-R15.1 | |
| MAT-R15.2 | |
| MAT-R15.3 | |
| MAT-R15.4 | |
| MAT-R16.1 | |
| MAT-R16.2 | |
| MAT-R16.3 | |
| MAT-R16.4 | |
| MAT-NF01 | `tests/Feature/Enrollment/EnrollmentPermissionsTest.php` › «a teacher sees the enrollments but does not enroll, move nor annul»; `tests/Feature/Enrollment/EnrollmentPermissionsTest.php` › «whoever does not see students does not see the enrollments» |
| MAT-NF02 | `tests/Feature/Enrollment/EnrollmentIsolationTest.php` › «another institution sees none of its enrollments»; `tests/Feature/TenantTableTest.php`; `tests/Architecture/LayersTest.php` › «the modules enrollments build on never depend on them» |
| MAT-NF03 | `tests/Feature/Enrollment/Infrastructure/EnrollmentSchemaTest.php` › «keeps one current enrollment per student and year»; `tests/Isolation/EnrollmentConcurrencyTest.php` › «while one enrollment holds the last place, the other waits; then it finds the section full» |
| MAT-NF04 | |
| MAT-NF05 | |
| MAT-NF06 | |
