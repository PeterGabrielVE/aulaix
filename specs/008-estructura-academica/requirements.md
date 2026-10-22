---
spec: 008-estructura-academica
prefijo: EAC
estado: implementada
fecha: 2026-10-22
aprobada: 2026-10-22
backlog: F1-16 – F1-19
módulo: AcademicStructure
depende-de: 003-catalogos (materias), 004-identidad-global (miembros y roles, IDG-R08.4), 005-auditoria, 006-institucion (niveles del plantel), 007-anio-escolar (años escolares)
la-usan: 009-estudiantes (qué estudiantes ve cada docente, vía 010), 010-inscripcion (secciones y cupos), 011-evaluacion (quién carga notas de qué)
---

# Requisitos — Estructura académica

## Contexto

Antes de inscribir estudiantes (SPEC-010) o cargar notas (SPEC-011), cada
plantel necesita organizar su año escolar:

- qué grados ofrece;
- cuántas secciones abre en cada grado y con qué cupo;
- qué materias ve cada grado (pensum);
- quién dicta cada materia en cada sección, quién guía cada sección y quién
  coordina cada nivel.

Esta spec cubre esa estructura, año escolar por año escolar (SPEC-007).
Deja preparado lo que necesitan otras specs:

- la inscripción (cupos);
- las notas (qué docente puede cargar notas de qué materia y sección);
- el bloqueo de IDG-R08.3: no se puede eliminar la cuenta de quien tiene
  asignaciones en el año activo.

## Alcance

- **Incluye:**
  - los grados oficiales de cada nivel, limitados a los niveles del plantel;
  - las secciones de cada grado en un año escolar, con su letra y su cupo;
  - el pensum de cada grado en un año escolar, con materias del catálogo;
  - copiar secciones y pensum del año anterior;
  - asignar docentes por materia y sección, docente guía por sección y
    coordinadores por nivel;
  - qué se puede cambiar según el estado del año escolar;
  - la consulta de la estructura y «Mis asignaciones» del docente;
  - las consultas públicas para SPEC-010 y SPEC-011;
  - el bloqueo de la eliminación de cuenta (IDG-R08.4);
  - permisos, bitácora y aislamiento por plantel.
- **Excluye:**
  - ubicar estudiantes en secciones y filtrar qué estudiantes ve cada
    docente (SPEC-010, inscripción);
  - notas, planes de evaluación y escalas (SPEC-011);
  - horarios y aulas;
  - crear grados o materias propios del plantel: los grados son los
    oficiales y las materias salen del catálogo (SPEC-003).

## Decisiones de producto incorporadas (2026-10-22)

| Pregunta | Decisión |
|----------|----------|
| Grados | Fijos del MPPE: Inicial (Maternal I, II y III; Preescolar 1.er, 2.º y 3.er nivel), Primaria (1.º a 6.º grado), Media General (1.º a 5.º año). El plantel usa los de sus niveles (SPEC-006). |
| Secciones | Pertenecen a un año escolar; se identifican por una letra (A, B, C…), única por grado y año; tienen cupo. |
| Pensum | Cada plantel elige, para cada grado y año escolar, materias del catálogo de su nivel. Se puede copiar del año anterior. |
| Asignaciones | Docente por materia y sección, docente guía por sección y coordinador por nivel. |
| Quién gestiona | Administrador y Director, con el permiso nuevo `gestionar-estructura`. Coordinador y Docente consultan. |
| Cupo | Se define en la sección; la inscripción (SPEC-010) lo respetará. |
| Qué estudiantes ve el docente | Pasa a SPEC-010: hace falta saber en qué sección está cada estudiante. |
| Cambios según el año | Planificado: todo. Activo: añadir secciones y materias, cambiar cupos y docentes; quitar una sección o materia solo si no tiene estudiantes ni notas. Cerrado: solo lectura. |
| Coordinador | Se asigna a uno o varios niveles, o a grados concretos de un nivel (subcoordinaciones). Un nivel puede tener varios coordinadores. |
| Quién dicta clases o es guía | Un miembro activo con el rol Docente. Un Director o un Coordinador puede dictar clases si además tiene el rol Docente (asignación formal de carga horaria). |
| Docente que sale o es suspendido | Sus asignaciones no se quitan solas: el plantel registra la novedad (SPEC-004) y las reasigna a mano. El sistema avisa de las que quedan con una persona que ya no es miembro activo. |
| Especialistas | En todos los niveles, además del docente guía (docente de aula o integrador), se pueden asignar docentes por materia (Educación Física, Inglés, Música…). |
| Tope de cupo | Maternal 15, Preescolar 25, Primaria 35, Media General 40. Se puede superar indicando el motivo (p. ej. alta demanda autorizada), que queda en la bitácora. |
| Bloqueo de eliminación de cuenta | Si la persona dicta una materia o es docente guía en el año escolar activo de algún plantel. |

