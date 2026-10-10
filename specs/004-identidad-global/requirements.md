---
spec: 004-identidad-global
prefijo: IDG
estado: aprobada
versión: 3
fecha: 2026-10-10
aprobada: 2026-10-10
backlog: F1-07 (y revisión de F1-06)
adr: 0004
depende-de: 001-tenancy, 005-auditoria
---

# Requisitos — Identidad global, membresías y acceso centralizado

## Contexto

Una persona puede trabajar, estudiar o tener representados en varios
planteles. Hoy necesita una cuenta y una contraseña por colegio. F1-07 pide
que tenga una sola identidad y elija qué plantel abrir.

Principio de producto: **la identidad pertenece a la persona; la relación
con cada plantel la administra el plantel.** Una acción administrativa en un
colegio nunca altera la identidad de la persona ni su acceso a los demás.

Las decisiones de producto que fijan esta versión son las del documento
«Decisiones de producto para la SPEC-004» (2026-10-10), resumidas en
[Decisiones incorporadas](#decisiones-incorporadas).

## Alcance

- **Incluye:** cuenta global, membresías, invitaciones, autenticación
  centralizada, selector de plantel, gestión de la identidad por su titular,
  salir de un plantel, eliminar la cuenta, consolidación y migración de las
  cuentas actuales.
- **Excluye:** SSO con proveedores externos, solicitud de acceso a un plantel
  (no está en el backlog), alta de instituciones (F4-04) y panel de
  superadministración (F4-06).

## Glosario

| Término | Significado |
|---------|-------------|
| Cuenta / identidad | Identidad global de una persona: email normalizado único, contraseña y nombre. |
| Titular | La persona dueña de una cuenta. |
| Membresía | Relación cuenta ↔ plantel, con estado, motivo de suspensión y roles (`institution_memberships`). |
| Estado de la cuenta | `active`, `pending_deletion` (período de gracia), `closed` (acceso revocado de forma permanente y datos intactos a la espera de las reglas legales), `deleted` (datos tratados según la tabla de conservación; bloqueado por D1). |
| Motivo de suspensión | Por qué está suspendida una membresía: `institution` (decisión del plantel), `security` o `account_deletion` (eliminación pendiente). |
| Invitación | Propuesta pendiente de incorporar a un email a un plantel con unos roles (`invitations`). |
| Desvinculación | Fin de una membresía: la persona deja de pertenecer al plantel. |
| Suspensión | Bloqueo, temporal o no, del acceso a un plantel, sin terminar la membresía. |
| Eliminación de cuenta | Proceso global de supresión de la identidad y de sus datos eliminables. |
| Consolidación | Unión, verificada por el titular, de varias cuentas históricas con el mismo email. |
| Email normalizado | Email sin espacios alrededor y en minúsculas. Sin reglas por proveedor (p. ej., no se quitan puntos en Gmail). |

## Decisiones incorporadas

| Pregunta | Decisión |
|----------|----------|
| Q1 Email duplicado entre planteles | Identidad única. La consolidación se hace solo tras verificar la titularidad; si hay indicios de que son personas distintas, no se fusiona automáticamente. |
| Q2 Edición de nombre y email | Los gestiona el titular. El gestor administra membresías e invitaciones pendientes. |
| Q3 «Eliminar mi cuenta» | Dos acciones distintas: salir de un plantel y eliminar la cuenta global. Los registros académicos se conservan. |
| Q4 Dominio central | Punto único de autenticación y selector posterior al login. El buscador se conserva como directorio. |
| Q5 Conservación legal | La spec se aprueba sin plazos legales. La **eliminación efectiva queda bloqueada** hasta tener criterios validados por asesoría legal (dependencia D1). Se construye todo lo que no prejuzga esa decisión. |
| Q6 Consolidaciones dudosas | Comando de consola restringido y auditado, con modo de simulación, operado por soporte. El futuro panel de superadministración reutilizará las mismas reglas. |
| Q7 Plazo de gracia | 30 días naturales en estado `pending_deletion`, cancelables. Es una decisión de producto, no un plazo legal. |
| Q8 Terminología | «Plantel» en toda la interfaz. «Institución educativa» solo en textos formales. Los nombres técnicos siguen la convención del proyecto (`institution`). |

## Criterios de SPEC-002 que sustituye

| Criterio anterior | Lo sustituye |
|-------------------|--------------|
| IAM-R02.1 – R02.5 (login en el subdominio) | IDG-R03 |
| IAM-R02.6, R02.7, R03.1, R04.6 (estado de la cuenta) | El estado pasa a la membresía: IDG-R02, IDG-R03.4, IDG-R03.8 |
| IAM-R04 (invitación) | IDG-R04 |
| IAM-R05 (recuperación por plantel) | IDG-R06 |
| IAM-R08.2 (editar email desde el perfil) | IDG-R05 |
| IAM-R08.4, R08.5 (eliminar cuenta) | IDG-R07, IDG-R08 |
| IAM-R09.5 – R09.7, R09.11 – R09.13 (alta, unicidad, edición, reenvío) | IDG-R04, IDG-R05.5 |

El resto de SPEC-002 (reglas de roles IAM-R10, roles base IAM-R11, pantalla
por rol IAM-R12) se mantiene, aplicado a la membresía del plantel abierto.

## Requisitos

### IDG-R01 — Una persona, una identidad

- **IDG-R01.1** — El sistema deberá identificar a cada persona con una única
  cuenta, cuyo email normalizado sea único en todo el sistema.
- **IDG-R01.2** — El sistema deberá normalizar todo email antes de guardarlo
  y antes de buscar duplicados.
- **IDG-R01.3** — El sistema deberá permitir que una cuenta tenga membresías
  en varios planteles, con roles distintos en cada uno.

### IDG-R02 — Membresías

- **IDG-R02.1** — El sistema deberá conceder a una cuenta, en un plantel,
  solo los roles y permisos de su membresía activa en ese plantel.
- **IDG-R02.2** — Cuando un gestor suspende la membresía de una persona, el
  sistema deberá bloquear su acceso a ese plantel sin afectar a su cuenta ni a
  sus otras membresías.
- **IDG-R02.3** — Cuando un gestor reactiva una membresía suspendida, el
  sistema deberá devolverle el acceso con los roles que tenía.
- **IDG-R02.4** — Cuando una membresía termina (desvinculación), el sistema
  deberá conservar los registros académicos, administrativos y de auditoría
  asociados a esa persona en el plantel.

### IDG-R03 — Autenticación centralizada y selección de plantel

**Historia:** Como docente de dos colegios, quiero entrar una sola vez y
elegir en cuál trabajar.

- **IDG-R03.1** — El sistema deberá ofrecer el inicio de sesión, la
  recuperación de contraseña y la gestión de la cuenta únicamente en el
  dominio central.
- **IDG-R03.2** — Cuando se abre la página de login del subdominio de un
  plantel, el sistema deberá redirigir al login central y, tras autenticarse,
  abrir ese plantel si la persona tiene membresía activa en él.
- **IDG-R03.3** — Si las credenciales no son válidas, entonces el sistema
  deberá responder con el mensaje genérico, sin revelar si el email tiene
  cuenta.
- **IDG-R03.4** — Cuando una persona con exactamente una membresía activa se
  autentica, el sistema deberá abrir directamente ese plantel.
- **IDG-R03.5** — Cuando una persona con varias membresías activas se
  autentica, el sistema deberá mostrar un selector solo con esos planteles.
- **IDG-R03.6** — Cuando una persona sin membresías activas se autentica, el
  sistema deberá mostrar una pantalla de bienvenida con sus invitaciones
  pendientes y el directorio de colegios.
- **IDG-R03.7** — Cuando una persona con varias membresías activas elige
  otro plantel desde el menú, el sistema deberá abrirlo sin pedir de nuevo la
  contraseña.
- **IDG-R03.8** — El sistema deberá verificar en **cada petición** que la
  persona tiene membresía activa en el plantel abierto. Si no la tiene,
  entonces deberá denegar el acceso a ese plantel y mantener su sesión en los
  demás.
- **IDG-R03.9** — Si se elige o se escribe en la URL un plantel sin membresía
  activa, entonces el sistema deberá denegarlo, con la misma respuesta que
  para un plantel inexistente.
- **IDG-R03.10** — El sistema deberá bloquear temporalmente los intentos
  fallidos tras 5 por email e IP.

### IDG-R04 — Invitaciones

- **IDG-R04.1** — Cuando un gestor invita a un email con nombre y roles, el
  sistema deberá registrar una invitación pendiente al plantel, válida durante
  7 días, y enviarla por correo.
- **IDG-R04.2** — El sistema deberá mostrar al gestor la misma respuesta
  exista o no una cuenta con ese email, sin revelar si pertenece a otros
  planteles.
- **IDG-R04.3** — Si el email ya tiene una membresía en el plantel, entonces el
  sistema deberá rechazar la invitación.
- **IDG-R04.4** — Cuando una persona sin cuenta abre una invitación válida, el
  sistema deberá permitirle crear su cuenta (nombre de la invitación,
  contraseña nueva, email verificado por el propio enlace) y crear la
  membresía con los roles de la invitación.
- **IDG-R04.5** — Cuando una persona con cuenta abre una invitación válida, el
  sistema deberá pedirle que se autentique y, al aceptar, crear la membresía,
  sin cambiar su nombre ni su contraseña.
- **IDG-R04.6** — Si la cuenta autenticada no tiene el email de la
  invitación, entonces el sistema deberá rechazar la aceptación.
- **IDG-R04.7** — Cuando un gestor corrige el nombre o el email de una
  invitación pendiente, el sistema deberá invalidar el enlace anterior y
  enviar uno nuevo.
- **IDG-R04.8** — Cuando un gestor reenvía una invitación pendiente, el
  sistema deberá invalidar el enlace anterior y enviar uno nuevo.
- **IDG-R04.9** — Si una invitación está aceptada, caducada o anulada,
  entonces el sistema deberá rechazar su uso.
- **IDG-R04.10** — El sistema deberá permitir al gestor anular una
  invitación pendiente.

### IDG-R05 — Identidad bajo control del titular

- **IDG-R05.1** — El sistema deberá permitir al titular cambiar su nombre
  desde su cuenta.
- **IDG-R05.2** — Cuando el titular pide cambiar su email, el sistema deberá
  exigir reautenticación y enviar un enlace de verificación al **nuevo**
  email, manteniendo el actual hasta que se verifique.
- **IDG-R05.3** — Cuando se verifica el nuevo email, el sistema deberá
  cambiarlo y avisar al email **anterior**.
- **IDG-R05.4** — Si el nuevo email ya pertenece a otra cuenta, entonces el
  sistema deberá rechazarlo sin revelar el motivo exacto a terceros.
- **IDG-R05.5** — El sistema no deberá permitir a un gestor cambiar el nombre
  ni el email de una cuenta. Solo podrá corregirlos en invitaciones
  pendientes (IDG-R04.7).
- **IDG-R05.6** — El sistema no deberá mostrar a un gestor las membresías que
  una persona tiene en otros planteles.

### IDG-R06 — Contraseña global

- **IDG-R06.1** — Cuando el titular restablece o cambia su contraseña, el
  sistema deberá aplicarla a toda la cuenta y avisarle por correo.
- **IDG-R06.2** — Cuando se restablece o cambia la contraseña, el sistema
  deberá cerrar todas las demás sesiones de la cuenta.
- **IDG-R06.3** — El sistema deberá mantener los criterios de recuperación
  de SPEC-002 (enlace de 60 minutos y de un solo uso, misma respuesta exista
  o no la cuenta, límites de envío), ahora desde el dominio central.

### IDG-R07 — Salir de un plantel

- **IDG-R07.1** — Cuando una persona sale de un plantel, el sistema deberá
  terminar esa membresía y conservar su cuenta y sus demás membresías.
- **IDG-R07.2** — Cuando una membresía termina o se suspende, el sistema
  deberá revocar los permisos y las sesiones abiertas en ese plantel.
- **IDG-R07.3** — Si la persona es el único Administrador activo del plantel,
  entonces el sistema deberá impedir que salga hasta que otra persona tenga
  ese rol. Esto resuelve IAM-H01 para el plantel.
- **IDG-R07.4** — El sistema deberá ofrecer «Salir de este plantel» y
  «Eliminar mi cuenta» como acciones separadas, en lugares distintos.

### IDG-R08 — Eliminar la cuenta global

**Solicitud**

- **IDG-R08.1** — Cuando el titular inicia la eliminación de su cuenta, el
  sistema deberá exigir reautenticación, mostrar las consecuencias («afectará
  tu acceso a todos los planteles asociados») y pedir una confirmación
  explícita.
- **IDG-R08.2** — Si la persona es el único Administrador activo de algún
  plantel, entonces el sistema deberá impedir la eliminación hasta que
  transfiera ese rol.
- **IDG-R08.3** — Donde existan estudiantes representados (SPEC-009), si la
  persona es el único representante legal de algún estudiante, entonces el
  sistema deberá impedir la eliminación hasta que se resuelva esa relación.
- **IDG-R08.4** — Donde existan asignaciones docentes (SPEC-008), si la
  persona es la única responsable de una asignación activa, entonces el
  sistema deberá impedir la eliminación hasta que se transfiera.
**Período de gracia (`pending_deletion`, 30 días naturales)**

- **IDG-R08.5** — Cuando el titular confirma la solicitud, el sistema deberá
  poner la cuenta en `pending_deletion`, registrar la fecha de inicio y la
  fecha prevista (inicio + 30 días naturales), e informarle de ambas por
  correo, junto con cómo cancelar.
- **IDG-R08.6** — Cuando la cuenta entra en `pending_deletion`, el sistema
  deberá cerrar todas sus sesiones, invalidar sus tokens pendientes
  (recuperación, verificación, traspaso) y suspender sus membresías activas
  con motivo `account_deletion`.
- **IDG-R08.7** — Mientras la cuenta está en `pending_deletion`, si el titular
  se autentica (incluida la recuperación de contraseña), entonces el sistema
  deberá mostrarle solo la fecha prevista y la opción de cancelar, sin abrir
  ningún plantel.
- **IDG-R08.8** — Mientras la cuenta está en `pending_deletion`, el sistema
  deberá rechazar la aceptación de invitaciones dirigidas a su email. Las
  invitaciones que la persona emitió como gestor seguirán vigentes: pertenecen
  al plantel.
- **IDG-R08.9** — Cuando el titular cancela la solicitud dentro del plazo, el
  sistema deberá devolver la cuenta a `active` y reactivar **solo** las
  membresías suspendidas con motivo `account_deletion`. Las suspendidas por
  el plantel o por seguridad no se reactivan.
- **IDG-R08.10** — El sistema deberá volver a comprobar los bloqueos de
  IDG-R08.2 – R08.4 antes de ejecutar la eliminación al final del plazo. Si
  alguno se cumple, la ejecución se aplaza y se avisa al titular y al soporte.

**Ejecución al final del plazo**

- **IDG-R08.11** — Cuando vence el plazo y no hay bloqueos, el sistema deberá
  pasar la cuenta a `closed`: invalidar sus credenciales de forma permanente y
  terminar todas sus membresías, sin borrar ni anonimizar datos.
- **IDG-R08.12** — *(Bloqueado por D1.)* Cuando existan reglas de
  conservación aprobadas, el sistema deberá tratar los datos de las cuentas
  `closed` por categorías (eliminar, anonimizar o conservar) y pasarlas a
  `deleted`.
- **IDG-R08.13** — El sistema no deberá eliminar de forma irreversible
  expedientes, calificaciones, actas ni registros históricos mientras D1 no
  defina su tratamiento.
- **IDG-R08.14** — El sistema deberá registrar cada solicitud de eliminación y
  cada transición de estado (solicitada, cancelada, aplazada, cerrada,
  eliminada) en la bitácora (IDG-R10).

### IDG-R09 — Migración y consolidación de cuentas históricas

- **IDG-R09.1** — Cuando se migran las cuentas actuales, el sistema deberá
  convertir cada email normalizado con una sola cuenta en una identidad
  global, conservando su contraseña, su verificación y su membresía (estado y
  roles).
- **IDG-R09.2** — Cuando un email normalizado tiene cuentas en varios
  planteles, el sistema deberá crear una identidad **pendiente de
  consolidación**, sin fusionar credenciales.
- **IDG-R09.3** — Mientras una identidad está pendiente de consolidación, el
  sistema deberá aceptar cada contraseña histórica **solo** para el plantel al
  que pertenecía, y ofrecer la consolidación tras el login.
- **IDG-R09.4** — Cuando el titular demuestra que controla el email (enlace o
  código de un solo uso), el sistema deberá permitirle fijar una contraseña
  única, asociar a la identidad las membresías verificadas, revocar las
  credenciales históricas y avisarle por correo.
- **IDG-R09.5** — Si hay indicios de que las cuentas pertenecen a personas
  distintas (los nombres normalizados no coinciden), entonces el sistema no
  deberá consolidarlas automáticamente: deberá marcar el caso para revisión
  (IDG-R11) y mantener IDG-R09.3 mientras tanto.
- **IDG-R09.6** — Cuando se migran las cuentas que aún no aceptaron su
  invitación, el sistema deberá convertirlas en invitaciones pendientes con su
  plazo original.
- **IDG-R09.7** — El sistema nunca deberá copiar, transferir ni mostrar una
  contraseña o su hash durante la migración o la consolidación.

### IDG-R10 — Auditoría

- **IDG-R10.1** — El sistema deberá registrar en la bitácora (SPEC-005):
  - los cambios de identidad (nombre, email, contraseña);
  - las altas, suspensiones y fines de membresía;
  - los cambios de roles;
  - las invitaciones;
  - las consolidaciones;
  - las solicitudes y ejecuciones de eliminación.
- **IDG-R10.2** — El sistema deberá restringir la consulta de esos registros
  a los roles autorizados y no registrar datos personales más allá de los
  imprescindibles: IDs, no valores de contraseña ni tokens.

### IDG-R11 — Revisión manual de consolidaciones dudosas

**Historia:** Como soporte de la plataforma, quiero resolver los casos de
identidad dudosos con una herramienta segura, sin tocar la base de datos a
mano.

- **IDG-R11.1** — El sistema deberá ofrecer un comando de consola que liste
  los casos marcados para revisión y otro que resuelva un caso concreto.
- **IDG-R11.2** — El comando de resolución deberá exigir la identificación
  del operador, los identificadores de las cuentas implicadas, la decisión
  (consolidar, mantener separadas) y un motivo.
- **IDG-R11.3** — Si el operador no está en la lista de operadores
  autorizados de la configuración del entorno, entonces el comando deberá
  negarse a ejecutar.
- **IDG-R11.4** — Cuando se ejecuta en modo simulación (`--dry-run`), el
  comando deberá mostrar los cambios que haría sin aplicar ninguno.
- **IDG-R11.5** — Si las cuentas indicadas no están marcadas para revisión, o
  si su estado cambió desde que se marcaron, entonces el comando deberá
  rechazar la operación sin cambios.
- **IDG-R11.6** — Si la decisión es consolidar, el comando deberá exigir
  constancia de la verificación de titularidad (referencia del procedimiento
  de soporte) y aplicar la consolidación en una sola transacción: o se aplica
  completa, o no se aplica nada.
- **IDG-R11.7** — El comando deberá registrar en la bitácora el operador, la
  fecha, la decisión, el motivo, el resultado y los identificadores afectados,
  sin contraseñas, tokens ni datos personales innecesarios.
- **IDG-R11.8** — El sistema nunca deberá consolidar un caso marcado para
  revisión por ninguna vía automática.

## Requisitos no funcionales

- **IDG-NF01** (seguridad) — Los datos personales de una cuenta solo son
  visibles para los planteles donde tiene membresía, y lo garantiza la base de
  datos (ADR 0004).
- **IDG-NF02** (privacidad) — Ninguna respuesta revela a un plantel en qué
  otros planteles tiene membresía una persona, ni si un email tiene cuenta.
- **IDG-NF03** (seguridad) — El cambio de plantel sin contraseña (IDG-R03.7)
  usa un token de un solo uso, firmado, ligado a la cuenta y al plantel de
  destino, con una vida de 60 segundos como máximo.
- **IDG-NF04** (seguridad) — Contraseñas con hash adaptativo; tokens de
  invitación, recuperación, verificación y consolidación hasheados, de un solo
  uso y con caducidad.
- **IDG-NF05** (seguridad) — Las operaciones sensibles (cambio de email,
  eliminación, consolidación) exigen una reautenticación de hace menos de 10
  minutos.
- **IDG-NF06** (cumplimiento) — Verificación según OWASP ASVS 4.0 nivel 2,
  capítulos V2 (autenticación) y V3 (sesiones).
- **IDG-NF07** (mantenibilidad) — Las reglas de consolidación y de
  eliminación viven en casos de uso de `IdentityAccess\Application`. El
  comando de consola de hoy y el panel de superadministración de mañana
  (F4-06) son solo adaptadores de entrada de esos mismos casos de uso.
- **IDG-NF08** (UX) — La interfaz usa «plantel» de forma consistente en la
  navegación, el selector, las membresías, los correos y los mensajes de error,
  incluidas las pantallas existentes que hoy dicen «institución» (p. ej.
  «Selecciona tu plantel», «Cambiar de plantel», «¿Deseas dejar de pertenecer
  a este plantel?»). La terminología se valida con usuarios en F0-04.

## Dependencias

| ID | Dependencia | Bloquea | No bloquea |
|----|-------------|---------|------------|
| **D1** | Criterios de conservación validados por asesoría legal (LOPNNA, normativa del MPPE sobre expedientes, protección de datos): categorías de datos, y para cada una si se elimina, se anonimiza o se conserva, y durante cuánto tiempo. | IDG-R08.12 (paso a `deleted`) | La aprobación de esta spec y todo IDG-R08.1 – R08.11, R08.13 y R08.14: estados, solicitud, gracia, cancelación, cierre y registro. |
| **D2** | SPEC-005 (bitácora de auditoría) implementada. | IDG-R10, IDG-R11.7, IDG-R08.14 | — |
| **D3** | SPEC-008 y SPEC-009 implementadas. | Los bloqueos IDG-R08.3 y R08.4 se activan cuando existan esos datos. | — |

## Preguntas abiertas

Ninguna. Q1 a Q8 están resueltas en [Decisiones incorporadas](#decisiones-incorporadas).

## Matriz de trazabilidad

| Criterio | Test(s) |
|----------|---------|
| IDG-R01.1 – IDG-R11.8 | Pendientes (spec en borrador). |
