---
spec: 004-identidad-global
estado: implementada
fecha: 2026-10-15
diseño: design.md
depende-de: 001-tenancy (implementada), 005-auditoria (implementada)
---

# Tareas — Identidad global, membresías y acceso centralizado

Se entrega en **tres PRs** (A, B y C). Cada uno deja la suite completa en
verde, incluidos `--group=rls` y `Architecture`, y se mergea antes de
empezar el siguiente.

**Tests de SPEC-002 sustituidos.** Cuando una tarea cambia un
comportamiento que esta spec sustituye, reescribe su test con el criterio IDG
y lo traza en la matriz. Los tests de criterios no sustituidos (reglas de
roles, pantalla de inicio, recuperación) conservan su lógica (Q-03).

---

## PR A — Modelo, datos y gestión de miembros

### Fase A1 — Dominio (sin Laravel)

- [x] **A-01** — `EmailAddress` (normalización), `PersonName`, `AccountId`,
  `HashedPassword`, `AccountState`, `ConsolidationState`.
  - Criterios: IDG-R01.2
  - Test: `tests/Unit/IdentityAccess/Domain/Account/*Test.php`
- [x] **A-02** — `BaseRole`, `Permission`, `RoleName`, `RoleSet`,
  `HomeScreen` y `RoleAssignmentPolicy` (las reglas de SPEC-002, en el dominio).
  - Criterios: IAM-R10.*, IAM-R11.1, IAM-R12.2
  - Test: `tests/Unit/IdentityAccess/Domain/Role/*Test.php`, `RoleAssignmentPolicyTest.php` (matriz de casos)
- [x] **A-03** — `Membership` (estados, motivo de suspensión, reactivación
  selectiva) y `LastAdministratorRule`.
  - Criterios: IDG-R02.2, IDG-R02.3, IDG-R07.3 (regla), IDG-R08.9 (regla)
  - Test: `tests/Unit/IdentityAccess/Domain/Membership/*Test.php`
- [x] **A-04** — `Invitation` (ciclo de vida, caducidad de 7 días, token
  renovado al corregir o reenviar) e `InvitationToken` (aleatorio, guardado
  como hash).
  - Criterios: IDG-R04.1, R04.7 – R04.9, IDG-NF04
  - Test: `tests/Unit/IdentityAccess/Domain/Invitation/InvitationTest.php`
- [x] **A-05** — Agregado `Account` (núcleo: nombre, estado,
  consolidación) y puertos de repositorio.
  - Test: `tests/Unit/IdentityAccess/Domain/Account/AccountTest.php`

### Fase A2 — Datos

- [x] **A-06** — Migración del esquema: `institution_memberships` e
  `invitations` (RLS estándar); columnas nuevas de `users`.
  (`password_reset_tokens` global pasa a B-06, cambio aprobado el 2026-10-15.)
  - Criterios: IDG-R01.3, IDG-NF04
  - Test: `TenantTableTest` (automático); `tests/Feature/IdentityAccess/MembershipIsolationTest.php` (grupo `rls`)
- [x] **A-07** — Migración de datos de las cuentas: membresías, invitaciones
  pendientes **con su hash bcrypt y su plazo original** (los enlaces ya
  enviados siguen funcionando), marcado de duplicados `pending`/`review`, sin
  mover contraseñas. Con su `down()`.
  - Criterios: IDG-R09.1, R09.2, R09.5 (marcado), R09.6, R09.7
  - Test: `tests/Feature/IdentityAccess/AccountMigrationTest.php` (base sembrada
    con cuenta única, duplicados con el mismo nombre, duplicados con nombres
    distintos e invitación pendiente; `up` y `down`)
- [x] **A-08** — Quitar `users.institution_id`; índice único parcial de
  email; políticas RLS por comando de `users` (ADR 0006).
  - Criterios: IDG-R01.1, IDG-NF01
  - Test: `tests/Feature/IdentityAccess/AccountIsolationTest.php` (grupo `rls`:
    invisible sin membresía con plantel activo, visible sin plantel activo,
    inserción permitida, unicidad parcial)

### Fase A3 — Infraestructura

- [x] **A-09** — Mover `App\Models\User` → `AccountModel` (`$table = 'users'`,
  `#[UseFactory]`, morph map `user` + migración de `model_has_roles.model_type`);
  `MembershipModel`, `InvitationModel`, mappers y repositorios;
  `SpatieRoleCatalog`; mover `SpatiePermissionTeamHook`.
  - Test: `tests/Feature/IdentityAccess/Infrastructure/*RepositoryTest.php`;
    `RolePermissionTest.php` (sin cambios de lógica)
- [x] **A-10** — `EnsureActiveMembership` (sustituye a `EnsureUserIsActive`).
  - Criterios: IDG-R03.8, IDG-R03.9
  - Test: `tests/Feature/IdentityAccess/MembershipAccessTest.php` (membresía
    suspendida o terminada → fuera de ese plantel; sin membresía → como un plantel inexistente)
- [x] **A-11** — Test de arquitectura: solo `IdentityAccess` usa `AccountModel` (ADR 0006).
  - Test: suite `Architecture` (verificada en rojo)

### Fase A4 — Casos de uso del gestor

- [x] **A-12** — `InviteMember` (misma respuesta exista o no la cuenta;
  rechazo si ya es miembro) + correo + auditoría.
  - Criterios: IDG-R04.1 – R04.3, IDG-R10.1, IDG-NF02
- [x] **A-13** — `CorrectInvitation`, `ResendInvitation`, `CancelInvitation`.
  - Criterios: IDG-R04.7, R04.8, R04.10
- [x] **A-14** — `AcceptInvitation` en el dominio central
  (`/invitaciones/{plantel}/{token}`): solo para cuentas nuevas en este PR.
  - Criterios: IDG-R04.4, R04.9
