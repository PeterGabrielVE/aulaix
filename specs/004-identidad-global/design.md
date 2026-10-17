---
spec: 004-identidad-global
estado: aprobada
fecha: 2026-10-15
requisitos: requirements.md
depende-de: 001-tenancy, 005-auditoria
adr: 0004, 0006
incluye: refactor de SPEC-002 (identidad y acceso)
---

# Diseño — Identidad global, membresías y acceso centralizado

## Resumen

Se crea el módulo `IdentityAccess`, que absorbe el refactor de SPEC-002:

- **`Account`**: la identidad global, en la tabla `users`.
- **`Membership`**: la relación cuenta ↔ plantel, con su estado y sus roles.
- **`Invitation`**: la invitación pendiente de un plantel.

Las reglas de roles (`RoleAssignmentPolicy`, `BaseRole`, `HomeScreen`) son
las del diseño de SPEC-002, aplicadas a la membresía.

El login se centraliza en el dominio central. Los subdominios reciben la
sesión con un token de traspaso y comprueban la membresía activa en cada
petición. El aislamiento de `users` sigue el ADR 0006. Todo cambio de
identidad, membresía, rol o invitación se audita con `AuditTrail`
(SPEC-005).

**Se entrega en tres PRs**, con la suite en verde en cada uno:

| PR | Contenido | Criterios |
|----|-----------|-----------|
| **A. Modelo y datos** | Tablas, RLS, migración de cuentas, acceso por membresía en los subdominios, gestión de miembros e invitaciones por el gestor, refactor de SPEC-002 | IDG-R01, R02, R03.8 – R03.10, R04, R05.5, R05.6, R09.1, R09.2, R09.6, R09.7, R10 (parcial), NF01, NF02, NF04 |
| **B. Autenticación central** | Login y recuperación centrales, selector, traspaso, consolidación de cuentas, comandos de soporte | IDG-R03.1 – R03.7, R06, R09.3 – R09.5, R11, NF03, NF05 (reautenticación) |
| **C. Cuenta del titular** | Cambio de nombre y email, salir de un plantel, eliminación con gracia, tarea programada | IDG-R05.1 – R05.4, R07, R08, NF05, NF08 |

## Estructura

```
src/IdentityAccess/
├── Domain/
│   ├── Account/         Account (agregado) · AccountId · EmailAddress (normalizada) · PersonName
│   │                    HashedPassword · AccountState (active | pending_deletion | closed | deleted)
│   │                    ConsolidationState (none | pending | review) · AccountRepository
│   ├── Membership/      Membership (agregado) · MembershipStatus (active | suspended | ended)
│   │                    SuspensionReason (institution | security | account_deletion) · MembershipRepository
│   ├── Invitation/      Invitation (agregado) · InvitationToken · InvitationStatus · InvitationRepository
│   ├── Role/            BaseRole · Permission · RoleName · RoleSet · HomeScreen · RoleCatalog   (de SPEC-002)
│   └── Policy/          RoleAssignmentPolicy (SPEC-002) · DeletionBlockers · LastAdministratorRule
├── Application/
│   ├── Port/            Authenticator · PasswordHasher · LoginThrottle · HandoffTokens · SessionRevoker
│   │                    Mailer (invitaciones, avisos, verificación) · ReauthenticationGate
│   ├── Members/         InviteMember · CorrectInvitation · ResendInvitation · CancelInvitation
│   │                    ChangeMemberRoles · SuspendMember · ReactivateMember · ListMembers (query)
│   ├── Auth/            LogIn · ChoosePlantel · IssueHandoff · ConsumeHandoff · LogOut
│   │                    RequestPasswordReset · ResetPassword · AcceptInvitation
│   ├── Consolidation/   StartConsolidation · CompleteConsolidation · ListConsolidationCases · ResolveConsolidationCase
│   ├── Account/         ChangeName · RequestEmailChange · ConfirmEmailChange · ChangePassword
│   │                    LeavePlantel · RequestDeletion · CancelDeletion · CloseDueAccounts
│   └── Home/            ResolveHomeScreen (query)
└── Infrastructure/
    ├── Persistence/     AccountModel (Authenticatable, HasRoles) · MembershipModel · InvitationModel
    │                    mappers y repositorios Eloquent · SpatieRoleCatalog
    ├── Auth/            LaravelAuthenticator · CacheHandoffTokens · DatabaseSessionRevoker · RateLimiterLoginThrottle
    ├── Notifications/   InvitationMail · PasswordResetMail · EmailChangeVerification · EmailChangedNotice ·
    │                    PasswordChangedNotice · DeletionScheduledNotice · ConsolidationCode
    ├── SpatiePermissionTeamHook (movido desde app/Providers)
    └── IdentityAccessServiceProvider
```