## Glosario

| Término | Significado |
|---------|-------------|
| Nivel | Inicial, Primaria o Media General (catálogo de SPEC-003). |
| Grado | Escalón oficial de un nivel: Maternal II, 1.er nivel de Preescolar, 3.er grado, 2.º año… |
| Maternal / Preescolar | Las dos etapas de Inicial: Maternal (0 a 3 años, salas Maternal I, II y III) y Preescolar (3 a 6 años, 1.er, 2.º y 3.er nivel). |
| Tope de cupo | Máximo de estudiantes por sección según el nivel o la etapa. |
| Sección | Grupo de estudiantes de un grado en un año escolar, identificado por una letra («1.er año A»). |
| Cupo | Máximo de estudiantes de una sección. |
| Pensum | Materias que ve un grado en un año escolar. |
| Asignación docente | Docente que dicta una materia en una sección durante un año escolar. |
| Docente guía | Docente responsable de una sección (docente de aula en Inicial y Primaria). |
| Coordinador de nivel | Persona que coordina un nivel del plantel en un año escolar. |
| Gestor | Quien tiene `gestionar-estructura` en el plantel abierto. |

## Requisitos

### EAC-R01 — Grados

**Historia:** Como gestor, quiero trabajar con los grados oficiales de los
niveles que ofrece mi plantel, sin tener que crearlos.

**Criterios de aceptación (EARS):**

- **EAC-R01.1** — El sistema deberá ofrecer los grados oficiales de cada
  nivel: Inicial (Maternal I, II y III; Preescolar 1.er, 2.º y 3.er nivel),
  Primaria (1.º a 6.º grado) y Media General (1.º a 5.º año).
- **EAC-R01.2** — El sistema deberá ofrecer solo los grados de los niveles
  que el plantel indicó (SPEC-006, INS-R03).
- **EAC-R01.3** — Si el plantel aún no indicó sus niveles, entonces el
  sistema deberá pedir que los complete antes de crear secciones.

### EAC-R02 — Secciones

**Historia:** Como gestor, quiero abrir las secciones de cada grado para el
año escolar, con su cupo, para organizar a los estudiantes.

**Criterios de aceptación (EARS):**

- **EAC-R02.1** — Cuando un gestor crea una sección de un grado en un año
  escolar, con una letra y un cupo, el sistema deberá registrarla.
- **EAC-R02.2** — Si esa letra ya existe en ese grado y año escolar,
  entonces el sistema deberá rechazarla.
- **EAC-R02.3** — Si la letra no es una sola letra de la A a la Z, entonces
  el sistema deberá rechazarla.
- **EAC-R02.4** — Si el cupo no es un número entero mayor que cero, entonces
  el sistema deberá rechazarlo.
- **EAC-R02.5** — Cuando un gestor cambia el cupo de una sección, el sistema
  deberá guardarlo.
- **EAC-R02.6** — Donde existan inscripciones (SPEC-010), si el cupo nuevo es
  menor que los estudiantes inscritos en la sección, entonces el sistema
  deberá rechazarlo.
- **EAC-R02.7** — Cuando un gestor quita una sección, el sistema deberá
  quitarla junto con sus asignaciones docentes, con las reglas de EAC-R07.
- **EAC-R02.8** — Si el cupo supera el tope de su nivel o etapa (Maternal
  15, Preescolar 25, Primaria 35, Media General 40) y el gestor no indica un
  motivo, entonces el sistema deberá rechazarlo.
- **EAC-R02.9** — Cuando el gestor supera el tope indicando un motivo, el
  sistema deberá guardar el cupo con su motivo y mostrarlo en la sección.

