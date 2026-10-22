---
spec: 009-estudiantes
prefijo: EST
estado: aprobada
fecha: 2026-10-21
aprobada: 2026-10-21
backlog: F1-20 – F1-24
módulo: Students
depende-de: 001-tenancy, 003-catalogos (geografía), 004-identidad-global (invitaciones, cuentas, IDG-R08.3), 005-auditoria
la-usan: 008-estructura-academica, 010-inscripcion, 011-evaluacion, 012-documentos, portal del representante (F2-07)
---

# Requisitos — Estudiantes y representantes

## Contexto

Todo lo académico gira alrededor del estudiante: la inscripción (010), las
notas (011) y los documentos (012) necesitan saber quién es cada estudiante
y quiénes lo representan. Hoy AulaX solo tiene cuentas con el rol
Estudiante o Representante, sin ficha ni vínculo entre ellos.

Esta spec crea la ficha del estudiante y la de sus representantes en cada
plantel. Cubre su identificación oficial (cédula de identidad o cédula
escolar, según las reglas del MPPE para el SIGE), los retiros y los
traslados entre planteles AulaX. Además activa el bloqueo de IDG-R08.3: no
se puede eliminar la cuenta del único representante legal de un estudiante.

## Alcance

- **Incluye:**
  - registrar, editar, consultar y buscar estudiantes del plantel abierto;
  - su identificación: cédula de identidad (C.I.) o, mientras no la tenga,
    cédula escolar (C.E.), con el paso de una a otra;
  - los representantes de cada estudiante, su parentesco y quién es
    representante legal;
  - datos básicos de salud, visibles solo para quien gestiona estudiantes;
  - el retiro y la reincorporación;
  - el traslado entre dos planteles AulaX;
  - vincular la cuenta de un representante con su ficha, y que vea la ficha
    básica de sus representados y pida correcciones;
  - la bitácora, los permisos y el aislamiento por plantel.
- **Excluye:**
  - la inscripción en un año escolar, el grado y la sección (SPEC-010, con
    la estructura de SPEC-008);
  - las notas y el historial académico (SPEC-011);
  - las constancias de estudio, retiro o traslado en PDF (SPEC-012);
  - el portal completo del representante (F2-07): aquí solo ve la ficha
    básica y pide correcciones;
  - la cuenta del propio estudiante y lo que ve con ella;
  - limitar qué estudiantes ve cada docente según sus secciones (SPEC-008);
  - traslados desde o hacia planteles que no usan AulaX;
  - importar estudiantes en lote (desde el SIGE o una hoja de cálculo).

## Decisiones de producto incorporadas (2026-10-21)

