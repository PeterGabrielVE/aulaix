---
spec: 014-superadministracion
prefijo: PLT
estado: aprobada
fecha: 2026-10-26
aprobada: 2026-10-26
backlog: F4-06 (y la operación de IDG-R11)
módulo: Platform (nuevo)
depende-de: 001-tenancy, 004-identidad-global (IDG-R11), 005-auditoria (bitácora de plataforma, AUD-R04.2), ADR nuevo (identidad de plataforma)
---

# Requisitos — Superadministración de plataforma

## Contexto

Hoy AulaX no tiene ninguna pantalla para quien opera la plataforma:

- los casos dudosos de consolidación de cuentas se resuelven con un comando
  de consola, autorizado por una lista de correos en la configuración del
  entorno (IDG-R11);
- activar o desactivar un plantel exige tocar la base de datos;
- nadie puede ver cuántos planteles hay ni cuánto se usa cada uno;
- la bitácora de plataforma se escribe (AUD-R04.2), pero no se puede
  consultar (SPEC-005, dependencia D2).

Esta spec crea el panel de plataforma en `admin.APP_DOMAIN`, con sus
propios operadores, separados por completo de las cuentas de los
planteles. El panel ve los planteles y sus cifras agregadas, pero nunca sus
datos personales ni académicos.

Backlog: F4-06 («lista de tenants, uso, plan y estado»). Se adelanta porque
la operación de soporte lo necesita (ver «014» en `specs/roadmap.md`).

## Alcance

- **Incluye:**
  - las cuentas de operador de plataforma, con contraseña y TOTP
    obligatorio, en su propio dominio y su propia sesión;
  - dos niveles de operador: Soporte y Superadministrador;
  - el alta del primer Superadministrador por consola y la gestión de
    operadores desde el panel;
  - la lista de planteles con su estado y su uso agregado;
  - activar y desactivar planteles;
  - la resolución de consolidaciones dudosas desde el panel, con las reglas
    de IDG-R11;
  - la consulta de la bitácora de plataforma;
  - el registro en la bitácora de toda acción y consulta del panel.
- **Excluye:**
  - **el acceso excepcional a datos de un plantel («break-glass»):** si
    soporte necesita mirar dentro, un Administrador del plantel lo invita
    como miembro con una cuenta normal. Queda para una spec posterior;
  - planes y suscripciones (F4-05): la lista no muestra plan;
  - crear planteles desde el panel o de forma automática (F4-04);
  - borrar planteles o sus datos;
  - métricas de negocio, facturación y tableros (F3, F8).

## Decisiones de producto incorporadas (2026-10-26)

| Pregunta | Decisión |
|----------|----------|
| Cuenta del operador | Aparte de las cuentas de los planteles, con su propio correo y contraseña. Una cuenta de operador no puede ser miembro de un plantel. |
| Segundo factor | TOTP (app de autenticación), obligatorio, con códigos de recuperación de un solo uso. |
| Niveles | Soporte: consulta planteles, uso y bitácora, y resuelve consolidaciones. Superadministrador: además activa y desactiva planteles y gestiona operadores. |
| Break-glass | Fuera de alcance. El panel no ve datos personales ni académicos. |
| Plan | Fuera de alcance (F4-05). |
| Uso | Miembros activos por rol, estudiantes activos, última actividad, espacio de sus archivos y año escolar activo. Solo cifras. |
| Desactivar | Bloqueo sin borrar, con motivo y bitácora, y aviso por correo a los Administradores del plantel. Reversible. |
| Primer Superadministrador | Lo crea un comando de consola. Los demás operadores se invitan desde el panel. |
| Lista de operadores autorizados de IDG-R11.3 | La sustituyen los operadores activos del panel. |
| Invitación de operador | Caduca a las 48 horas. |
| Valores de seguridad | 30 minutos sin actividad cierran la sesión; 5 intentos por minuto; las acciones sensibles exigen un TOTP de los últimos 10 minutos. |
| Códigos de recuperación | 10, que se muestran una sola vez y pueden regenerarse. |

## Glosario

