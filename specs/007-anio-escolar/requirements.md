---
spec: 007-anio-escolar
prefijo: CAL
estado: implementada
fecha: 2026-10-16
aprobada: 2026-10-19
backlog: F1-13, F1-14, F1-15
módulo: AcademicCalendar
depende-de: 001-tenancy, 004-identidad-global (permisos por membresía), 005-auditoria
la-usan: 008-estructura-academica, 010-inscripcion, 011-evaluacion
---

# Requisitos — Año escolar y lapsos

## Contexto

Todo lo académico de un plantel ocurre dentro de un año escolar dividido en
tres lapsos: la estructura (008), la inscripción (010) y, sobre todo, las
notas (011). El backlog lo pide en tres ítems:

- **F1-13** — «Año escolar y 3 lapsos con fechas»: un solo año activo por
  plantel.
- **F1-14** — «Apertura y cierre de lapsos»: un lapso cerrado bloquea la
  edición de notas, salvo permiso especial.
- **F1-15** — «Pantalla de gestión de año y lapsos»: crear el año, editar
  fechas y cerrar un lapso con confirmación.

Esta spec define el calendario y la regla de bloqueo. La edición de notas
existe con SPEC-011, que aplicará esa regla.

## Alcance

- **Incluye:**
  - el año escolar con sus fechas y su ciclo de vida (planificado, activo,
    cerrado);
  - sus tres lapsos con fechas, apertura y cierre en orden, y reapertura con
    motivo;
  - la regla que dice si un lapso admite cambios de notas, y el permiso
    especial que la exceptúa;
  - la pantalla de gestión;
  - el registro en la bitácora y el aislamiento por plantel.
- **Excluye:**
  - la edición de notas y su bloqueo efectivo (SPEC-011, que consume la
    regla CAL-R05);
  - la prosecución y los cambios de grado al cerrar el año (SPEC-010);
  - las boletas y los documentos de fin de lapso o de año (SPEC-012);
  - días feriados, horarios y calendario de actividades;
  - un número de lapsos distinto de tres (el backlog los fija en tres);
  - los avisos a docentes al cerrar o reabrir un lapso (F2-05).

## Decisiones de producto incorporadas (2026-10-16)

| Pregunta | Decisión |
|----------|----------|
| Ciclo de vida del año | Planificado → activo → cerrado. Se puede preparar el año siguiente mientras el actual está activo. Un año cerrado no se reabre. |
| Quién gestiona el calendario | Administrador y Director, con el permiso nuevo `gestionar-calendario`. |
| Reabrir un lapso | Sí, con motivo y registrado en la bitácora, solo mientras el año no esté cerrado. |
| Permiso especial para notas de lapsos cerrados | `editar-notas-lapso-cerrado`, que ningún rol base trae por defecto: se concede a quien haga falta. |
| Fechas de los lapsos | Dentro del año, en orden y sin solaparse. Puede haber días entre lapsos y no se exige cubrir todo el año. |
| Apertura y cierre | Manual y en orden: al activar el año se abre el lapso 1 y cerrar un lapso abre el siguiente. Las fechas son informativas. |
| Cierre del año | Con los tres lapsos cerrados y confirmación. Solo cambia el estado; el resto lo hacen 010 y 012. |
| Editar fechas | Solo las de lapsos no cerrados, y las del año mientras no esté cerrado. |
| Nombre del año | Se genera de las fechas: año de inicio y año de fin («2026-2027»). |
| Duración máxima del año | 11 meses. |
| Solape entre años | Los años de un plantel no pueden solaparse en fechas. |
| Borrar un año planificado | Se puede, si nunca se activó. |
| Reabrir con el lapso siguiente abierto | Solo se reabre el último lapso cerrado; el abierto vuelve a «sin abrir». Siempre hay un solo lapso abierto. |
| Avisos a docentes | No en esta spec: los dará el módulo de notificaciones (F2-05). |

## Glosario

| Término | Significado |
|---------|-------------|
| Año escolar | Período académico de un plantel (p. ej. septiembre a julio), con fecha de inicio y de fin. |
| Lapso | Cada una de las tres divisiones del año escolar, con sus propias fechas. |
| Año planificado | Año creado y en preparación: todavía no rige. |
| Año activo | El año que rige en el plantel. Hay como máximo uno. |
| Lapso abierto | El lapso en curso del año activo: admite cambios de notas. |
| Gestor del calendario | Miembro con el permiso `gestionar-calendario`. |

