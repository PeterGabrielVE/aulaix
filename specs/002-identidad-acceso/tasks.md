---
spec: 002-identidad-acceso
estado: borrador
fecha: 2026-10-10
diseño: design.md
depende-de: 000 y 001 (implementadas)
---

# Tareas — Identidad y acceso (refactor hexagonal)

Regla transversal: al cerrar **cada** tarea, `php artisan test --compact`
(incluido `--group=rls`) sigue en verde. Se sugieren tres PRs: dominio
(fases 1–2), infraestructura y datos (fase 3), casos de uso y adaptadores
(fases 4–6).

## Fase 1 — Kernel

- [ ] **T-01** — Ampliar `Shared`: `BusinessRuleViolations` (varias
  violaciones campo → mensaje, patrón Notification) y su mapeo a 422 con todas
  las claves.
  - Test: `tests/Feature/Shared/DomainExceptionRenderingTest.php` › varias violaciones

## Fase 2 — Dominio

- [ ] **T-02** — VOs `UserId`, `EmailAddress`, `PersonName`, `HashedPassword`; enum `UserStatus` (mover).
  - Test: `tests/Unit/IdentityAccess/*Test.php`
- [ ] **T-03** — `Permission`, `BaseRole` (prioridad, permisos, pantalla), `HomeScreen`, `RoleName`, `RoleSet`.
  - Criterios: IAM-R11.1, IAM-R12.2
  - Test: `BaseRoleTest`, `RoleSetTest`
- [ ] **T-04** — Agregado `User` + eventos.
  - Criterios: IAM-R04.2, IAM-R08.2, IAM-R09.5, IAM-R09.11
  - Test: `tests/Unit/IdentityAccess/UserTest.php`
- [ ] **T-05** — `RoleAssignmentPolicy`.
  - Criterios: IAM-R10.1 – IAM-R10.6
  - Test: `tests/Unit/IdentityAccess/RoleAssignmentPolicyTest.php` (matriz de casos)
- [ ] **T-06** — Puertos `UserRepository`, `RoleCatalog` y los de `Application/Port`.

## Fase 3 — Infraestructura y datos

- [ ] **T-07** — Morph map `user` + migración de `model_type` (un `UPDATE`
  por tabla; están exentas de RLS) y su `down()`.
  - Test: `tests/Feature/IdentityAccess/MorphTypeMigrationTest.php` (los roles sobreviven a `up` y a `down`)
- [ ] **T-08** — Mover `App\Models\User` → `UserModel` (`#[UseFactory]`,
  `config/auth.php`); notificaciones a `Infrastructure/Notifications`.
  Actualizar los `use` de tests, seeders y factories.
  - Test: suite completa
- [ ] **T-09** — `UserMapper`, `EloquentUserRepository`, `EloquentUserDirectory`.
  - Test: `tests/Feature/IdentityAccess/EloquentUserRepositoryTest.php`
- [ ] **T-10** — `SpatieRoleCatalog`; mover `SpatiePermissionTeamHook` desde `app/Providers`.
  - Criterios: IAM-R11.2, IAM-R11.3
  - Test: `RolePermissionTest.php`
- [ ] **T-11** — Adaptadores de auth: `LaravelAuthenticator`,
  `RateLimiterLoginThrottle`, `LaravelPasswordHasher`,
  `BrokerInvitationTokens`, `BrokerPasswordResetTokens`.
  - Test: tests de integración de cada adaptador

## Fase 4 — Casos de uso

- [ ] **T-12** — `ProvisionBaseRolesHandler`; `RolePermissionSeeder` y la
  migración de roles lo usan.
  - Criterios: IAM-R11.1, IAM-R11.4
- [ ] **T-13** — `CreateUser`, `UpdateUser`, `ResendInvitation` + listener de
  `UserInvited`.
  - Criterios: IAM-R09.5 – R09.13, IAM-R10.*
  - Test: handlers con puertos en memoria; incluye «no se envía invitación si el alta falla»
- [ ] **T-14** — `LogInHandler` + test del bloqueo tras 5 intentos.
  - Criterios: IAM-R02.3 – R02.9
- [ ] **T-15** — `AcceptInvitation`, `RequestPasswordReset`, `ResetPassword`.
  - Criterios: IAM-R04.*, IAM-R05.*
- [ ] **T-16** — `ConfirmPassword`, `ChangePassword`, `MarkEmailVerified`, `UpdateProfile`, `DeleteOwnAccount`.
  - Criterios: IAM-R06.*, IAM-R07.*, IAM-R08.*
- [ ] **T-17** — Queries `ListUsers`, `GetUserForEdit`, `ListAssignableRoles`, `ResolveHomeScreen`.
  - Criterios: IAM-R09.3, IAM-R09.4, IAM-R12.*

## Fase 5 — Adaptadores de entrada

- [ ] **T-18** — `UserController` + `UserRequest` (solo forma).
  - Test: `UserManagementTest.php` completo
- [ ] **T-19** — Controladores de `Auth/*` + `LoginRequest` (solo forma).
  - Test: `tests/Feature/Auth/*`
- [ ] **T-20** — `ProfileController`, `HomeController` (tabla `HomeScreen` → ruta), `EnsureUserIsActive`, `HandleInertiaRequests`.
  - Test: `ProfileTest.php`, `RoleHomeTest.php`, `AuthenticationTest.php`
- [ ] **T-21** — Borrar las clases antiguas de `app/` y añadir los controladores a `MIGRATED_CONTROLLERS`.
  - Test: suite `Architecture`

## Fase 6 — Hallazgos (solo si se aprueban)

- [ ] **T-22** — IAM-H01: proteger al último Administrador activo (borrado y desactivación).
- [ ] **T-23** — IAM-H02: DTO explícito en `HandleInertiaRequests` y revisión de los componentes Vue que usan `auth.user`.
- [ ] **T-24** — IAM-H03: unicidad de email del perfil dentro del caso de uso.
- [ ] **T-25** — IAM-H04: escapar comodines `LIKE` en `EloquentUserDirectory`.
- [ ] **T-26** — IAM-H05: ADR sobre el aislamiento de las tablas de Spatie y,
  según lo que se decida, la política RLS o los tests dedicados.

## Verificación final

- [ ] `php artisan test --compact` en verde (incluido `--group=rls`).
- [ ] Suite `Architecture` en verde; ninguna referencia a `App\Models\User`.
- [ ] Migración de `model_type` probada sobre una copia de staging.
- [ ] `/security-review` (S-12).
- [ ] `vendor/bin/pint --dirty` sin cambios.
- [ ] `estado: implementada` en `design.md` y `tasks.md`.