## Modelo de dominio

| Elemento | Invariantes |
|----------|-------------|
| `EmailAddress` | Normalizada: `trim` + minúsculas, sin reglas por proveedor (IDG-R01.2). |
| `Account` | Nombre ≠ vacío. `changeName()`, `requestEmailChange(nuevo)` (deja `pending_email`), `confirmEmailChange()`, `changePassword()`, `requestDeletion(clock)` (fecha prevista = +30 días naturales), `cancelDeletion()`, `close()`. No se puede pedir la eliminación si no está `active`, ni cancelarla si no está en `pending_deletion`. Registra eventos de plataforma. |
| `Membership` | Una por cuenta y plantel (único `(institution_id, user_id)`). `suspend(reason)`, `reactivate(onlyIfReason?)`, `end()`. `reactivate(account_deletion)` solo reactiva las suspendidas por ese motivo (IDG-R08.9). Una membresía terminada no se reactiva. |
| `Invitation` | `pending → accepted / cancelled`, y caduca a los 7 días. `correct(nombre, email)` y `resend()` generan un token nuevo e invalidan el anterior (IDG-R04.7, R04.8). Solo se acepta si está `pending` y vigente (IDG-R04.9). |
| `RoleAssignmentPolicy`, `BaseRole`, `HomeScreen` | Las de SPEC-002, sin cambios de reglas, aplicadas a la membresía del plantel activo. |
| `LastAdministratorRule` | Un plantel no puede quedarse sin Administrador activo: lo comprueban salir del plantel, la suspensión, el cambio de roles y la eliminación (IDG-R07.3, R08.2). |
| `DeletionBlockers` | IDG-R08.2 hoy. Los bloqueos de R08.3 (representante) y R08.4 (docente) son puntos de extensión (`DeletionBlocker`) que registrarán SPEC-009 y SPEC-008. |

## Datos

```
users (identidad global; pierde institution_id y la RLS por plantel)
  + consolidation_state  varchar NULL          -- null | pending | review | separated
  + account_state        varchar NOT NULL      -- active | pending_deletion | closed | deleted
  + deletion_requested_at, deletion_due_at timestamptz NULL
  + pending_email varchar NULL, pending_email_token_hash varchar NULL, pending_email_expires_at timestamptz NULL
  - status (pasa a la membresía)
  UNIQUE (email) WHERE consolidation_state IS NULL           (ADR 0006 B)
  RLS forzada, políticas por comando                          (ADR 0006 A)
    SELECT/UPDATE/DELETE  USING (<plantel> IS NULL OR EXISTS (membresía en <plantel>))
    INSERT                WITH CHECK (true)

institution_memberships  (belongsToInstitution + RowLevelSecurity::enable)
  user_id FK, status, suspension_reason NULL, ended_at NULL, timestamps
  UNIQUE (institution_id, user_id)

invitations  (belongsToInstitution + RowLevelSecurity::enable)
  email, name, roles jsonb, token_hash, expires_at, status, invited_by, accepted_by NULL, timestamps
  UNIQUE parcial (institution_id, email) WHERE status = 'pending'

password_reset_tokens  (pasa a ser global)
  email PK, token, created_at;  RowLevelSecurity::enableOutsideTenants (solo sin plantel activo)

sessions  (sin cambios: Laravel la lee antes de resolver el plantel, así que no puede tener RLS)
```

Spatie (`model_has_roles`) no cambia: el *team* es el plantel y el modelo
es la cuenta global.

### Migración de las cuentas actuales (PR A)

Es una sola migración, institución por institución con su tenant activado,
y reversible. Cada paso cita el criterio que cumple:

1. Crea `institution_memberships` e `invitations`.
2. Por cada fila de `users`, crea su membresía (mismo `user_id`, su
   `institution_id`, `status` → estado de la membresía) (IDG-R09.1).
3. Las cuentas que no aceptaron su invitación pasan a `invitations`
   pendientes con su plazo original, y su fila de `users` se borra
   (IDG-R09.6).
4. Agrupa por email normalizado:
   - una sola fila → `consolidation_state = null`;
   - varias filas con nombres normalizados iguales → `pending`;
   - varias con nombres distintos → `review` (IDG-R09.2, R09.5).
5. Quita `users.institution_id`, el índice `(institution_id, email)` y la
   política `tenant_isolation`. Crea el índice parcial y las políticas nuevas.
6. ~~Pasa `password_reset_tokens` a global.~~ **Cambio aprobado (2026-10-15):**
   se hace en el PR B (B-06), junto con la recuperación central. En el PR A la
   recuperación sigue en el subdominio y necesita la tabla por plantel.

**Cambio aprobado (2026-10-15) — enlaces de invitación ya enviados.** Las
cuentas no aceptadas se migran a `invitations` **conservando el hash bcrypt**
del token del *broker* de Laravel y su plazo original (IDG-R09.6).
`Invitation` reconoce los dos formatos: sha256 para las nuevas y bcrypt para
las migradas. La ruta antigua del subdominio (`/invitation/{token}`) redirige
al enlace central, así los correos ya enviados siguen funcionando.

Las contraseñas y sus hashes nunca se copian ni se mueven entre filas
(IDG-R09.7). El `down()` hace el camino inverso mientras no se haya
consolidado ninguna cuenta. Después de la primera consolidación, la vuelta
atrás es por copia de seguridad, y así se anota en las notas de la versión.

## Flujos principales

### Login central y entrada al plantel (PR B)

```
aulaix.com/login ── LogIn(email, password) ── throttle email+IP (IDG-R03.10)
   ├─ credenciales inválidas → mensaje genérico (IDG-R03.3)
   ├─ varias filas con ese email (consolidación) → prueba la contraseña contra cada una;
   │    la que coincide inicia sesión solo para su plantel y ofrece consolidar (IDG-R09.3)
   ├─ cuenta pending_deletion → solo la pantalla «cancelar eliminación» (IDG-R08.7)
   └─ membresías activas: 0 → bienvenida (R03.6) | 1 → traspaso al plantel (R03.4) | n → selector (R03.5)

traspaso: IssueHandoff(cuenta, plantel) exige membresía activa → token cifrado (APP_KEY) con
          cuenta, plantel, caducidad de 60 s y nonce → redirige a {sub}.aulaix.com/auth/handoff?t=…
          ConsumeHandoff: descifra, comprueba plantel = subdominio y caducidad, Cache::add(nonce)
          (un solo uso), inicia la sesión del subdominio (IDG-R03.7, NF03)

{sub}.aulaix.com/login → redirige al login central con ?plantel={sub}; tras autenticarse
          se traspasa a ese plantel si hay membresía activa (IDG-R03.2)
```

En cada petición a un subdominio, el middleware `EnsureActiveMembership`
(sustituye a `EnsureUserIsActive`) comprueba que la membresía del plantel
activo está `active` y que la cuenta está `active`. Si no, cierra la sesión
**de ese subdominio** y redirige al dominio central (IDG-R03.8). Con la RLS
de `users`, una cuenta sin membresía en el plantel ni siquiera se puede
cargar: el guard la trata como invitada (IDG-R03.9).

### Revocación de sesiones

- **Salir del plantel o suspensión** (IDG-R07.2): la comprobación por
  petición corta el acceso en la siguiente petición. Además,
  `SessionRevoker` borra las sesiones de esa cuenta en ese subdominio.
- **Cambio o restablecimiento de contraseña** (IDG-R06.2) y **solicitud de
  eliminación** (IDG-R08.6): borra todas las filas de `sessions` de la
  cuenta salvo la actual.
- **Cerrar sesión:** cierra la sesión en todos los dominios. Con una sola
  identidad, cerrar sesión en un plantel y seguir dentro de otro sería
  sorprendente (decisión pendiente, ver abajo).