## Requisitos

### CAL-R01 — Crear y preparar el año escolar

**Historia:** Como Director, quiero crear el año escolar con sus fechas y
las de sus tres lapsos, para preparar el año antes de que empiece.

**Criterios de aceptación (EARS):**

- **CAL-R01.1** — Cuando un gestor del calendario crea un año escolar con su
  fecha de inicio, su fecha de fin y las de sus tres lapsos, el sistema
  deberá registrarlo como **planificado**, con sus lapsos sin abrir.
- **CAL-R01.2** — Si la fecha de fin del año no es posterior a la de inicio,
  entonces el sistema deberá rechazarlo.
- **CAL-R01.3** — Si algún lapso empieza antes que el año, termina después
  del año o termina antes de empezar, entonces el sistema deberá rechazarlo.
- **CAL-R01.4** — Si los lapsos no van en orden o se solapan (un lapso
  empieza antes de que termine el anterior), entonces el sistema deberá
  rechazarlo.
- **CAL-R01.5** — El sistema deberá aceptar días entre un lapso y el
  siguiente, y lapsos que no cubren el año entero.
- **CAL-R01.6** — Si ya hay un año planificado en el plantel, entonces el
  sistema deberá rechazar la creación de otro.
- **CAL-R01.7** — El sistema deberá nombrar cada año con el año de su fecha
  de inicio y el de su fecha de fin (p. ej. «2026-2027»).
- **CAL-R01.8** — Si la fecha de fin del año queda a más de 11 meses de la de
  inicio, entonces el sistema deberá rechazarlo.
- **CAL-R01.9** — Si las fechas del año se solapan con las de otro año del
  mismo plantel, sea cual sea su estado, entonces el sistema deberá
  rechazarlo.

### CAL-R02 — Activar y cerrar el año

**Historia:** Como Director, quiero activar el año cuando empiece y cerrarlo
cuando termine, para que todo el plantel trabaje en el año correcto.

**Criterios de aceptación (EARS):**

- **CAL-R02.1** — El sistema deberá permitir como máximo un año activo por
  plantel.
- **CAL-R02.2** — Cuando un gestor activa un año planificado, el sistema
  deberá marcarlo como activo y abrir su lapso 1.
- **CAL-R02.3** — Si se intenta activar un año mientras otro está activo,
  entonces el sistema deberá rechazarlo.
- **CAL-R02.4** — Cuando un gestor cierra el año activo, confirmando la
  acción, el sistema deberá marcarlo como cerrado.
- **CAL-R02.5** — Si alguno de los tres lapsos del año no está cerrado,
  entonces el sistema deberá rechazar el cierre del año.
- **CAL-R02.6** — El sistema no deberá permitir reabrir un año cerrado.

### CAL-R03 — Editar fechas

**Historia:** Como Director, quiero corregir las fechas del año o de un
lapso, para ajustar el calendario a lo que dispone el ministerio.

**Criterios de aceptación (EARS):**

- **CAL-R03.1** — Mientras el año no esté cerrado, el sistema deberá
  permitir a un gestor editar sus fechas y las de sus lapsos no cerrados.
- **CAL-R03.2** — Si la edición deja las fechas en un estado que CAL-R01.2 a
  R01.4, R01.8 o R01.9 rechazan, entonces el sistema deberá rechazarla sin
  cambiar nada.
- **CAL-R03.4** — Cuando cambian las fechas de inicio o de fin del año, el
  sistema deberá actualizar su nombre (CAL-R01.7).
- **CAL-R03.3** — Si se intenta editar las fechas de un lapso cerrado o de un
  año cerrado, entonces el sistema deberá rechazarlo.

### CAL-R04 — Abrir, cerrar y reabrir lapsos

**Historia:** Como Director, quiero cerrar cada lapso cuando termine, para
que nadie cambie las notas después sin autorización.

**Criterios de aceptación (EARS):**

- **CAL-R04.1** — Mientras el año esté activo, el sistema deberá tener
  exactamente un lapso abierto, salvo cuando sus tres lapsos estén cerrados.
- **CAL-R04.2** — Cuando un gestor cierra el lapso abierto, confirmando la
  acción, el sistema deberá cerrarlo y abrir el siguiente, si lo hay.
- **CAL-R04.3** — El sistema no deberá abrir ni cerrar ningún lapso por el
  solo paso de sus fechas.