| Término | Significado |
|---------|-------------|
| Panel | La aplicación de `admin.APP_DOMAIN`. |
| Operador | Persona con cuenta de plataforma: Soporte o Superadministrador. |
| TOTP | Código de 6 dígitos que cambia cada 30 s y genera una app de autenticación (RFC 6238). |
| Código de recuperación | Código de un solo uso que sustituye al TOTP si se pierde el dispositivo. |
| Uso del plantel | Cifras agregadas de un plantel, sin ningún dato de una persona. |
| Bitácora de plataforma | La de SPEC-005 para los eventos fuera de un plantel (AUD-R04.2). |

## Requisitos

### PLT-R01 — Acceso al panel

**Historia:** Como operador, quiero entrar al panel con contraseña y un
segundo factor, en un dominio y una sesión que no se mezclen con los de los
planteles.

**Criterios de aceptación (EARS):**

- **PLT-R01.1** — El sistema deberá servir el panel solo en
  `admin.APP_DOMAIN`, con una cookie de sesión propia que no se comparte con
  el dominio central ni con los planteles.
- **PLT-R01.2** — Cuando un operador entra con su correo y su contraseña,
  el sistema deberá pedirle el TOTP, o un código de recuperación, antes de
  abrir el panel.
- **PLT-R01.3** — Si el correo, la contraseña o el código no son correctos,
  entonces el sistema deberá responder con un mensaje único, sin revelar
  cuál falló ni si el correo existe (S-05).
- **PLT-R01.4** — Si un operador está desactivado, entonces el sistema no
  deberá dejarle entrar, aunque sus credenciales sean correctas.
- **PLT-R01.5** — Cuando un operador usa un código de recuperación, el
  sistema deberá invalidarlo y avisarle cuántos le quedan.
- **PLT-R01.10** — Cuando un operador configura su TOTP, el sistema deberá
  mostrarle 10 códigos de recuperación una sola vez, y permitirle
  regenerarlos más tarde, anulando los anteriores.
- **PLT-R01.6** — Mientras un operador no tiene TOTP configurado, el sistema
  deberá llevarlo a configurarlo y no abrir ninguna otra pantalla.
- **PLT-R01.7** — Las credenciales de una cuenta de un plantel no deberán
  abrir el panel, ni las de un operador el dominio central o un plantel.
- **PLT-R01.8** — Cuando una sesión del panel lleva 30 minutos sin
  actividad, el sistema deberá cerrarla.
- **PLT-R01.9** — Cuando un operador cierra la sesión, el sistema deberá
  invalidarla en el servidor.

### PLT-R02 — Primer Superadministrador

**Historia:** Como responsable de la plataforma, quiero crear el primer
Superadministrador sin depender de nadie más.

**Criterios de aceptación (EARS):**

- **PLT-R02.1** — El sistema deberá ofrecer un comando de consola que cree
  un Superadministrador con su nombre y su correo, y le envíe un enlace para
  elegir su contraseña y configurar su TOTP, válido 48 horas.
- **PLT-R02.2** — Si el correo ya pertenece a un operador, entonces el
  comando deberá rechazarlo sin cambios.
- **PLT-R02.3** — El comando deberá registrar el alta en la bitácora de
  plataforma, con origen «consola».

### PLT-R03 — Gestión de operadores

**Historia:** Como Superadministrador, quiero invitar, cambiar de nivel y
desactivar operadores, para que el equipo de soporte tenga el acceso justo.

**Criterios de aceptación (EARS):**

- **PLT-R03.1** — Cuando un Superadministrador invita a una persona como
  Soporte o Superadministrador, el sistema deberá enviarle un enlace de un
  solo uso, válido 48 horas, para elegir su contraseña y configurar su TOTP.
- **PLT-R03.2** — Si el enlace de invitación ya se usó o caducó, entonces el
  sistema deberá rechazarlo.
- **PLT-R03.3** — El sistema deberá permitir al Superadministrador cambiar
  el nivel de un operador y desactivarlo o reactivarlo.
- **PLT-R03.4** — Cuando se desactiva un operador o se le cambia el nivel,
  el sistema deberá cerrar sus sesiones abiertas.
- **PLT-R03.5** — Si la acción dejaría la plataforma sin ningún
  Superadministrador activo, entonces el sistema deberá rechazarla.
- **PLT-R03.6** — Si un Superadministrador intenta desactivarse o quitarse
  el nivel a sí mismo, entonces el sistema deberá rechazarlo.