| Pregunta | Decisión |
|----------|----------|
| Alcance | Ficha del estudiante, representantes, retiros y traslados entre planteles AulaX. |
| Quién registra y edita | Administrador y Director, con el permiso nuevo `gestionar-estudiantes`. Coordinador y Docente solo consultan (`ver-estudiantes`). |
| Cuenta del representante | Opcional. El representante es una ficha con sus datos; si se le quiere dar acceso, se le invita y su cuenta queda vinculada a la ficha. |
| Identidad del estudiante | La ficha es de cada plantel y está aislada por RLS. Un traslado copia la ficha al plantel destino. |
| Formato de la cédula escolar | 12 caracteres `N T AA CCCCCCCC`: `N` = V o E; `T` = tipo de parto (1 sencillo, 2 primer morocho, 3 segundo morocho, 4 trillizo o siguientes); `AA` = últimos dos dígitos del año de nacimiento; `C` = cédula de la madre (de preferencia) o del padre, completada a 8 dígitos con ceros a la izquierda. Ejemplos: `V11915432100`, `V32109876543`. |
| Validación de la C.E. | Formato y coherencia: el año coincide con el de nacimiento y la cédula embebida es la de un representante registrado del estudiante. |
| De C.E. a C.I. | Cuando el estudiante obtiene su C.I. del SAIME, esta pasa a ser su identificación y la C.E. queda en su historial. |
| Traslados | El plantel destino pide al estudiante por su identificación. El origen aprueba, y se copian la ficha y los representantes. En el origen el estudiante queda retirado por traslado. |
| Salud | Alergias, condiciones médicas y discapacidad. Las ven Administrador y Director; los demás solo un aviso de que hay información médica. |
| Qué estudiantes ven Coordinador y Docente | Todos los del plantel, sin los datos de salud. SPEC-008 lo limitará a sus secciones. |
| Representantes | Varios por estudiante, con al menos un representante legal. Uno se marca como contacto principal. |
| Retiro | Con fecha y motivo. El estudiante sigue en el historial y puede reincorporarse. |
| Representante con cuenta | Ve la ficha básica (sin salud) de sus representados y puede pedir correcciones. |
| C.E. repetida entre hermanos | La C.E. es única en el plantel. Si dos hermanos nacidos el mismo año darían la misma, se rechaza y el gestor usa otro tipo de parto, como en el SIGE. |
| Carácter `N` de la C.E. | Es la nacionalidad del representante cuya C.I. va embebida: `N` + `C` deben ser la C.I. (V o E y número) de un representante registrado. |
| Representante extranjero | Puede identificarse con pasaporte. Un pasaporte no sirve para formar la C.E. |
| Caducidad de un traslado | 15 días naturales sin respuesta del origen. |
| Motivos de retiro | Cambio de domicilio, cambio de plantel, razones económicas o familiares, y otro (con observación). |
| Cuentas en un traslado | Se copian los datos del representante, no el vínculo con su cuenta: el destino lo invita si quiere darle acceso. |
| Campos de la ficha | Además: país de nacimiento y, si es Venezuela, el estado del catálogo; y un teléfono de emergencia. |
| Conservación | Las fichas de estudiantes retirados se conservan indefinidamente por ahora. La política de borrado se define con la validación legal (D1). |

## Glosario

| Término | Significado |
|---------|-------------|
| Estudiante | Niño, niña o adolescente registrado en un plantel, con su ficha. |
| Ficha | Los datos de un estudiante en un plantel: personales, identificación, salud y representantes. |
| C.I. | Cédula de identidad del SAIME: `V` (venezolano) o `E` (extranjero) y un número. |
| C.E. | Cédula escolar: código de 12 caracteres del MPPE para quien aún no tiene C.I. |
| Representante | Persona responsable del estudiante ante el plantel (madre, padre u otro familiar). Tiene ficha propia en el plantel y puede representar a varios estudiantes. |
| Representante legal | Representante con la responsabilidad legal sobre el estudiante. |
| Contacto principal | El representante a quien el plantel contacta primero. |
| Gestor | Quien tiene `gestionar-estudiantes` en el plantel abierto. |
| Retiro | Baja del estudiante en el plantel, con fecha y motivo. Su ficha se conserva. |
| Traslado | Paso de un estudiante de un plantel AulaX (origen) a otro (destino), con su ficha. |

## Requisitos

### EST-R01 — Ficha del estudiante

**Historia:** Como gestor, quiero registrar a cada estudiante con sus datos
oficiales, para tenerlo identificado en el plantel desde su ingreso.

**Criterios de aceptación (EARS):**

- **EST-R01.1** — Cuando un gestor registra un estudiante con nombres,
  apellidos, sexo, fecha de nacimiento, nacionalidad, identificación y al
  menos un representante legal, el sistema deberá crearlo como estudiante
  activo del plantel abierto.
- **EST-R01.2** — Si falta alguno de esos datos, entonces el sistema deberá
  rechazar el registro e indicar cada dato que falta.
- **EST-R01.3** — Si la fecha de nacimiento es posterior a hoy, entonces el
  sistema deberá rechazarla.
- **EST-R01.4** — El sistema deberá permitir registrar, además, el país de
  nacimiento (y, si es Venezuela, el estado del catálogo), la dirección
  (parroquia del catálogo y dirección escrita) y un teléfono de emergencia,
  y dejarlos vacíos.