- **CAL-R04.4** — Cuando un gestor reabre el último lapso cerrado del año
  activo indicando un motivo, el sistema deberá reabrirlo y dejar sin abrir
  el lapso que estaba abierto, si lo había.
- **CAL-R04.5** — Si se intenta reabrir un lapso sin indicar un motivo,
  entonces el sistema deberá rechazarlo.
- **CAL-R04.6** — Si el año está cerrado o planificado, entonces el sistema
  deberá rechazar abrir, cerrar o reabrir sus lapsos.
- **CAL-R04.7** — Si se intenta reabrir un lapso que no es el último cerrado,
  entonces el sistema deberá rechazarlo.

### CAL-R05 — Bloqueo de notas por lapso

**Historia:** Como Director, quiero que las notas de un lapso cerrado no se
puedan cambiar salvo con un permiso especial, para que las calificaciones
entregadas sean definitivas.

**Criterios de aceptación (EARS):**

- **CAL-R05.1** — Mientras un lapso esté abierto, el sistema deberá
  considerar que sus notas admiten cambios.
- **CAL-R05.2** — Mientras un lapso esté cerrado o sin abrir, el sistema
  deberá considerar que sus notas no admiten cambios, salvo para quien tenga
  el permiso `editar-notas-lapso-cerrado`.
- **CAL-R05.3** — Mientras el año esté cerrado, el sistema deberá considerar
  que las notas de todos sus lapsos no admiten cambios, tampoco con el
  permiso `editar-notas-lapso-cerrado`.
- **CAL-R05.4** — El sistema no deberá incluir `editar-notas-lapso-cerrado`
  en ningún rol base, tampoco en el de Administrador, y deberá permitir
  concederlo.

### CAL-R06 — Pantalla de gestión y permisos

**Historia:** Como Director, quiero una pantalla con el año, sus lapsos y
sus estados, para gestionar el calendario sin ayuda técnica.

**Criterios de aceptación (EARS):**

- **CAL-R06.1** — El sistema deberá mostrar a los gestores del calendario
  los años del plantel, con sus fechas y estados, y los tres lapsos de cada
  uno.
- **CAL-R06.2** — Cuando un gestor va a cerrar un lapso o el año, el sistema
  deberá pedirle una confirmación explícita antes de hacerlo.
- **CAL-R06.3** — El sistema deberá permitir las acciones de CAL-R01 a R04
  solo a quien tenga el permiso `gestionar-calendario`.
- **CAL-R06.4** — Si alguien sin el permiso intenta una de esas acciones,
  entonces el sistema deberá responder 403.
- **CAL-R06.5** — El sistema deberá incluir `gestionar-calendario` entre los
  permisos base de Administrador y Director, también en los planteles que ya
  existen.

### CAL-R07 — Bitácora

- **CAL-R07.1** — El sistema deberá registrar en la bitácora del plantel
  (SPEC-005): la creación de un año, sus cambios de fechas, su activación, su
  cierre y su eliminación, y el cierre y la reapertura de cada lapso, con el
  motivo de la reapertura.

### CAL-R08 — Aislamiento por plantel

- **CAL-R08.1** — El sistema deberá mostrar y modificar solo los años y
  lapsos del plantel abierto; un año de otro plantel deberá responder como
  inexistente (404), también si se escribe su identificador en la URL (S-02).
- **CAL-R08.2** — El sistema deberá garantizar el aislamiento en la base de
  datos: una consulta sin plantel activo, o de otro plantel, no deberá
  devolver ni modificar años ni lapsos (S-01).
- **CAL-R08.3** — Cada plantel deberá tener su propio año activo: activar,
  cerrar o editar el de un plantel no deberá afectar al de otro.

### CAL-R09 — Eliminar un año planificado

**Historia:** Como Director, quiero eliminar un año que creé por error, para
no arrastrarlo en el calendario.

**Criterios de aceptación (EARS):**

- **CAL-R09.1** — Cuando un gestor elimina un año planificado, confirmando
  la acción, el sistema deberá eliminarlo con sus lapsos.
- **CAL-R09.2** — Si el año está activo o cerrado, entonces el sistema deberá
  rechazar su eliminación.

## Requisitos no funcionales

- **CAL-NF01** (seguridad) — Toda acción de gestión del calendario exige el
  permiso `gestionar-calendario` en la ruta y la regla en el caso de uso
  (S-04).