- **PLT-R03.7** — Cuando un Superadministrador restablece el segundo factor
  de otro operador (dispositivo perdido), el sistema deberá anular su TOTP y
  sus códigos de recuperación, cerrar sus sesiones y pedirle configurarlo de
  nuevo en su próximo acceso.
- **PLT-R03.8** — Si un operador de nivel Soporte intenta gestionar
  operadores, entonces el sistema deberá denegarlo.

### PLT-R04 — Planteles y su uso

**Historia:** Como operador, quiero ver todos los planteles, su estado y
cuánto se usan, sin ver datos de ninguna persona.

**Criterios de aceptación (EARS):**

- **PLT-R04.1** — El sistema deberá listar los planteles con su nombre,
  subdominio, tipo, estado y fecha de alta.
- **PLT-R04.2** — El sistema deberá permitir buscar por nombre o subdominio
  y filtrar por estado.
- **PLT-R04.3** — Cuando un operador abre un plantel, el sistema deberá
  mostrar su uso:
  - miembros activos por rol base;
  - estudiantes activos;
  - fecha de su última actividad;
  - espacio que ocupan sus archivos;
  - año escolar activo, o «sin año activo».
- **PLT-R04.4** — El sistema no deberá mostrar en el panel ningún nombre,
  correo, cédula ni dato académico de los miembros o estudiantes de un
  plantel.

### PLT-R05 — Activar y desactivar planteles

**Historia:** Como Superadministrador, quiero desactivar un plantel (por
falta de pago, fin de contrato o abuso) sin perder sus datos, y reactivarlo
cuando corresponda.

**Criterios de aceptación (EARS):**

- **PLT-R05.1** — Cuando un Superadministrador desactiva un plantel con un
  motivo, el sistema deberá marcarlo inactivo, y desde entonces su
  subdominio responderá 404 (TEN-R03.3).
- **PLT-R05.2** — Si no se indica un motivo, entonces el sistema deberá
  rechazar la desactivación.
- **PLT-R05.3** — Cuando se desactiva o se reactiva un plantel, el sistema
  deberá avisar por correo a sus Administradores activos, con el motivo.
- **PLT-R05.4** — El sistema no deberá borrar ni modificar ningún dato del
  plantel al desactivarlo o reactivarlo.
- **PLT-R05.5** — Cuando un Superadministrador reactiva un plantel
  inactivo, el sistema deberá marcarlo activo y su subdominio volverá a
  responder.
- **PLT-R05.6** — Si un operador de nivel Soporte intenta activar o
  desactivar un plantel, entonces el sistema deberá denegarlo.

### PLT-R06 — Consolidaciones dudosas

**Historia:** Como operador, quiero resolver los casos de identidad dudosos
desde el panel, con las mismas garantías que el comando de consola.

**Criterios de aceptación (EARS):**

- **PLT-R06.1** — El sistema deberá listar los casos marcados para revisión
  con las cuentas implicadas: identificador, nombre, correo y planteles de
  cada una, lo justo para decidir (IDG-R11.1).
- **PLT-R06.2** — Cuando un operador resuelve un caso, el sistema deberá
  exigir la decisión (consolidar o mantener separadas) y un motivo. Si la
  decisión es consolidar, también la referencia de la verificación de
  titularidad (IDG-R11.2, R11.6).
- **PLT-R06.3** — Antes de aplicar la decisión, el sistema deberá mostrar
  los cambios que hará y pedir confirmación (equivalente a `--dry-run`,
  IDG-R11.4).
- **PLT-R06.4** — Si las cuentas ya no están marcadas para revisión o su
  estado cambió, entonces el sistema deberá rechazar la operación sin
  cambios (IDG-R11.5).
- **PLT-R06.5** — El sistema deberá aplicar la resolución con las mismas
  reglas y casos de uso que el comando (IDG-NF07): toda o nada, y en la
  bitácora con el operador de la sesión (IDG-R11.6, R11.7).
- **PLT-R06.6** — Cuando el comando de consola de IDG-R11 identifica al
  operador, el sistema deberá aceptar solo a un operador activo del panel,
  en lugar de la lista de la configuración del entorno (sustituye
  IDG-R11.3).

### PLT-R07 — Bitácora

**Historia:** Como Superadministrador, quiero saber qué hizo y qué consultó
cada operador, y ver los eventos de la identidad global.