- **EST-R01.5** — Cuando un gestor edita los datos de un estudiante, el
  sistema deberá guardarlos con las mismas reglas del registro.

### EST-R02 — Identificación: cédula de identidad y cédula escolar

**Historia:** Como gestor, quiero identificar a cada estudiante con su
cédula o, si aún no la tiene, con su cédula escolar, como pide el SIGE.

**Criterios de aceptación (EARS):**

- **EST-R02.1** — El sistema deberá identificar a cada estudiante con una
  C.I. o, mientras no la tenga, con una C.E.
- **EST-R02.2** — Si una C.I. no está formada por `V` o `E` y un número de
  1 a 8 dígitos, entonces el sistema deberá rechazarla.
- **EST-R02.3** — Si una C.E. no tiene exactamente 12 caracteres con la forma
  `N T AA CCCCCCCC` (`N` = `V` o `E`; `T` entre 1 y 4; `AA` y `C`
  dígitos), entonces el sistema deberá rechazarla.
- **EST-R02.4** — Si los dígitos `AA` de la C.E. no coinciden con los dos
  últimos dígitos del año de nacimiento del estudiante, entonces el sistema
  deberá rechazarla.
- **EST-R02.5** — Si el carácter `N` y los 8 dígitos `C` de la C.E. no
  coinciden con la C.I. (nacionalidad y número, completado a 8 dígitos con
  ceros a la izquierda) de alguno de los representantes registrados del
  estudiante, entonces el sistema deberá rechazarla.
- **EST-R02.6** — Si otro estudiante del plantel ya tiene esa misma
  identificación, entonces el sistema deberá rechazarla e indicar que, para
  hermanos nacidos el mismo año, se use otro tipo de parto.
- **EST-R02.7** — Cuando un gestor registra la C.I. de un estudiante que
  tenía C.E., el sistema deberá usar la C.I. como su identificación y
  conservar la C.E. en su historial.
- **EST-R02.8** — Cuando se busca a un estudiante por una C.E. que tuvo
  antes, el sistema deberá encontrarlo.

### EST-R03 — Representantes

**Historia:** Como gestor, quiero registrar a los representantes de cada
estudiante y su parentesco, para saber a quién acudir y quién responde
legalmente por él.

**Criterios de aceptación (EARS):**

- **EST-R03.1** — Cuando un gestor registra un representante con nombres,
  apellidos, documento de identidad, teléfono y parentesco con el
  estudiante (madre, padre u otro), el sistema deberá vincularlo al
  estudiante.
- **EST-R03.2** — El sistema deberá aceptar como documento del
  representante una C.I. (V o E y número) o, si es extranjero sin C.I., un
  pasaporte.
- **EST-R03.9** — El sistema deberá permitir que el representante tenga
  además un correo y una dirección, y dejarlos vacíos.
- **EST-R03.3** — Si ya existe en el plantel un representante con ese
  documento, entonces el sistema deberá vincular al mismo representante,
  sin crear un duplicado (por ejemplo, para hermanos).
- **EST-R03.4** — El sistema deberá permitir varios representantes por
  estudiante, e indicar en cada vínculo si es representante legal.
- **EST-R03.5** — Si un cambio deja al estudiante sin ningún representante
  legal, entonces el sistema deberá rechazarlo.
- **EST-R03.6** — El sistema deberá mantener exactamente un contacto
  principal entre los representantes de cada estudiante.
- **EST-R03.7** — Si se intenta desvincular al representante cuya C.I.
  forma la C.E. vigente del estudiante, entonces el sistema deberá
  rechazarlo.
- **EST-R03.8** — Cuando un gestor corrige los datos de un representante, el
  sistema deberá mostrar los datos nuevos en todos sus representados del
  plantel.

### EST-R04 — Datos de salud

**Historia:** Como gestor, quiero registrar las alergias, condiciones
médicas y discapacidad de un estudiante, para atenderlo bien sin exponer
esa información a quien no la necesita.