### Invitaciones (PR A)

El gestor invita desde el plantel. La respuesta es la misma exista o no la
cuenta (IDG-R04.2). El enlace apunta al **dominio central**:
`aulaix.com/invitaciones/{plantel}/{token}`, porque es allí donde se crea
o se autentica la cuenta.

`AcceptInvitation` activa el tenant del plantel solo para leer la invitación
y crear la membresía, y lo desactiva al terminar:

- **Sin cuenta:** crea la cuenta (el email queda verificado por el propio
  enlace) y la membresía (IDG-R04.4).
- **Con cuenta:** exige estar autenticado con ese mismo email y crea solo la
  membresía (IDG-R04.5, R04.6).

Hasta que exista el login central (PR B), el enlace sigue funcionando para
cuentas nuevas, que son todas las de hoy.

### Consolidación (PR B)

1. Tras entrar con una cuenta `pending`, el sistema envía un código de un solo
   uso al email.
2. `CompleteConsolidation(código, contraseña nueva)` elige la fila
   superviviente (la más antigua) y le asigna la nueva contraseña.
3. Mueve a ella las membresías y los roles (`model_has_roles.model_id`) del
   resto de filas y las borra.
4. Revoca las sesiones de las filas borradas y avisa por correo.
5. Registra el evento en la bitácora de plataforma. Todo ocurre en una
   transacción.

Las cuentas en `review` nunca pasan por este flujo (IDG-R11.8). Las resuelve
soporte con `identity:consolidation:resolve`:

- opciones `--operator`, `--reason`, `--verification-ref` y `--dry-run`;
- la lista de operadores autorizados está en `config/identity.php`;
- el comando y el futuro panel de SPEC-014 usan el mismo caso de uso
  (IDG-NF07).

### Eliminación (PR C)

- `RequestDeletion` comprueba los bloqueos, pone la cuenta en
  `pending_deletion`, suspende sus membresías activas con motivo
  `account_deletion`, revoca las sesiones y los tokens, y avisa por correo.
- La tarea programada diaria `identity:close-due-accounts` vuelve a comprobar
  los bloqueos de las cuentas vencidas:
  - si sigue habiendo alguno, aplaza el cierre y avisa;
  - si no, pasa la cuenta a `closed` y termina sus membresías (IDG-R08.10,
    R08.11).
- El paso a `deleted` no existe hasta que se resuelva la dependencia legal
  D1 (IDG-R08.12).

## Auditoría (IDG-R10)

| Evento | Bitácora | Caso de uso |
|--------|----------|-------------|
| `identity.invitation.{created,corrected,resent,cancelled,accepted}` | Plantel | Members / AcceptInvitation |
| `identity.membership.{created,suspended,reactivated,ended}` | Plantel | Members / LeavePlantel / RequestDeletion |
| `identity.membership.roles_changed` | Plantel | ChangeMemberRoles |
| `identity.account.{name_changed,email_changed}` | Plataforma | Account |
| `identity.account.password_changed` (sin valores) | Plataforma | ChangePassword / ResetPassword / CompleteConsolidation |
| `identity.account.consolidated` | Plataforma | CompleteConsolidation / ResolveConsolidationCase |
| `identity.account.deletion_{requested,cancelled,postponed}`, `identity.account.closed` | Plataforma | RequestDeletion / CancelDeletion / CloseDueAccounts |

Los eventos de plantel se registran con el tenant activo. Los de plataforma
se registran sin plantel activo, que es justo donde viven esos casos de uso
(dominio central, consola y tarea programada).

## Seguridad (STRIDE)