### EAC-R03 — Pensum

**Historia:** Como gestor, quiero indicar qué materias ve cada grado en el
año escolar, para asignarles docentes y luego evaluarlas.

**Criterios de aceptación (EARS):**

- **EAC-R03.1** — Cuando un gestor elige materias del catálogo para un grado
  en un año escolar, el sistema deberá guardarlas como el pensum de ese
  grado en ese año.
- **EAC-R03.2** — Si una materia no es del nivel del grado, entonces el
  sistema deberá rechazarla.
- **EAC-R03.3** — Si una materia se repite en el pensum de un grado,
  entonces el sistema deberá rechazarla.
- **EAC-R03.4** — Cuando un gestor quita una materia del pensum, el sistema
  deberá quitarla junto con sus asignaciones docentes en las secciones de
  ese grado, con las reglas de EAC-R07.

### EAC-R04 — Copiar del año anterior

**Historia:** Como gestor, quiero partir de la estructura del año anterior
al planificar uno nuevo, para no repetirla a mano.

**Criterios de aceptación (EARS):**

- **EAC-R04.1** — Cuando un gestor pide copiar la estructura de un año
  escolar a otro planificado, el sistema deberá copiar las secciones (letra
  y cupo) y el pensum de cada grado, sin las asignaciones de personas.
- **EAC-R04.2** — Si el año destino ya tiene secciones o pensum, entonces el
  sistema deberá rechazar la copia.
- **EAC-R04.3** — El sistema deberá copiar solo los grados de los niveles
  que el plantel ofrece en ese momento.

### EAC-R05 — Docentes por materia y sección

**Historia:** Como gestor, quiero indicar quién dicta cada materia en cada
sección, para que cada docente sepa sus clases y luego cargue las notas.

**Criterios de aceptación (EARS):**

- **EAC-R05.1** — Cuando un gestor asigna un docente a una materia del
  pensum en una sección, el sistema deberá registrar la asignación.
- **EAC-R05.2** — Si la persona no es un miembro activo del plantel con el
  rol Docente, entonces el sistema deberá rechazar la asignación. (Un
  Director o un Coordinador que también tenga el rol Docente puede ser
  asignado.)
- **EAC-R05.3** — Si la materia no está en el pensum del grado de la
  sección, entonces el sistema deberá rechazar la asignación.
- **EAC-R05.4** — El sistema deberá mantener como máximo un docente por
  materia y sección; asignar otro deberá reemplazar al anterior.
- **EAC-R05.5** — Cuando un gestor quita una asignación, el sistema deberá
  dejar esa materia sin docente en la sección.
- **EAC-R05.6** — Cuando una persona asignada deja de ser miembro activo del
  plantel, el sistema deberá conservar sus asignaciones hasta que un gestor
  las reasigne.
- **EAC-R05.7** — El sistema deberá permitir asignar docentes por materia en
  todos los niveles, además del docente guía (p. ej. especialistas de
  Educación Física o Inglés en Inicial y Primaria).

### EAC-R06 — Docente guía y coordinadores

**Historia:** Como gestor, quiero nombrar al docente guía de cada sección y
a los coordinadores de cada nivel.

**Criterios de aceptación (EARS):**

- **EAC-R06.1** — Cuando un gestor nombra el docente guía de una sección, el
  sistema deberá registrarlo, reemplazando al anterior si lo había.
- **EAC-R06.2** — Si la persona no es un miembro activo del plantel con el
  rol Docente, entonces el sistema deberá rechazarla como docente guía.
- **EAC-R06.3** — Cuando un gestor asigna un coordinador a uno o varios
  niveles del plantel, o a grados concretos de un nivel, en un año escolar,
  el sistema deberá registrarlo.
- **EAC-R06.4** — Si la persona no es un miembro activo del plantel con el
  rol Coordinador, o el nivel no es del plantel, entonces el sistema deberá
  rechazar la asignación.
- **EAC-R06.5** — El sistema deberá permitir quitar el docente guía de una
  sección y un coordinador de un nivel.
- **EAC-R06.6** — El sistema deberá permitir varios coordinadores en un
  mismo nivel del mismo año escolar.