**Criterios de aceptación (EARS):**

- **PLT-R07.1** — El sistema deberá registrar en la bitácora de plataforma
  cada acceso al panel (con éxito o fallido), cada acción y cada consulta de
  un plantel o de un caso de consolidación, con el operador, la fecha y la
  IP.
- **PLT-R07.2** — El sistema deberá permitir a los operadores consultar la
  bitácora de plataforma, con filtros por fecha, operador y tipo de evento.
- **PLT-R07.3** — El sistema no deberá guardar en la bitácora contraseñas,
  secretos TOTP, códigos de recuperación ni tokens.
- **PLT-R07.4** — El sistema no deberá mostrar en el panel la bitácora de
  ningún plantel (AUD-R04.1).

## Requisitos no funcionales

- **PLT-NF01** (seguridad) — Ningún acceso del panel a los datos usa un rol
  de base de datos con `BYPASSRLS` ni una función que salte la RLS de forma
  general (S-03). Las cifras de un plantel se leen con la RLS de ese
  plantel, o desde datos que no son de ningún plantel.
- **PLT-NF02** (seguridad) — Contraseñas con hash adaptativo; el secreto
  TOTP, cifrado en reposo; los códigos de recuperación y los tokens de
  invitación, guardados con hash y de un solo uso (S-07, S-08).
- **PLT-NF03** (seguridad) — El inicio de sesión y la verificación del
  código admiten como máximo 5 intentos por minuto por correo e IP (S-06).
  Cada TOTP se acepta una sola vez.
- **PLT-NF04** (seguridad) — Activar o desactivar un plantel y gestionar
  operadores exigen un TOTP nuevo, tecleado en los últimos 10 minutos.
- **PLT-NF05** (seguridad) — Autorización en dos capas: middleware de nivel
  en la ruta y comprobación en el caso de uso (S-04).
- **PLT-NF06** (rendimiento) — La lista de planteles, con 500 planteles,
  carga en menos de 500 ms (p95). El uso de un plantel, en menos de 300 ms.
- **PLT-NF07** (usabilidad) — El panel funciona en 360 px de ancho y cumple
  WCAG 2.1 AA, como el resto (NAV-NF04, NF05).

## Cambios en otras specs

Se aplican en el mismo PR que los implemente (P-04):

- **IDG-R11.3 (SPEC-004):** la lista de operadores autorizados de la
  configuración del entorno (`IDENTITY_CONSOLIDATION_OPERATORS`) deja de
  existir. La sustituye PLT-R06.6.
- **SPEC-005, dependencia D2:** la pantalla de la bitácora de plataforma la
  cubre PLT-R07.2.
- **TEN-R01.1 (SPEC-001):** el estado de un plantel ya puede cambiarse
  desde el panel (PLT-R05).

## Fuera de alcance y preguntas abiertas