| Amenaza | Vector | Mitigación | Verificado por |
|---------|--------|------------|----------------|
| Spoofing | Fuerza bruta en el login central | Throttle por email + IP con la IP real (proxies de confianza, SPEC-005) | IDG-R03.10 |
| Spoofing | Reutilizar o falsificar un token de traspaso | Cifrado y autenticado con `APP_KEY`, caducidad de 60 s, ligado a cuenta y plantel, nonce de un solo uso en caché | IDG-NF03 |
| Spoofing | Aceptar una invitación ajena | Si la cuenta existe, exige estar autenticado con el email invitado | IDG-R04.6 |
| Tampering | Un gestor cambia la identidad global de una persona | El caso de uso no existe para gestores; solo el titular, con reautenticación | IDG-R05.5, NF05 |
| Repudiation | Cambios de rol o de cuenta sin rastro | `AuditTrail` en cada caso de uso | IDG-R10 |
| Information disclosure | Un plantel lee personas que no son miembros suyos | RLS de `users` por membresía con plantel activo | IDG-NF01 |
| Information disclosure | Un gestor descubre que un email tiene cuenta en otro plantel | Misma respuesta al invitar; lista de miembros sin otras membresías | IDG-R04.2, R05.6, NF02 |
| Information disclosure | Código sin plantel activo lee `users` entera (ADR 0006) | Solo `IdentityAccess` usa `AccountModel`, verificado por un test de arquitectura | Test de arquitectura |
| Elevation of privilege | Entrar a un plantel escribiendo su subdominio | `EnsureActiveMembership` + RLS de `users` | IDG-R03.8, R03.9 |
| Elevation of privilege | Fusión de dos personas distintas | Sin fusión automática; `review` solo la resuelve soporte, con verificación | IDG-R09.5, R11.8 |
| Elevation of privilege | Plantel sin Administrador | `LastAdministratorRule` | IDG-R07.3, R08.2 |

## Errores

| Excepción | HTTP / efecto |
|-----------|---------------|
| `InvalidCredentials`, `AccountInactive`, `TooManyLoginAttempts` | 422 en `email` (mismos mensajes de hoy) |
| `InvitationNotUsable` (caducada, aceptada, anulada, token inválido) | 422 |
| `InvitationEmailMismatch` | 403 |
| `MemberAlreadyExists` | 422 en `email` |
| `LastAdministrator`, `DeletionBlocked` | 422 con el motivo |
| `HandoffRejected` | Redirección al login central, sin detalle |
| `NotAMember` | 404, igual que un plantel inexistente (IDG-R03.9) |

## Estrategia de pruebas

- **Dominio:** `Account` (estados, gracia de 30 días con `Clock`), `Membership`
  (motivos de suspensión y reactivación selectiva), `Invitation` (ciclo de
  vida, token renovado), `EmailAddress`, `LastAdministratorRule`, y las
  reglas de SPEC-002.
- **Aplicación:** cada caso de uso con repositorios en memoria y `AuditTrail`
  falso. Se verifican los eventos auditados y que la respuesta sea idéntica
  exista o no la cuenta.
- **Integración:**
  - RLS de `users` con y sin plantel activo, y la inserción;
  - unicidad parcial del email;
  - migración de datos sobre una copia sembrada con duplicados, pendientes y
    nombres distintos (`up` y `down`);
  - traspaso de un solo uso y caducado;
  - revocación de sesiones.
- **Feature (HTTP):** login central, selector, traspaso, acceso denegado sin
  membresía, invitaciones, consolidación y eliminación.
- **Tests existentes de SPEC-002:** los de criterios que esta spec
  **sustituye** (login en el subdominio, unicidad por plantel, eliminar la
  cuenta desde el perfil) se reescriben con los nuevos criterios IDG y se
  trazan en su matriz. Es un cambio de comportamiento aprobado, no un
  refactor. Los demás (reglas de roles, pantalla de inicio, recuperación)
  conservan su lógica (Q-03).
- **Arquitectura:**
  - solo `IdentityAccess` usa `AccountModel`;
  - los controladores migrados entran en `MIGRATED_CONTROLLERS`.

## Decisiones que necesitan visto bueno