- [x] **A-15** — `ChangeMemberRoles`, `SuspendMember`, `ReactivateMember`,
  `ListMembers` (sin otras membresías de la persona). Los tres primeros,
  unidos en `UpdateMember` (ver «Notas de implementación» del diseño).
  - Criterios: IDG-R02.1 – R02.3, IDG-R05.5, R05.6, IAM-R09.3, IAM-R10.*
- [x] **A-16** — Reescribir `UserController`/`UserRequest` sobre estos casos
  de uso (pantallas de «Usuarios» → miembros e invitaciones) y reescribir los
  tests de los criterios sustituidos (IAM-R09.5 – R09.7, R09.11 – R09.13).
  - Test: `UserManagementTest.php` (reescrito donde sustituye, intacto donde no)
- [x] **A-17** — `ResolveHomeScreen` + `HomeController`.
  - Criterios: IAM-R12.*
  - Test: `RoleHomeTest.php` (sin cambios de lógica)

### Cierre de PR A

- [x] Suite completa, `rls` y `Architecture` en verde; `migrate:fresh --seed` (dos veces).
- [ ] Migración de datos probada sobre una copia de staging.
- [x] `/security-review` (S-12): sin hallazgos de confianza alta o media (2026-10-23; ver «Revisión de seguridad» en `specs/roadmap.md`).

---

## PR B — Autenticación central y consolidación

- [x] **B-01** — Puertos `Authenticator`, `LoginThrottle` (email + IP),
  `HandoffTokens`, `SessionRevoker` y sus adaptadores.
  - Criterios: IDG-R03.10, IDG-NF03
  - Test: `CacheHandoffTokensTest` (un solo uso, caducado a los 61 s, plantel ajeno)
- [x] **B-02** — `LogIn` central (cuentas normales y filas en consolidación;
  0/1/n membresías) + pantallas de login, selector y bienvenida.
  - Criterios: IDG-R03.1, R03.3 – R03.6, R09.3
- [x] **B-03** — `IssueHandoff` / `ConsumeHandoff` + cambio de plantel desde el menú.
  - Criterios: IDG-R03.7, IDG-NF03
- [x] **B-04** — Redirección del login de los subdominios al central, con
  vuelta al plantel. Reescribir los tests de IAM-R02.1 – R02.5.
  - Criterios: IDG-R03.2
- [x] **B-05** — `LogOut` global.
- [x] **B-06** — `password_reset_tokens` global (`enableOutsideTenants`) +
  `RequestPasswordReset` / `ResetPassword` centrales
  (mantienen IAM-R05.2 – R05.9) + cerrar las otras sesiones + aviso.
  - Criterios: IDG-R06.1 – R06.3
- [x] **B-07** — `AcceptInvitation` para cuentas existentes (autenticarse y
  coincidir con el email).
  - Criterios: IDG-R04.5, R04.6
- [x] **B-08** — `StartConsolidation` / `CompleteConsolidation` (código de un
  solo uso, contraseña nueva, mover membresías y roles, borrar las filas sobrantes, aviso).
  - Criterios: IDG-R09.4, R09.7
- [x] **B-09** — Comandos `identity:consolidation:list` y
  `identity:consolidation:resolve` (lista de operadores autorizados, `--dry-run`,
  validación del estado, transacción, auditoría).
  - Criterios: IDG-R11.1 – R11.8, IDG-NF07
- [x] **B-10** — Reautenticación (`password.confirm`, 10 minutos) para las
  operaciones sensibles.
  - Criterios: IDG-NF05
- [x] **Cierre de PR B** — suite en verde, `/security-review`: sin hallazgos de confianza alta o media (2026-10-23; ver «Revisión de seguridad» en `specs/roadmap.md`).

---

## PR C — Cuenta del titular y eliminación

- [x] **C-01** — `ChangeName`; `RequestEmailChange` / `ConfirmEmailChange`
  (verificar el nuevo email, avisar al anterior, rechazar un email en uso sin
  revelar el motivo).
  - Criterios: IDG-R05.1 – R05.4
- [x] **C-02** — `ChangePassword` desde la cuenta (cierra las otras sesiones).
  - Criterios: IDG-R06.1, R06.2
- [x] **C-03** — `LeavePlantel` (con `LastAdministratorRule`, revoca las
  sesiones de ese plantel, conserva los registros). Reescribir los tests de IAM-R08.4 y R08.5.
  - Criterios: IDG-R07.1 – R07.4
- [x] **C-04** — `RequestDeletion` / `CancelDeletion` (bloqueos, gracia de 30
  días, suspensión `account_deletion`, revocación, avisos, pantalla limitada
  durante la gracia, invitaciones rechazadas).
  - Criterios: IDG-R08.1 – R08.9, R08.14
- [x] **C-05** — Tarea programada diaria `identity:close-due-accounts`
  (vuelve a comprobar los bloqueos; aplaza o cierra).
  - Criterios: IDG-R08.10, R08.11, R08.13
- [x] **C-06** — Terminología «plantel» en toda la interfaz, incluidas las
  pantallas existentes.
  - Criterios: IDG-NF08
- [x] **Cierre de PR C** — suite en verde, `/security-review`, matriz
  completa, `estado: implementada`. Revisión: sin hallazgos de confianza alta o media (2026-10-23; ver «Revisión de seguridad» en `specs/roadmap.md`).

## Fuera de esta spec

- IDG-R08.12 (paso a `deleted`): bloqueado por la dependencia legal D1.
- IDG-R08.3 y R08.4 (bloqueos por representados y por asignaciones docentes):
  los puntos de extensión se crean aquí; los registran SPEC-009 y SPEC-008.