Ninguna pregunta abierta: las tres del borrador están resueltas en
[Decisiones de producto
incorporadas](#decisiones-de-producto-incorporadas-2026-10-26).

**Fuera de alcance:** el acceso excepcional a datos de un plantel
(break-glass), planes y suscripciones (F4-05), el alta de planteles (F4-04)
y el borrado de planteles.

## Matriz de trazabilidad

| Criterio | Test(s) |
|----------|---------|
| PLT-R01.1 | `tests/Feature/Platform/OperatorLoginTest.php` › «an operator enters with email, password and TOTP, and only the admin host gets the cookie» |
| PLT-R01.2 | `tests/Unit/Platform/Application/LogInOperatorTest.php` › «opens the session with a valid TOTP, and records it»; `tests/Feature/Platform/Infrastructure/Google2faTotpCodesTest.php` › «accepts the codes of the RFC 6238 test vectors, and tells their time step»; `tests/Feature/Platform/OperatorLoginTest.php` › «an operator enters with email, password and TOTP, and only the admin host gets the cookie» |
| PLT-R01.3 | `tests/Unit/Platform/Application/LogInOperatorTest.php` › «gives one answer, one event and no clue for every failure (V5)»; `tests/Feature/Platform/OperatorLoginTest.php` › «every failure gives the same answer and the same entry (V5)» |
| PLT-R01.4 | `tests/Unit/Platform/Domain/OperatorTest.php` › «may not enter once deactivated, and enters again once reactivated»; `tests/Feature/Platform/OperatorLoginTest.php` › «a session started before a deactivation ends on its next request» |
| PLT-R01.5 | `tests/Unit/Platform/Domain/RecoveryCodesTest.php` › «uses a code once, and says how many are left»; `tests/Unit/Platform/Application/LogInOperatorTest.php` › «accepts a recovery code once, and says how many are left»; `tests/Feature/Platform/OperatorLoginTest.php` › «a recovery code opens the panel once» |
| PLT-R01.6 | `tests/Unit/Platform/Domain/OperatorTest.php` › «starts pending, without password nor TOTP, and may not enter»; `tests/Unit/Platform/Application/OperatorInvitationTest.php` › «activates the operator with its password and a confirmed TOTP, and gives ten recovery codes once» |
| PLT-R01.7 | `tests/Feature/Platform/OperatorLoginTest.php` › «the panel needs an operator session, and an account of an institution is none»; `tests/Feature/Platform/OperatorLoginTest.php` › «an operator email does not open the central login» |
| PLT-R01.8 | `tests/Feature/Platform/OperatorLoginTest.php` › «the session ends after 30 minutes without activity» |
| PLT-R01.9 | `tests/Unit/Platform/Application/LogInOperatorTest.php` › «ends the session on logout, and records it»; `tests/Feature/Platform/OperatorLoginTest.php` › «logging out ends the session on the server» |
| PLT-R01.10 | `tests/Unit/Platform/Domain/RecoveryCodesTest.php` › «keeps ten codes, only as hashes»; `tests/Unit/Platform/Application/OperatorInvitationTest.php` › «activates the operator with its password and a confirmed TOTP, and gives ten recovery codes once»; `tests/Feature/Platform/OperatorInvitationTest.php` › «the link sets the password and the TOTP, then shows the ten recovery codes once»; `tests/Unit/Platform/Application/OperatorManagementTest.php` › «regenerates the own recovery codes, voiding the old ones (PLT-R01.10)»; `tests/Feature/Platform/OperatorMfaResetTest.php` › «an operator regenerates its own recovery codes, which voids the old ones» |
| PLT-R02.1 | `tests/Feature/Platform/PlatformBootstrapCommandTest.php` › «creates a pending SuperAdmin, sends its link and records it with console as origin» |
| PLT-R02.2 | `tests/Feature/Platform/PlatformBootstrapCommandTest.php` › «refuses an email that already belongs to an operator» |
| PLT-R02.3 | `tests/Feature/Platform/PlatformBootstrapCommandTest.php` › «creates a pending SuperAdmin, sends its link and records it with console as origin» |
| PLT-R03.1 | `tests/Unit/Platform/Application/OperatorInvitationTest.php` › «invites a pending operator by email, keeping only the hash of the token, and records it»; `tests/Unit/Platform/Application/OperatorInvitationTest.php` › «lets only an active SuperAdmin invite from the panel»; `tests/Feature/Platform/OperatorManagementTest.php` › «a SuperAdmin lists the operators and invites one» |
| PLT-R03.2 | `tests/Unit/Platform/Domain/OperatorInvitationTest.php` › «lasts 48 hours, not a second more»; `tests/Unit/Platform/Domain/OperatorInvitationTest.php` › «is used once»; `tests/Feature/Platform/Infrastructure/OperatorPersistenceTest.php` › «a link is consumed once, even by two racing requests»; `tests/Feature/Platform/OperatorInvitationTest.php` › «a link lasts 48 hours, and not a minute more»; `tests/Feature/Platform/OperatorInvitationTest.php` › «a link is used once, even by two requests»; `tests/Feature/Platform/OperatorInvitationTest.php` › «a newer link revokes the older one»; `tests/Feature/Platform/OperatorInvitationTest.php` › «expired, used, revoked and unknown links answer alike» |
| PLT-R03.3 | `tests/Unit/Platform/Application/OperatorManagementTest.php` › «changes the role of an operator, closing its sessions, and records it»; `tests/Unit/Platform/Application/OperatorManagementTest.php` › «deactivates an operator, closing its sessions and revoking its pending links (V4)»; `tests/Feature/Platform/OperatorManagementTest.php` › «a role change takes effect and closes that operator sessions» |
| PLT-R03.4 | `tests/Unit/Platform/Application/OperatorManagementTest.php` › «changes the role of an operator, closing its sessions, and records it»; `tests/Feature/Platform/OperatorManagementTest.php` › «a role change takes effect and closes that operator sessions» |
| PLT-R03.5 | `tests/Unit/Platform/Domain/SuperAdminRulesTest.php` › «refuses to leave the platform without an active SuperAdmin»; `tests/Unit/Platform/Application/OperatorManagementTest.php` › «never leaves the platform without an active SuperAdmin, nor lets one act on itself»; `tests/Feature/Platform/OperatorManagementTest.php` › «the last active SuperAdmin can not be demoted, nor demote itself» |
| PLT-R03.6 | `tests/Unit/Platform/Domain/SuperAdminRulesTest.php` › «refuses that an operator demotes or deactivates itself»; `tests/Unit/Platform/Application/OperatorManagementTest.php` › «never leaves the platform without an active SuperAdmin, nor lets one act on itself» |
| PLT-R03.7 | `tests/Unit/Platform/Application/OperatorManagementTest.php` › «resets the second factor of another operator and sends the only way back (PLT-R03.7, V4)»; `tests/Feature/Platform/OperatorMfaResetTest.php` › «the reset ends the operator sessions and leaves the link as its only way in» |
| PLT-R03.8 | `tests/Unit/Platform/Application/OperatorManagementTest.php` › «lets no Support manage operators (PLT-R03.8)»; `tests/Feature/Platform/OperatorManagementTest.php` › «a Support gets 403 on every operator route» |
| PLT-R04.1 | |
| PLT-R04.2 | |
| PLT-R04.3 | |
| PLT-R04.4 | |
| PLT-R05.1 | |
| PLT-R05.2 | |
| PLT-R05.3 | |
| PLT-R05.4 | |
| PLT-R05.5 | |
| PLT-R05.6 | |
| PLT-R06.1 | |
| PLT-R06.2 | |
| PLT-R06.3 | |
| PLT-R06.4 | |
| PLT-R06.5 | |
| PLT-R06.6 | |
| PLT-R07.1 | `tests/Unit/Platform/Application/LogInOperatorTest.php` › «opens the session with a valid TOTP, and records it»; `tests/Feature/Audit/Infrastructure/PlatformOriginTest.php` › «records an operator action in the platform log, with its origin and operator» |
| PLT-R07.2 | |
| PLT-R07.3 | `tests/Unit/Platform/Application/LogInOperatorTest.php` › «gives one answer, one event and no clue for every failure (V5)»; `tests/Feature/Platform/PlatformBootstrapCommandTest.php` › «creates a pending SuperAdmin, sends its link and records it with console as origin» |
| PLT-R07.4 | |
| PLT-NF01 | `tests/Feature/Platform/Infrastructure/OperatorTablesTest.php` › «an active institution sees no operator and writes none» |
| PLT-NF02 | `tests/Feature/Platform/Infrastructure/OperatorPersistenceTest.php` › «saves and loads an operator, with its TOTP secret encrypted at rest»; `tests/Feature/Platform/Infrastructure/OperatorPersistenceTest.php` › «keeps only the hashes of the recovery codes, and their use»; `tests/Feature/Platform/Infrastructure/OperatorPersistenceTest.php` › «a new link revokes the pending ones of the same operator, and keeps only the token hash» |
| PLT-NF03 | `tests/Unit/Platform/Domain/OperatorTest.php` › «accepts each TOTP step once, and none older than the last one used»; `tests/Feature/Platform/OperatorLoginTest.php` › «the sixth attempt in a minute is refused, whatever failed» |
| PLT-NF04 | `tests/Feature/Platform/RecentTotpTest.php` › «right after login, a sensitive action goes through»; `tests/Feature/Platform/RecentTotpTest.php` › «after ten minutes it asks for a code, then lets the action through»; `tests/Feature/Platform/RecentTotpTest.php` › «a code used at login can not confirm again» |
| PLT-NF05 | `tests/Unit/Platform/Application/OperatorManagementTest.php` › «lets no Support manage operators (PLT-R03.8)»; `tests/Feature/Platform/OperatorManagementTest.php` › «a Support gets 403 on every operator route» |
| PLT-NF06 | |
| PLT-NF07 | |