**Criterios de aceptación (EARS):**

- **EST-R04.1** — El sistema deberá permitir registrar, de forma opcional,
  las alergias, las condiciones médicas y la discapacidad de un estudiante.
- **EST-R04.2** — El sistema deberá mostrar los datos de salud solo a quien
  tenga `gestionar-estudiantes`.
- **EST-R04.3** — Mientras un estudiante tenga datos de salud, el sistema
  deberá mostrar a quien solo tenga `ver-estudiantes` un aviso de que hay
  información médica, sin su contenido.

### EST-R05 — Consulta y búsqueda

**Historia:** Como miembro del plantel con `ver-estudiantes`, quiero
encontrar rápido a un estudiante y ver su ficha.

**Criterios de aceptación (EARS):**

- **EST-R05.1** — El sistema deberá mostrar a quien tenga `ver-estudiantes`
  la lista de estudiantes del plantel abierto, con su nombre, su
  identificación y su estado (activo o retirado).
- **EST-R05.2** — El sistema deberá mostrar por defecto solo a los
  estudiantes activos, y permitir ver también a los retirados.
- **EST-R05.3** — Cuando se busca por nombre, apellido o identificación, el
  sistema deberá mostrar los estudiantes que coinciden.
- **EST-R05.4** — Cuando se abre la ficha de un estudiante, el sistema
  deberá mostrar sus datos y sus representantes, con los datos de salud
  según EST-R04.

### EST-R06 — Retiro y reincorporación

**Historia:** Como gestor, quiero retirar a un estudiante que deja el
plantel sin perder su historial, y reincorporarlo si vuelve.

**Criterios de aceptación (EARS):**

- **EST-R06.1** — Cuando un gestor retira a un estudiante activo con una
  fecha y un motivo, el sistema deberá marcarlo como retirado y conservar
  su ficha.
- **EST-R06.2** — Si falta la fecha o el motivo, o la fecha es posterior a
  hoy, entonces el sistema deberá rechazar el retiro.
- **EST-R06.3** — El sistema deberá ofrecer como motivo: cambio de
  domicilio, cambio de plantel, razones económicas o familiares, u otro; y
  permitir una observación opcional.
- **EST-R06.4** — Cuando un gestor reincorpora a un estudiante retirado, el
  sistema deberá marcarlo de nuevo como activo y conservar el historial de
  sus retiros.

### EST-R07 — Traslado entre planteles AulaX

**Historia:** Como gestor del plantel al que llega un estudiante, quiero
pedir su ficha al plantel de donde viene, para no registrarla de nuevo.

**Criterios de aceptación (EARS):**

- **EST-R07.1** — Cuando un gestor del plantel destino solicita un traslado
  con la identificación de un estudiante, el sistema deberá enviar la
  solicitud al plantel donde ese estudiante está activo.
- **EST-R07.2** — El sistema deberá responder al destino lo mismo exista o
  no un estudiante activo con esa identificación, sin revelar sus datos ni
  su plantel antes de la aprobación.
- **EST-R07.3** — Si el plantel destino ya tiene un estudiante con esa
  identificación, entonces el sistema deberá rechazar la solicitud.
- **EST-R07.4** — El sistema deberá mostrar a los gestores del origen las
  solicitudes pendientes, con el nombre del plantel destino y el
  estudiante pedido.
- **EST-R07.5** — Cuando un gestor del origen aprueba la solicitud, el
  sistema deberá crear en el destino al estudiante activo con su ficha,
  incluidos sus datos de salud y sus representantes, y marcarlo en el
  origen como retirado por cambio de plantel, con la fecha de la
  aprobación.
- **EST-R07.6** — Cuando un gestor del origen rechaza la solicitud con un
  motivo, el sistema deberá mostrar al destino el rechazo y su motivo.
- **EST-R07.7** — Si el origen no responde en 15 días naturales, entonces el
  sistema deberá dar la solicitud por caducada y mostrarlo al destino.