| # | Decisión | Alternativa |
|---|----------|-------------|
| 1 | **ADR 0006**: RLS de `users` por contexto, en lugar de funciones `SECURITY DEFINER` | Mantener el ADR 0004 §6, que exige un rol privilegiado del que el rol de la app podría abusar |
| 2 | **ADR 0006**: cuentas duplicadas como filas separadas marcadas (índice parcial) | `legacy_credentials`, que obliga a fusionar antes de verificar |
| 3 | Traspaso con token cifrado y nonce en caché (Redis), sin tabla | Tabla de traspasos, que necesitaría una excepción de RLS |
| 4 | Aceptar invitaciones en el dominio central (`/invitaciones/{plantel}/{token}`) | En el subdominio: la cuenta aún no es miembro, así que la RLS no la dejaría cargar |
| 5 | Cerrar sesión cierra **todas** las sesiones de la cuenta | Cerrar solo la del dominio actual |
| 6 | Entrega en tres PRs (A, B y C) | Un solo PR, demasiado grande para revisarlo con garantías |
| 7 | Los tests de los criterios de SPEC-002 sustituidos se reescriben | Conservarlos sin cambios es imposible: el comportamiento cambia por decisión de producto |

## Riesgos y plan de rollback

- **Migración de datos (riesgo alto):** se prueba sobre una copia de staging
  con cuentas duplicadas reales antes de producción. Su `down()` es válido
  hasta la primera consolidación; después, la vuelta atrás es por copia de
  seguridad.
- **Ventana entre PR A y PR B:** el login sigue en el subdominio, ya con
  acceso por membresía. Las cuentas duplicadas siguen entrando cada una a su
  plantel, como hoy.
- **Redis obligatorio para el traspaso en producción:** con el *store* `array`
  el nonce no se compartiría entre peticiones. La configuración de producción
  ya usa Redis (`CACHE_STORE=redis`).
- **ADR 0006, código del dominio central:** cualquier lectura nueva de
  `users` sin plantel activo fuera de `IdentityAccess` debe fallar el test de
  arquitectura.

## Notas de implementación (PR A)

Ajustes hechos al implementar, sin cambiar ningún criterio:

- **Ids de `users` tomados de la secuencia.** Con RLS, un `INSERT … RETURNING`
  también tiene que cumplir la política de `SELECT`, y una cuenta recién
  creada aún no es miembro del plantel activo. `AccountModel` pide el id con
  `nextval()` antes de insertar y no usa `RETURNING`.
- **`UpdateMember` en lugar de `ChangeMemberRoles`, `SuspendMember` y
  `ReactivateMember`.** El formulario del gestor cambia roles y estado a la
  vez, y SPEC-002 exige mostrar todos los errores juntos. Un solo caso de uso
  evalúa `RoleAssignmentPolicy` y `LastAdministratorRule` y aplica los dos
  cambios en una transacción. Los eventos auditados siguen separados
  (`roles_changed`, `suspended`, `reactivated`).
- **Puertos de lectura.** `MemberDirectory` sirve las pantallas del gestor
  (miembros e invitaciones pendientes) y `HasActiveMembership` comprueba el
  acceso en el login y en cada petición. Las comprobaciones comunes de los
  casos de uso del gestor están en `MemberRules`.
- **Aceptar una invitación en el PR A.** Tras crear la cuenta, el dominio
  central muestra «invitación aceptada» con el enlace al login del plantel;
  el traspaso de sesión llega con el PR B. Si el email ya tiene cuenta, la
  aceptación se rechaza con un aviso y no crea nada (B-07). Solo recibe ese
  aviso quien tiene un enlace válido.
- **Una invitación pendiente por email.** Si la anterior ya venció, se anula
  al invitar de nuevo; si sigue vigente, se rechaza la nueva.
- **Membresía no activa.** `EnsureActiveMembership` cierra la sesión del
  subdominio y lleva a su login, hasta que el PR B lleve al login central
  (B-04).

## Cambios aprobados para el PR B (2026-10-16)

Al empezar el PR B, el diseño no encajaba con el código en un punto y dejaba
abiertos otros dos. Los tres se aprobaron el 2026-10-16.

### 1. Leer membresías e invitaciones sin plantel activo

**Problema.** El login central (IDG-R03.4 – R03.6), el selector, el traspaso
y la consolidación necesitan saber, **sin plantel activo**, en qué planteles
tiene membresía una cuenta y qué invitaciones pendientes tiene su email.
Hoy `institution_memberships` e `invitations` usan `RowLevelSecurity::enable`:
sin plantel activo se leen vacías. El ADR 0006 solo abre `users` en el
dominio central.