### EAC-R07 — Cambios según el estado del año escolar

**Historia:** Como gestor, quiero poder ajustar la estructura durante el
año sin perder lo que ya depende de ella.

**Criterios de aceptación (EARS):**

- **EAC-R07.1** — Mientras el año escolar está planificado, el sistema
  deberá permitir cualquier cambio de su estructura.
- **EAC-R07.2** — Mientras el año escolar está activo, el sistema deberá
  permitir crear secciones, añadir materias al pensum, cambiar cupos y
  cambiar las asignaciones de personas.
- **EAC-R07.3** — Donde existan inscripciones o notas (SPEC-010, SPEC-011),
  mientras el año escolar está activo, si se intenta quitar una sección con
  estudiantes o una materia con notas, entonces el sistema deberá
  rechazarlo.
- **EAC-R07.4** — Si el año escolar está cerrado, entonces el sistema deberá
  rechazar cualquier cambio de su estructura.

### EAC-R08 — Consulta

**Historia:** Como miembro del plantel, quiero ver cómo está organizado el
año escolar y, como docente, mis clases.

**Criterios de aceptación (EARS):**

- **EAC-R08.1** — El sistema deberá mostrar a quien tenga `ver-estructura`
  la estructura de un año escolar del plantel: grados, secciones con su
  cupo, pensum, docentes por materia, docentes guía y coordinadores.
- **EAC-R08.2** — El sistema deberá mostrar a cada docente sus asignaciones
  del año escolar activo: las materias que dicta, en qué secciones, y las
  secciones de las que es guía.
- **EAC-R08.3** — El sistema deberá permitir a otras funcionalidades
  consultar las secciones de un año escolar con su cupo (SPEC-010).
- **EAC-R08.4** — El sistema deberá permitir a otras funcionalidades
  consultar si una persona dicta una materia en una sección del año activo
  (SPEC-011) y qué secciones tiene a su cargo (SPEC-010).
- **EAC-R08.5** — El sistema deberá mostrar a los gestores las asignaciones
  del año activo cuya persona ya no es miembro activo del plantel, para que
  las reasignen.

### EAC-R09 — Bloqueo de la eliminación de cuenta

- **EAC-R09.1** — Si la persona dicta alguna materia o es docente guía en
  el año escolar activo de algún plantel, entonces el sistema deberá
  impedir la eliminación de su cuenta hasta que la reemplacen (IDG-R08.4).

### EAC-R10 — Permisos

- **EAC-R10.1** — El sistema deberá permitir cambiar la estructura solo a
  quien tenga el permiso `gestionar-estructura`.
- **EAC-R10.2** — El sistema deberá incluir `gestionar-estructura` en los
  roles base Administrador y Director, también en los planteles que ya
  existen.
- **EAC-R10.3** — El sistema deberá incluir `ver-estructura` en los roles
  base Administrador, Director, Coordinador y Docente, también en los
  planteles que ya existen.
- **EAC-R10.4** — Si alguien sin el permiso intenta un cambio o una
  consulta, entonces el sistema deberá responder 403.

### EAC-R11 — Bitácora

- **EAC-R11.1** — El sistema deberá registrar en la bitácora del plantel
  (SPEC-005):
  - la creación y la retirada de secciones y los cambios de cupo;
  - los cambios del pensum;
  - la copia de un año a otro;
  - cada asignación, reemplazo o retirada de docentes, docentes guía y
    coordinadores, con los valores anterior y nuevo.

### EAC-R12 — Aislamiento por plantel

- **EAC-R12.1** — El sistema deberá mostrar y modificar solo la estructura
  del plantel abierto (S-01).
- **EAC-R12.2** — El sistema deberá tomar el plantel únicamente del
  subdominio de la petición, nunca de un dato que envíe el usuario (S-02).
- **EAC-R12.3** — Si se intenta asignar a una persona que no es miembro del
  plantel abierto, entonces el sistema deberá rechazarlo.

## Requisitos no funcionales

- **EAC-NF01** (seguridad) — Toda modificación exige `gestionar-estructura`
  en la ruta y en el caso de uso (S-04).