- **EST-R07.8** — El sistema no deberá copiar al destino el vínculo entre un
  representante y su cuenta: para darle acceso, el destino lo invita
  (EST-R08.1).

### EST-R08 — Cuenta del representante

**Historia:** Como representante, quiero ver la ficha de mis representados
y pedir que corrijan un dato, sin depender de ir al plantel.

**Criterios de aceptación (EARS):**

- **EST-R08.1** — Cuando un gestor invita a un representante que tiene
  correo, el sistema deberá enviarle una invitación al plantel con el rol
  Representante (IDG-R04) y, cuando la acepte, vincular su cuenta a esa
  ficha de representante.
- **EST-R08.2** — Mientras una cuenta esté vinculada a un representante, el
  sistema deberá mostrarle la ficha básica (sin datos de salud) de sus
  representados del plantel.
- **EST-R08.3** — Si una cuenta pide la ficha de un estudiante que no
  representa, entonces el sistema deberá responder como si no existiera.
- **EST-R08.4** — Cuando un representante envía una solicitud de corrección
  de su ficha o de la de un representado, el sistema deberá mostrarla a
  los gestores del plantel.
- **EST-R08.5** — Cuando un gestor marca una solicitud de corrección como
  atendida, el sistema deberá mostrar ese estado al representante.
- **EST-R08.6** — Si la cuenta está vinculada al único representante legal
  de algún estudiante activo, entonces el sistema deberá impedir su
  eliminación (IDG-R08.3).

### EST-R09 — Permisos

- **EST-R09.1** — El sistema deberá permitir registrar, editar, retirar,
  reincorporar y trasladar estudiantes, y gestionar sus representantes,
  solo a quien tenga el permiso `gestionar-estudiantes`.
- **EST-R09.2** — El sistema deberá incluir `gestionar-estudiantes` en los
  roles base Administrador y Director, también en los planteles que ya
  existen.
- **EST-R09.3** — El sistema deberá permitir consultar la lista y las fichas
  solo a quien tenga `ver-estudiantes` (Administrador, Director,
  Coordinador y Docente), además del representante según EST-R08.
- **EST-R09.4** — Si alguien sin el permiso intenta una de esas acciones,
  entonces el sistema deberá responder 403.

### EST-R10 — Bitácora

- **EST-R10.1** — El sistema deberá registrar en la bitácora del plantel
  (SPEC-005) el registro de un estudiante y cada cambio de sus datos, su
  identificación y sus representantes, con los valores anterior y nuevo.
- **EST-R10.2** — El sistema deberá registrar los retiros,
  reincorporaciones, solicitudes de traslado y su aprobación o rechazo.
- **EST-R10.3** — Cuando cambian los datos de salud, el sistema deberá
  registrar qué dato cambió, sin guardar su valor.

### EST-R11 — Aislamiento por plantel

- **EST-R11.1** — El sistema deberá mostrar y modificar solo los
  estudiantes, representantes y solicitudes del plantel abierto (S-01).
- **EST-R11.2** — El sistema deberá tomar el plantel únicamente del
  subdominio de la petición, nunca de un dato que envíe el usuario (S-02).
- **EST-R11.3** — El sistema deberá entregar al plantel destino los datos
  de un estudiante solo después de que el origen apruebe el traslado.

## Requisitos no funcionales

- **EST-NF01** (seguridad) — Toda modificación exige `gestionar-estudiantes`
  en la ruta y en el caso de uso (S-04).
- **EST-NF02** (privacidad) — Los datos de salud y de identificación de
  estudiantes y representantes nunca aparecen en los logs. Los datos de
  salud tampoco en la bitácora ni en respuestas a quien no gestiona
  estudiantes. Ningún dato personal de un estudiante sale hacia un servicio
  de IA sin seudonimizar (módulo `Ai`).
- **EST-NF03** (rendimiento) — La búsqueda de estudiantes responde en menos
  de 300 ms (p95) en un plantel con 5 000 estudiantes.