- **CAL-NF02** (integridad) — Cada transición (activar, cerrar el año,
  cerrar o reabrir un lapso) es atómica y queda registrada en la misma
  transacción que su entrada de la bitácora (ADR 0005). Dos gestores que
  actúan a la vez nunca dejan dos años activos ni dos lapsos abiertos: lo
  garantiza la base de datos.
- **CAL-NF03** (rendimiento) — La consulta «¿admite cambios este lapso para
  esta persona?» (CAL-R05) responde en menos de 10 ms (p95), porque SPEC-011
  la hará en cada guardado de notas.
- **CAL-NF04** (usabilidad) — La pantalla de gestión funciona en pantallas de
  360 px de ancho (F1-11) y usa la terminología «plantel», «año escolar» y
  «lapso» (IDG-NF08).

## Cambios en otras specs

- IAM-R11.1 (SPEC-002): el Administrador deja de tener «todos» los
  permisos; tiene todos salvo `editar-notas-lapso-cerrado` (CAL-R05.4).
  Administrador y Director reciben `gestionar-calendario` (CAL-R06.5). La
  tabla de IAM-R11.1 se actualiza en el PR de implementación de esta spec.

## Fuera de alcance y preguntas abiertas

Ninguna pregunta abierta: todas están resueltas en [Decisiones de producto
incorporadas](#decisiones-de-producto-incorporadas-2026-10-16).

Fuera de alcance: feriados, horarios, actividades del calendario, cantidad
variable de lapsos y avisos a docentes (F2-05).

## Matriz de trazabilidad

| Criterio | Test(s) |
|----------|---------|
| CAL-R01.1 | `tests/Unit/AcademicCalendar/Domain/SchoolYearTest.php` › «is planned with its three terms not yet open»; `tests/Unit/AcademicCalendar/Application/SchoolYearPlanningTest.php` › «creates a planned year, under the lock, recorded in the same transaction» |
| CAL-R01.2 | `tests/Unit/AcademicCalendar/Domain/SchoolYearTest.php` › «must end after it starts»; `tests/Feature/AcademicCalendar/Infrastructure/CalendarConstraintsTest.php` › «a year ends after it starts and has a known status» |
| CAL-R01.3 | `tests/Unit/AcademicCalendar/Domain/SchoolYearTest.php` › «keeps every term inside the year»; `tests/Unit/AcademicCalendar/Domain/SchoolYearTest.php` › «refuses a term that ends before it starts» |
| CAL-R01.4 | `tests/Unit/AcademicCalendar/Domain/SchoolYearTest.php` › «keeps the terms in order and apart»; `tests/Feature/AcademicCalendar/SchoolYearManagementTest.php` › «invalid dates come back on their fields» |
| CAL-R01.5 | `tests/Unit/AcademicCalendar/Domain/SchoolYearTest.php` › «allows days between terms and terms that do not cover the whole year» |
| CAL-R01.6 | `tests/Unit/AcademicCalendar/Domain/SchoolYearScheduleTest.php` › «allows a single planned year»; `tests/Unit/AcademicCalendar/Application/SchoolYearPlanningTest.php` › «refuses a second planned year»; `tests/Feature/AcademicCalendar/Infrastructure/CalendarConstraintsTest.php` › «an institution has one planned year at most» |
| CAL-R01.7 | `tests/Unit/AcademicCalendar/Domain/SchoolYearTest.php` › «is named after the years it starts and ends in» |
| CAL-R01.8 | `tests/Unit/AcademicCalendar/Domain/SchoolYearTest.php` › «lasts 11 months at most» |
| CAL-R01.9 | `tests/Unit/AcademicCalendar/Domain/SchoolYearScheduleTest.php` › «refuses dates that overlap another year, whatever its state»; `tests/Feature/AcademicCalendar/Infrastructure/CalendarConstraintsTest.php` › «the years of an institution never overlap, sharing a single day included» |
| CAL-R02.1 | `tests/Unit/AcademicCalendar/Domain/SchoolYearScheduleTest.php` › «allows a single active year»; `tests/Feature/AcademicCalendar/Infrastructure/CalendarConstraintsTest.php` › «an institution has one active year at most» |
| CAL-R02.2 | `tests/Unit/AcademicCalendar/Domain/SchoolYearTest.php` › «opens its first term when activated»; `tests/Unit/AcademicCalendar/Application/SchoolYearLifecycleTest.php` › «activates a planned year, recorded under the lock» |
| CAL-R02.3 | `tests/Unit/AcademicCalendar/Application/SchoolYearLifecycleTest.php` › «refuses to activate a year while another is active» |
| CAL-R02.4 | `tests/Unit/AcademicCalendar/Domain/SchoolYearTest.php` › «closes, once confirmed, only with its three terms closed»; `tests/Unit/AcademicCalendar/Application/SchoolYearLifecycleTest.php` › «closes the year once its three terms are closed and it is confirmed» |
| CAL-R02.5 | `tests/Unit/AcademicCalendar/Domain/SchoolYearTest.php` › «closes, once confirmed, only with its three terms closed» |
| CAL-R02.6 | `tests/Unit/AcademicCalendar/Domain/SchoolYearTest.php` › «never comes back once closed» |
| CAL-R03.1 | `tests/Feature/AcademicCalendar/SchoolYearManagementTest.php` › «the dates of a year can be corrected, and its name follows them» |
| CAL-R03.2 | `tests/Unit/AcademicCalendar/Application/SchoolYearPlanningTest.php` › «refuses new dates that overlap another year» |
| CAL-R03.3 | `tests/Unit/AcademicCalendar/Domain/SchoolYearTest.php` › «keeps the dates of a closed term»; `tests/Unit/AcademicCalendar/Domain/SchoolYearTest.php` › «never comes back once closed» |
| CAL-R03.4 | `tests/Unit/AcademicCalendar/Domain/SchoolYearTest.php` › «changes its dates, and its name follows them» |
| CAL-R04.1 | `tests/Unit/AcademicCalendar/Domain/SchoolYearTest.php` › «keeps exactly one term open while active, until all three are closed»; `tests/Feature/AcademicCalendar/Infrastructure/CalendarConstraintsTest.php` › «a year has terms 1 to 3, each once, and one open at most» |
| CAL-R04.2 | `tests/Unit/AcademicCalendar/Domain/SchoolYearTest.php` › «closes the open term and opens the next one, once confirmed»; `tests/Unit/AcademicCalendar/Application/SchoolYearLifecycleTest.php` › «closes the open term once confirmed, recording which one» |
| CAL-R04.3 | `tests/Feature/AcademicCalendar/SchoolYearManagementTest.php` › «terms do not open or close as their dates pass» |
| CAL-R04.4 | `tests/Unit/AcademicCalendar/Domain/SchoolYearTest.php` › «reopens the last closed term with a reason, leaving the open one not yet open»; `tests/Unit/AcademicCalendar/Application/SchoolYearLifecycleTest.php` › «reopens the last closed term, recording the reason» |
| CAL-R04.5 | `tests/Unit/AcademicCalendar/Domain/SchoolYearTest.php` › «needs a reason to reopen a term» |
| CAL-R04.6 | `tests/Unit/AcademicCalendar/Domain/SchoolYearTest.php` › «does not touch the terms of a planned year»; `tests/Unit/AcademicCalendar/Domain/SchoolYearTest.php` › «never comes back once closed» |
| CAL-R04.7 | `tests/Unit/AcademicCalendar/Domain/SchoolYearTest.php` › «only reopens the last closed term» |
| CAL-R05.1 | `tests/Unit/AcademicCalendar/Domain/TermEditabilityTest.php` › «lets anyone change the grades of the open term»; `tests/Feature/AcademicCalendar/Infrastructure/TermEditabilityTest.php` › «the open term admits grade changes; closed and not yet open ones only with the permission» |
| CAL-R05.2 | `tests/Unit/AcademicCalendar/Domain/TermEditabilityTest.php` › «locks closed and not yet open terms, except for the special permission»; `tests/Feature/AcademicCalendar/Infrastructure/TermEditabilityTest.php` › «the open term admits grade changes; closed and not yet open ones only with the permission» |
| CAL-R05.3 | `tests/Unit/AcademicCalendar/Domain/TermEditabilityTest.php` › «locks every term of a closed year, even with the special permission»; `tests/Feature/AcademicCalendar/Infrastructure/TermEditabilityTest.php` › «no term of a closed year admits changes, not even with the permission» |
| CAL-R05.4 | `tests/Unit/IdentityAccess/Domain/Role/RoleSetTest.php` › «no base role carries the permission to change grades of closed terms»; `tests/Feature/AcademicCalendar/CalendarPermissionsTest.php` › «the special permission exists but no role has it»; `tests/Feature/AcademicCalendar/ClosedTermPermissionTest.php` › «an administrador grants and withdraws the permission, and it is recorded»; `tests/Feature/AcademicCalendar/ClosedTermPermissionTest.php` › «a director can not grant it, not even to themselves» |
| CAL-R06.1 | `tests/Feature/AcademicCalendar/SchoolYearManagementTest.php` › «a manager sees the years of the institution with their terms»; `tests/Feature/AcademicCalendar/Infrastructure/TermEditabilityTest.php` › «the screen lists the years, newest first, with their terms» |
| CAL-R06.2 | `tests/Feature/AcademicCalendar/SchoolYearManagementTest.php` › «closing asks for an explicit confirmation» |
| CAL-R06.3 | `tests/Feature/AcademicCalendar/SchoolYearManagementTest.php` › «the administrador manages the calendar too»; `tests/Feature/AcademicCalendar/SchoolYearManagementTest.php` › «a member without the permission gets 403» |
| CAL-R06.4 | `tests/Feature/AcademicCalendar/SchoolYearManagementTest.php` › «a member without the permission gets 403» |
| CAL-R06.5 | `tests/Feature/AcademicCalendar/CalendarPermissionsTest.php` › «existing institutions get gestionar-calendario for Administrador and Director»; `tests/Unit/IdentityAccess/Domain/Role/RoleSetTest.php` › «each base role carries the permissions its specs give it»; `tests/Feature/AcademicCalendar/SchoolYearManagementTest.php` › «the menu offers the calendar only to its managers» |
| CAL-R07.1 | `tests/Feature/AcademicCalendar/SchoolYearManagementTest.php` › «a whole school year, from planning to closing»; `tests/Unit/AcademicCalendar/Application/SchoolYearPlanningTest.php` › «records only the dates that changed»; `tests/Unit/AcademicCalendar/Application/SchoolYearPlanningTest.php` › «deletes a planned year once confirmed, and records it» |
| CAL-R08.1 | `tests/Feature/AcademicCalendar/SchoolYearManagementTest.php` › «a year of another institution answers as not found»; `tests/Feature/AcademicCalendar/Infrastructure/TermEditabilityTest.php` › «a year of another institution, or one that does not exist, admits nothing» |
| CAL-R08.2 | `tests/Feature/AcademicCalendar/CalendarIsolationTest.php` › «an institution only sees its own years and terms»; `tests/Feature/AcademicCalendar/CalendarIsolationTest.php` › «with no institution active nothing is visible or writable»; `tests/Feature/AcademicCalendar/CalendarIsolationTest.php` › «another institution's year can not be changed, even with raw SQL» |
| CAL-R08.3 | `tests/Feature/AcademicCalendar/SchoolYearManagementTest.php` › «each institution has its own active year»; `tests/Feature/AcademicCalendar/Infrastructure/CalendarConstraintsTest.php` › «different institutions keep their own years, overlapping or not» |
| CAL-R09.1 | `tests/Feature/AcademicCalendar/SchoolYearManagementTest.php` › «a planned year can be deleted, an active one can not»; `tests/Unit/AcademicCalendar/Application/SchoolYearPlanningTest.php` › «deletes a planned year once confirmed, and records it» |
| CAL-R09.2 | `tests/Unit/AcademicCalendar/Domain/SchoolYearTest.php` › «can only be deleted while planned»; `tests/Unit/AcademicCalendar/Application/SchoolYearPlanningTest.php` › «does not delete a year that was activated» |
| CAL-NF01 | `tests/Feature/AcademicCalendar/SchoolYearManagementTest.php` › «a member without the permission gets 403» |
| CAL-NF02 | `tests/Feature/AcademicCalendar/Infrastructure/CalendarConstraintsTest.php` › «an institution has one active year at most»; `tests/Feature/AcademicCalendar/Infrastructure/EloquentSchoolYearRepositoryTest.php` › «turns a broken database rule into a conflict, saving nothing»; `tests/Unit/AcademicCalendar/Application/SchoolYearPlanningTest.php` › «creates a planned year, under the lock, recorded in the same transaction» |
| CAL-NF03 | `tests/Feature/AcademicCalendar/Infrastructure/TermEditabilityTest.php` › «the editability check answers within 10 ms (p95)» |
| CAL-NF04 | `tests/Architecture/TerminologyTest.php` › «the interface calls them planteles» |