- **EAC-NF02** (rendimiento) — La consulta «¿esta persona dicta esta materia
  en esta sección?» (EAC-R08.4) responde en menos de 10 ms (p95), porque
  SPEC-011 la usará en cada carga de notas.
- **EAC-NF03** (rendimiento) — La pantalla de estructura de un año con 60
  secciones carga en menos de 300 ms (p95).
- **EAC-NF04** (usabilidad) — Las pantallas funcionan en 360 px de ancho
  (F1-11) y usan la terminología «plantel» (IDG-NF08).

## Cambios en otras specs

- IAM-R11.1 (SPEC-002): `gestionar-estructura` para Administrador y
  Director; `ver-estructura` para Administrador, Director, Coordinador y
  Docente.
- IDG-R08.4 (SPEC-004): lo cumple EAC-R09.1, con el punto de extensión
  `DeletionBlocker`.
- SPEC-009: el filtro de qué estudiantes ve cada docente pasa a SPEC-010.

## Fuera de alcance y preguntas abiertas

Ninguna pregunta abierta: todas están resueltas en [Decisiones de producto
incorporadas](#decisiones-de-producto-incorporadas-2026-10-22).

**Fuera de alcance:**
- inscripción y ubicación de estudiantes en secciones (SPEC-010);
- notas (SPEC-011);
- horarios y aulas;
- auxiliares de Preescolar y otro personal sin carga docente;
- grados o materias propios del plantel.

## Matriz de trazabilidad

| Criterio | Test(s) |
|----------|---------|
| EAC-R01.1 | `tests/Unit/AcademicStructure/Domain/GradeAndSectionTest.php` › «has Maternal I to III, Preescolar 1st to 3rd level, Primaria 1st to 6th grade and Media General 1st to 5th year» |
| EAC-R01.2 | `tests/Unit/AcademicStructure/Domain/SchoolYearStructureTest.php` › «creates sections of the offered grades, one letter each»; `tests/Unit/AcademicStructure/Domain/SchoolYearStructureTest.php` › «refuses grades of levels the institution does not offer, or before it says which»; `tests/Unit/AcademicStructure/Application/SectionsTest.php` › «refuses a grade of a level the institution does not offer, and asks for the levels first» |
| EAC-R01.3 | `tests/Unit/AcademicStructure/Domain/SchoolYearStructureTest.php` › «refuses grades of levels the institution does not offer, or before it says which»; `tests/Unit/AcademicStructure/Application/SectionsTest.php` › «refuses a grade of a level the institution does not offer, and asks for the levels first» |
| EAC-R02.1 | `tests/Unit/AcademicStructure/Domain/SchoolYearStructureTest.php` › «creates sections of the offered grades, one letter each»; `tests/Unit/AcademicStructure/Application/SectionsTest.php` › «creates a section under the calendar lock, recorded inside the transaction»; `tests/Feature/AcademicStructure/StructureManagementTest.php` › «a manager opens sections and sets the curriculum of the planned year» |
| EAC-R02.2 | `tests/Unit/AcademicStructure/Domain/SchoolYearStructureTest.php` › «refuses a letter the grade already has in the year»; `tests/Feature/AcademicStructure/Infrastructure/StructureRepositoryTest.php` › «turns a broken unique rule into a conflict» |
| EAC-R02.3 | `tests/Unit/AcademicStructure/Domain/GradeAndSectionTest.php` › «takes a single letter A to Z as the section» |
| EAC-R02.4 | `tests/Unit/AcademicStructure/Domain/GradeAndSectionTest.php` › «needs a whole capacity above zero» |
| EAC-R02.5 | `tests/Unit/AcademicStructure/Domain/SchoolYearStructureTest.php` › «changes the capacity, never below the enrolled students»; `tests/Unit/AcademicStructure/Application/SectionsTest.php` › «changes the capacity, never below the enrolled students, recorded» |
| EAC-R02.6 | `tests/Unit/AcademicStructure/Domain/SchoolYearStructureTest.php` › «changes the capacity, never below the enrolled students»; `tests/Unit/AcademicStructure/Application/SectionsTest.php` › «changes the capacity, never below the enrolled students, recorded» |
| EAC-R02.7 | `tests/Unit/AcademicStructure/Domain/SchoolYearStructureTest.php` › «removes a section with its teacher assignments»; `tests/Unit/AcademicStructure/Application/SectionsTest.php` › «removes a section, unless it has students in the active year» |
| EAC-R02.8 | `tests/Unit/AcademicStructure/Domain/GradeAndSectionTest.php` › «needs a reason to go over the cap of the stage, and keeps it»; `tests/Unit/AcademicStructure/Domain/GradeAndSectionTest.php` › «caps the capacity by stage»; `tests/Feature/AcademicStructure/StructureManagementTest.php` › «the capacity goes over the cap only with a reason» |
| EAC-R02.9 | `tests/Unit/AcademicStructure/Domain/GradeAndSectionTest.php` › «needs a reason to go over the cap of the stage, and keeps it»; `tests/Feature/AcademicStructure/StructureManagementTest.php` › «the capacity goes over the cap only with a reason» |
| EAC-R03.1 | `tests/Unit/AcademicStructure/Domain/SchoolYearStructureTest.php` › «keeps the curriculum of each grade with subjects of its level, each once»; `tests/Unit/AcademicStructure/Application/CurriculumAndCopyTest.php` › «keeps the curriculum of a grade with subjects of the catalog of its level, recorded»; `tests/Feature/AcademicStructure/StructureManagementTest.php` › «a manager opens sections and sets the curriculum of the planned year» |
| EAC-R03.2 | `tests/Unit/AcademicStructure/Domain/SchoolYearStructureTest.php` › «keeps the curriculum of each grade with subjects of its level, each once»; `tests/Feature/Catalogs/SubjectsTest.php` › «tells the level of each given subject, leaving out the unknown ones»; `tests/Unit/AcademicStructure/Application/CurriculumAndCopyTest.php` › «keeps the curriculum of a grade with subjects of the catalog of its level, recorded» |
| EAC-R03.3 | `tests/Unit/AcademicStructure/Domain/SchoolYearStructureTest.php` › «keeps the curriculum of each grade with subjects of its level, each once»; `tests/Feature/AcademicStructure/Infrastructure/StructureRepositoryTest.php` › «stores the structure of a year and reads it back» |
| EAC-R03.4 | `tests/Unit/AcademicStructure/Domain/SchoolYearStructureTest.php` › «takes a subject out of the curriculum with its teachers in that grade»; `tests/Unit/AcademicStructure/Application/CurriculumAndCopyTest.php` › «does not take out of the active year a subject that has grades» |
| EAC-R04.1 | `tests/Unit/AcademicStructure/Domain/CopyStructureTest.php` › «copies the sections and the curriculum, without the people»; `tests/Unit/AcademicCalendar/Application/SchoolYearLookupTest.php` › «finds the year just before another one»; `tests/Unit/AcademicStructure/Application/CurriculumAndCopyTest.php` › «copies the structure of the year before into a planned year, recorded» |
| EAC-R04.2 | `tests/Unit/AcademicStructure/Domain/CopyStructureTest.php` › «copies only into a planned year with no structure yet» |
| EAC-R04.3 | `tests/Unit/AcademicStructure/Domain/CopyStructureTest.php` › «copies only the grades the institution offers now» |
| EAC-R05.1 | `tests/Unit/AcademicStructure/Domain/AssignmentsTest.php` › «assigns a teacher to a subject of the curriculum in a section, one per subject»; `tests/Unit/AcademicStructure/Application/AssignmentsTest.php` › «assigns and replaces the teacher of a subject, recorded with old and new»; `tests/Feature/AcademicStructure/AssignmentManagementTest.php` › «a manager assigns the teacher of a subject, the guide and a coordinator»; `tests/Feature/AcademicStructure/AssignmentManagementTest.php` › «only a teacher of the institution can teach or guide» |
| EAC-R05.2 | `tests/Feature/AcademicStructure/Infrastructure/StaffDirectoryTest.php` › «tells whether a person is an active member with a role»; `tests/Unit/AcademicStructure/Application/AssignmentsTest.php` › «takes a teacher who is also Director, but not a Director who is not a teacher, nor someone inactive or unknown» |
| EAC-R05.3 | `tests/Unit/AcademicStructure/Domain/AssignmentsTest.php` › «refuses a subject that is not in the curriculum of the section grade» |
| EAC-R05.4 | `tests/Unit/AcademicStructure/Domain/AssignmentsTest.php` › «assigns a teacher to a subject of the curriculum in a section, one per subject»; `tests/Unit/AcademicStructure/Application/AssignmentsTest.php` › «assigns and replaces the teacher of a subject, recorded with old and new» |
| EAC-R05.5 | `tests/Unit/AcademicStructure/Domain/AssignmentsTest.php` › «removes a teacher, leaving the subject without one»; `tests/Unit/AcademicStructure/Application/AssignmentsTest.php` › «removes a teacher» |
| EAC-R05.6 | `tests/Feature/AcademicStructure/Infrastructure/AssignmentQueriesTest.php` › «names the people assigned and flags, without removing, those who are no longer active members» |
| EAC-R05.7 | `tests/Unit/AcademicStructure/Domain/AssignmentsTest.php` › «assigns specialists in Inicial too, besides the guide» |
| EAC-R06.1 | `tests/Unit/AcademicStructure/Domain/AssignmentsTest.php` › «names, replaces and removes the guide of a section»; `tests/Unit/AcademicStructure/Application/AssignmentsTest.php` › «names and removes the guide teacher, only a teacher»; `tests/Feature/AcademicStructure/AssignmentManagementTest.php` › «a manager assigns the teacher of a subject, the guide and a coordinator» |
| EAC-R06.2 | `tests/Feature/AcademicStructure/Infrastructure/StaffDirectoryTest.php` › «tells whether a person is an active member with a role»; `tests/Unit/AcademicStructure/Application/AssignmentsTest.php` › «names and removes the guide teacher, only a teacher» |
| EAC-R06.3 | `tests/Unit/AcademicStructure/Domain/AssignmentsTest.php` › «assigns coordinators to a whole level or to some of its grades, several per level»; `tests/Unit/AcademicStructure/Application/AssignmentsTest.php` › «assigns and removes coordinators, only coordinators»; `tests/Feature/AcademicStructure/AssignmentManagementTest.php` › «who may not manage the structure gets 403 assigning people»; `tests/Feature/AcademicStructure/AssignmentManagementTest.php` › «a manager assigns the teacher of a subject, the guide and a coordinator» |
| EAC-R06.4 | `tests/Unit/AcademicStructure/Domain/AssignmentsTest.php` › «refuses a level the institution does not offer, or grades of another level»; `tests/Unit/AcademicStructure/Application/AssignmentsTest.php` › «assigns and removes coordinators, only coordinators» |
| EAC-R06.5 | `tests/Unit/AcademicStructure/Domain/AssignmentsTest.php` › «names, replaces and removes the guide of a section»; `tests/Unit/AcademicStructure/Application/AssignmentsTest.php` › «names and removes the guide teacher, only a teacher»; `tests/Unit/AcademicStructure/Application/AssignmentsTest.php` › «assigns and removes coordinators, only coordinators» |
| EAC-R06.6 | `tests/Unit/AcademicStructure/Domain/AssignmentsTest.php` › «assigns coordinators to a whole level or to some of its grades, several per level» |
| EAC-R07.1 | `tests/Unit/AcademicStructure/Domain/SchoolYearStructureTest.php` › «allows any change while the year is planned, even of what is in use» |
| EAC-R07.2 | `tests/Unit/AcademicStructure/Domain/SchoolYearStructureTest.php` › «allows creating, adding and changing while the year is active, but not removing what is in use»; `tests/Unit/AcademicStructure/Application/AssignmentsTest.php` › «assigns and replaces the teacher of a subject, recorded with old and new» |
| EAC-R07.3 | `tests/Unit/AcademicStructure/Domain/SchoolYearStructureTest.php` › «allows creating, adding and changing while the year is active, but not removing what is in use»; `tests/Unit/AcademicStructure/Application/SectionsTest.php` › «removes a section, unless it has students in the active year»; `tests/Unit/AcademicStructure/Application/CurriculumAndCopyTest.php` › «does not take out of the active year a subject that has grades» |
| EAC-R07.4 | `tests/Unit/AcademicStructure/Domain/SchoolYearStructureTest.php` › «changes nothing of a closed year»; `tests/Unit/AcademicStructure/Application/SectionsTest.php` › «changes nothing of a closed year, nor of a year it does not have» |
| EAC-R08.1 | `tests/Feature/AcademicStructure/Infrastructure/StructureQueriesTest.php` › «shows the active year by default: its grades, sections in order and curriculum»; `tests/Feature/AcademicStructure/StructureManagementTest.php` › «the screen shows the structure to teachers and coordinators, read only» |
| EAC-R08.2 | `tests/Feature/AcademicStructure/Infrastructure/AssignmentQueriesTest.php` › «lists a teacher assignments of the active year»; `tests/Feature/AcademicStructure/AssignmentManagementTest.php` › «a teacher sees its own classes» |
| EAC-R08.3 | `tests/Feature/AcademicStructure/Infrastructure/StructureQueriesTest.php` › «tells other modules the sections of a year with their capacity» |
| EAC-R08.4 | `tests/Feature/AcademicStructure/Infrastructure/AssignmentQueriesTest.php` › «tells other modules whether a person teaches a subject in a section of the active year, and their sections» |
| EAC-R08.5 | `tests/Feature/AcademicStructure/Infrastructure/AssignmentQueriesTest.php` › «names the people assigned and flags, without removing, those who are no longer active members»; `tests/Feature/AcademicStructure/AssignmentManagementTest.php` › «the screen names the people assigned and warns of those no longer active» |
| EAC-R09.1 | `tests/Feature/AcademicStructure/AccountDeletionBlockTest.php` › «whoever teaches a subject in the active year can not delete the account» |
| EAC-R10.1 | `tests/Unit/AcademicStructure/Application/SectionsTest.php` › «refuses whoever may not manage the structure»; `tests/Feature/AcademicStructure/StructureManagementTest.php` › «a manager opens sections and sets the curriculum of the planned year»; `tests/Feature/AcademicStructure/StructureManagementTest.php` › «who may not manage the structure gets 403 on every change» |
| EAC-R10.2 | `tests/Feature/AcademicStructure/StructurePermissionsTest.php` › «existing institutions get gestionar-estructura for Administrador and Director, and ver-estructura for teachers and coordinators too» |
| EAC-R10.3 | `tests/Feature/AcademicStructure/StructurePermissionsTest.php` › «existing institutions get gestionar-estructura for Administrador and Director, and ver-estructura for teachers and coordinators too» |
| EAC-R10.4 | `tests/Feature/AcademicStructure/StructureManagementTest.php` › «who may not manage the structure gets 403 on every change»; `tests/Feature/AcademicStructure/StructureManagementTest.php` › «a member without ver-estructura does not see the screen»; `tests/Feature/AcademicStructure/AssignmentManagementTest.php` › «who may not manage the structure gets 403 assigning people» |
| EAC-R11.1 | `tests/Unit/AcademicStructure/Application/StructureAuditTest.php` › «records a new section with its grade, letter and capacity»; `tests/Unit/AcademicStructure/Application/SectionsTest.php` › «creates a section under the calendar lock, recorded inside the transaction»; `tests/Unit/AcademicStructure/Application/CurriculumAndCopyTest.php` › «keeps the curriculum of a grade with subjects of the catalog of its level, recorded»; `tests/Unit/AcademicStructure/Application/CurriculumAndCopyTest.php` › «copies the structure of the year before into a planned year, recorded»; `tests/Unit/AcademicStructure/Application/AssignmentsTest.php` › «assigns and replaces the teacher of a subject, recorded with old and new» |
| EAC-R12.1 | `tests/Feature/AcademicStructure/StructureIsolationTest.php` › «an institution only sees its own structure»; `tests/Feature/AcademicStructure/StructureManagementTest.php` › «the structure of another institution does not exist here» |
| EAC-R12.2 | `tests/Feature/AcademicStructure/StructureManagementTest.php` › «the structure of another institution does not exist here» |
| EAC-R12.3 | `tests/Feature/AcademicStructure/Infrastructure/StaffDirectoryTest.php` › «does not take a member of another institution»; `tests/Unit/AcademicStructure/Application/AssignmentsTest.php` › «takes a teacher who is also Director, but not a Director who is not a teacher, nor someone inactive or unknown» |