**Propuesta.** Aplicar a las dos tablas el mismo criterio que el ADR 0006 A
aplica a `users`, solo para **lectura**:

| Comando | Política |
|---------|----------|
| `SELECT` | Visible si no hay plantel activo, o si `institution_id` es el del plantel activo. |
| `INSERT`, `UPDATE`, `DELETE` | Solo en el plantel activo (como hoy). |

- Con un plantel activo nada cambia: cada plantel sigue viendo solo lo suyo
  (S-01, IDG-NF01, NF02).
- Sin plantel activo, solo el código del dominio central, la consola y las
  tareas programadas leen; ese código vive en `IdentityAccess`, y los modelos
  ya están protegidos por el test de arquitectura de módulos.
- Se añade como decisión C del ADR 0006 (enmienda en este PR).

**Alternativas.** Activar uno a uno todos los planteles para buscar las
membresías de una cuenta: coste proporcional al número de planteles en cada
login. Copiar las membresías en `users`: duplica datos y la RLS deja de
proteger el estado de la membresía.

### 2. Código de consolidación (B-08)

El diseño pide «un código de un solo uso» sin decir dónde se guarda.
**Propuesta:** código de 6 dígitos guardado **hasheado en caché** (Redis en
producción), ligado a la cuenta, con 15 minutos de vida, un solo uso y 5
intentos como máximo; pedir otro invalida el anterior (IDG-NF04). Sin tabla
nueva, igual que el nonce del traspaso.

### 3. Sesión central

El dominio central conserva su propia sesión tras el login. Es la que
permite cambiar de plantel sin contraseña (IDG-R03.7): «Cambiar de plantel»
lleva al selector central, que emite un traspaso nuevo. Si la sesión central
caducó, se pide el login otra vez. Cerrar sesión (B-05) borra **todas** las
filas de `sessions` de la cuenta (central y subdominios), por eso
`SESSION_DRIVER` debe ser `database` en todos los entornos salvo los tests,
que lo fijan por test cuando lo necesitan.

## Notas de implementación (PR B)

Ajustes hechos al implementar, sin cambiar ningún criterio:

- **Repositorios con plantel explícito.** Con la decisión C del ADR 0006, sin
  plantel activo la RLS ya no limita la lectura de membresías e invitaciones.
  Por eso `MembershipRepository` e `InvitationRepository` reciben siempre el
  plantel, y `MemberDirectory` filtra por el plantel activo además de la RLS.
- **Estado `separated`.** Cuando soporte decide «mantener separadas» las
  cuentas de un caso en revisión (IDG-R11.2), pasan a `consolidation_state =
  separated`: siguen compartiendo email (el índice parcial lo permite), cada
  contraseña abre solo su plantel y nunca se ofrecen para unificar.
- **Recuperación con cuentas por unificar.** Un email con varias cuentas
  históricas no tiene una sola contraseña que restablecer: en lugar del
  enlace recibe un correo que explica cómo unificarlas. La respuesta en
  pantalla es la misma de siempre (IAM-R05.3).
- **`password_reset_tokens` se reconstruye.** La tabla pasa a global y los
  enlaces de recuperación pedidos antes del despliegue dejan de funcionar
  (viven 60 minutos). Los enlaces antiguos del subdominio redirigen al
  dominio central.
- **Aceptar con una cuenta existente (B-07).** Estar autenticado con el email
  invitado ya prueba que la persona recibe correo allí, así que no hace falta
  el token: se acepta desde el enlace o desde la bienvenida. Tras aceptar, el
  traspaso abre directamente el plantel. Volver a un plantel cuya membresía
  terminó queda fuera de este PR (lo resuelve PR C, con «salir del plantel»).
- **Reautenticación.** La confirmación de contraseña se comprueba contra la
  cuenta de la sesión, nunca por email, porque un email por unificar tiene
  varias cuentas. Las rutas sensibles exigen una confirmación de menos de
  10 minutos (`password.confirm:…,600`).
- **Membresía no activa.** `EnsureActiveMembership` cierra la sesión del
  subdominio y lleva a la pantalla central de planteles.
- **Redirecciones entre dominios.** Las respuestas que llevan a otro dominio
  usan `Inertia::location`, para que también funcionen en visitas de Inertia.