- **EST-NF04** (seguridad) — Las solicitudes de traslado tienen límite de
  frecuencia por plantel, para que no sirvan para averiguar en qué otros
  planteles están los estudiantes (umbral a fijar en el diseño).
- **EST-NF05** (usabilidad) — Las pantallas funcionan en 360 px de ancho
  (F1-11) y usan la terminología «plantel» (IDG-NF08).

## Cambios en otras specs

- IAM-R11.1 (SPEC-002): el Administrador y el Director reciben
  `gestionar-estudiantes`. Se actualiza en el PR de implementación.
- IDG-R08.3 (SPEC-004): deja de ser solo el punto de extensión; lo cumple
  EST-R08.6.

## Fuera de alcance y preguntas abiertas

Ninguna pregunta abierta: todas están resueltas en [Decisiones de producto
incorporadas](#decisiones-de-producto-incorporadas-2026-10-21).

**Fuera de alcance:** borrar fichas de estudiantes retirados (se define con
la validación legal, D1); inscripción, grado y sección (SPEC-008 y SPEC-010);
notas (SPEC-011); constancias en PDF (SPEC-012); portal completo del
representante (F2-07); cuenta del estudiante; traslados con planteles que
no usan AulaX; importación en lote.

## Matriz de trazabilidad

| Criterio | Test(s) |
|----------|---------|
| EST-R01.1 | `tests/Unit/Students/Domain/StudentTest.php` › «registers an active student with its data, identification and guardians»; `tests/Unit/Students/Application/RegisterStudentTest.php` › «registers a student with a new guardian, recorded inside the transaction»; `tests/Feature/Students/StudentManagementTest.php` › «a manager registers a student with its guardian and lands on its record» |
| EST-R01.2 | `tests/Unit/Students/Domain/StudentTest.php` › «reports every missing required datum at once»; `tests/Unit/Students/Application/RegisterStudentTest.php` › «reports the form errors by field, each guardian on its own» |
| EST-R01.3 | `tests/Unit/Students/Domain/StudentTest.php` › «refuses a birth date after today» |
| EST-R01.4 | `tests/Unit/Students/Domain/StudentTest.php` › «keeps the optional data, or none»; `tests/Unit/Catalogs/Application/CountriesTest.php` › «lists every ISO 3166-1 country with its Spanish name, by name»; `tests/Unit/Students/Application/RegisterStudentTest.php` › «refuses a birth state that is not in the catalog» |
| EST-R01.5 | `tests/Unit/Students/Domain/StudentTest.php` › «updates its data with the same rules»; `tests/Unit/Students/Application/RegisterStudentTest.php` › «edits a student and records only what changed» |
| EST-R02.1 | `tests/Unit/Students/Domain/IdentityTest.php` › «identifies a student by one or the other» |
| EST-R02.2 | `tests/Unit/Students/Domain/IdentityTest.php` › «rejects a cédula that is not V or E and 1 to 8 digits» |
| EST-R02.3 | `tests/Unit/Students/Domain/IdentityTest.php` › «rejects a cédula escolar that is not N T AA CCCCCCCC» |
| EST-R02.4 | `tests/Unit/Students/Domain/StudentGuardianshipTest.php` › «needs the year of the cédula escolar to be the birth year» |
| EST-R02.5 | `tests/Unit/Students/Domain/StudentGuardianshipTest.php` › «needs the cédula escolar to be formed with the cédula of one of its guardians» |
| EST-R02.6 | `tests/Feature/Students/Infrastructure/StudentRepositoryTest.php` › «tells whether another student of the institution has an identification»; `tests/Unit/Students/Application/RegisterStudentTest.php` › «refuses an identification another student of the institution has, suggesting another birth type for siblings» |
| EST-R02.7 | `tests/Unit/Students/Domain/StudentGuardianshipTest.php` › «moves to its cédula de identidad, keeping the cédula escolar in its history»; `tests/Unit/Students/Application/RegisterStudentTest.php` › «edits a student and records only what changed» |
| EST-R02.8 | `tests/Feature/Students/Infrastructure/StudentQueriesTest.php` › «finds students by name, family name or identification, including a former cédula escolar» |
| EST-R03.1 | `tests/Unit/Students/Domain/GuardianTest.php` › «registers a guardian with its required data»; `tests/Unit/Students/Application/RegisterStudentTest.php` › «registers a student with a new guardian, recorded inside the transaction» |
| EST-R03.2 | `tests/Unit/Students/Domain/IdentityTest.php` › «identifies a guardian by cédula or, if foreign without one, by passport»; `tests/Unit/Students/Domain/GuardianTest.php` › «takes a passport for a foreign guardian without cédula» |
| EST-R03.3 | `tests/Feature/Students/Infrastructure/StudentRepositoryTest.php` › «finds a guardian of the institution by its document, and corrects it»; `tests/Unit/Students/Application/RegisterStudentTest.php` › «links the guardian the institution already has with that document, without overwriting it»; `tests/Feature/Students/Infrastructure/StudentQueriesTest.php` › «finds a guardian of the institution by its document, to link it again» |
| EST-R03.4 | `tests/Unit/Students/Domain/StudentGuardianshipTest.php` › «takes several guardians, telling which ones are legal» |
| EST-R03.5 | `tests/Unit/Students/Domain/StudentGuardianshipTest.php` › «needs a legal guardian»; `tests/Unit/Students/Application/RegisterStudentTest.php` › «applies the rules of the record to the guardians» |
| EST-R03.6 | `tests/Unit/Students/Domain/StudentGuardianshipTest.php` › «needs exactly one primary contact» |
| EST-R03.7 | `tests/Unit/Students/Domain/StudentGuardianshipTest.php` › «does not unlink the guardian whose cédula forms the current cédula escolar» |
| EST-R03.8 | `tests/Unit/Students/Domain/GuardianTest.php` › «is corrected, its new data replacing the old»; `tests/Unit/Students/Application/HealthAndGuardianTest.php` › «corrects a guardian once for every student it represents, recording the old and new values» |
| EST-R03.9 | `tests/Unit/Students/Domain/GuardianTest.php` › «keeps the optional email and address, or none» |
| EST-R04.1 | `tests/Unit/Students/Domain/StudentLifecycleTest.php` › «keeps optional health data, trimmed»; `tests/Unit/Students/Application/HealthAndGuardianTest.php` › «changes the health data, recording which ones without their values» |
| EST-R04.2 | `tests/Feature/Students/Infrastructure/StudentQueriesTest.php` › «shows the record with its guardians and, to managers, its health data»; `tests/Feature/Students/Infrastructure/StudentQueriesTest.php` › «only warns that there is medical information to whoever does not manage students»; `tests/Feature/Students/StudentManagementTest.php` › «teachers and coordinators see the students, without their health data»; `tests/Feature/Students/StudentManagementTest.php` › «managers see the health data» |
| EST-R04.3 | `tests/Feature/Students/Infrastructure/StudentQueriesTest.php` › «only warns that there is medical information to whoever does not manage students»; `tests/Feature/Students/StudentManagementTest.php` › «teachers and coordinators see the students, without their health data» |
| EST-R05.1 | `tests/Feature/Students/Infrastructure/StudentQueriesTest.php` › «lists the active students of the institution by family name, with their identification»; `tests/Feature/Students/StudentManagementTest.php` › «teachers and coordinators see the students, without their health data»; `tests/Feature/Students/StudentManagementTest.php` › «the permissions are shared with the pages» |
| EST-R05.2 | `tests/Feature/Students/Infrastructure/StudentQueriesTest.php` › «shows the withdrawn students only when asked» |
| EST-R05.3 | `tests/Feature/Students/Infrastructure/StudentQueriesTest.php` › «finds students by name, family name or identification, including a former cédula escolar» |
| EST-R05.4 | `tests/Feature/Students/Infrastructure/StudentQueriesTest.php` › «shows the record with its guardians and, to managers, its health data»; `tests/Feature/Students/StudentManagementTest.php` › «teachers and coordinators see the students, without their health data» |
| EST-R06.1 | `tests/Unit/Students/Domain/StudentLifecycleTest.php` › «withdraws an active student, keeping its record»; `tests/Unit/Students/Application/WithdrawalTest.php` › «withdraws a student with its date and reason, recorded» |
| EST-R06.2 | `tests/Unit/Students/Domain/StudentLifecycleTest.php` › «needs the date and the reason of a withdrawal, not in the future»; `tests/Unit/Students/Application/WithdrawalTest.php` › «refuses a withdrawal dated after today, by the clock» |
| EST-R06.3 | `tests/Unit/Students/Domain/StudentLifecycleTest.php` › «offers the four reasons of the list» |
| EST-R06.4 | `tests/Unit/Students/Domain/StudentLifecycleTest.php` › «reinstates a withdrawn student, keeping the history of its withdrawals»; `tests/Unit/Students/Application/WithdrawalTest.php` › «reinstates a withdrawn student, recorded» |
| EST-R07.1 | |
| EST-R07.2 | |
| EST-R07.3 | |
| EST-R07.4 | |
| EST-R07.5 | |
| EST-R07.6 | |
| EST-R07.7 | |
| EST-R07.8 | |
| EST-R08.1 | |
| EST-R08.2 | |
| EST-R08.3 | |
| EST-R08.4 | |
| EST-R08.5 | |
| EST-R08.6 | |
| EST-R09.1 | `tests/Unit/Students/Application/RegisterStudentTest.php` › «refuses whoever may not manage students»; `tests/Feature/Students/StudentManagementTest.php` › «a manager registers a student with its guardian and lands on its record»; `tests/Feature/Students/StudentManagementTest.php` › «who may not manage students gets 403 on every change» |
| EST-R09.2 | `tests/Feature/Students/StudentPermissionsTest.php` › «existing institutions get gestionar-estudiantes for Administrador and Director only»; `tests/Unit/IdentityAccess/Domain/Role/RoleSetTest.php` › «each base role carries the permissions its specs give it» |
| EST-R09.3 | `tests/Feature/Students/StudentManagementTest.php` › «teachers and coordinators see the students, without their health data»; `tests/Feature/Students/StudentManagementTest.php` › «a guardian or a student without ver-estudiantes does not see the list» |
| EST-R09.4 | `tests/Feature/Students/StudentManagementTest.php` › «who may not manage students gets 403 on every change»; `tests/Feature/Students/StudentManagementTest.php` › «a guardian or a student without ver-estudiantes does not see the list» |
| EST-R10.1 | `tests/Unit/Students/Application/RegisterStudentTest.php` › «registers a student with a new guardian, recorded inside the transaction»; `tests/Unit/Students/Application/RegisterStudentTest.php` › «edits a student and records only what changed»; `tests/Unit/Students/Application/HealthAndGuardianTest.php` › «corrects a guardian once for every student it represents, recording the old and new values» |
| EST-R10.2 | `tests/Unit/Students/Application/WithdrawalTest.php` › «withdraws a student with its date and reason, recorded»; `tests/Unit/Students/Application/WithdrawalTest.php` › «reinstates a withdrawn student, recorded» |
| EST-R10.3 | `tests/Unit/Students/Application/StudentAuditTest.php` › «records which health data changed, without their values»; `tests/Unit/Students/Application/HealthAndGuardianTest.php` › «changes the health data, recording which ones without their values» |
| EST-R11.1 | `tests/Feature/Students/StudentIsolationTest.php` › «an institution only sees its own students, guardians and their records»; `tests/Feature/Students/StudentManagementTest.php` › «a student of another institution does not exist here, whatever the id» |
| EST-R11.2 | `tests/Feature/Students/StudentManagementTest.php` › «a student of another institution does not exist here, whatever the id» |
| EST-R11.3 | |
