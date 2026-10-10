---
spec: 002-identidad-acceso
estado: borrador
fecha: 2026-10-10
requisitos: requirements.md
depende-de: 000-fundaciones-hexagonales, 001-tenancy
---

# Diseño — Identidad y acceso (refactor hexagonal)

## Resumen

Se crea el módulo `IdentityAccess`. Las reglas de negocio que hoy están
repartidas entre closures de `UserRequest`, métodos de `User` y controladores
pasan a ser dominio puro y probado aisladamente:

- `RoleAssignmentPolicy` (IAM-R10)
- prioridad de roles y pantalla de inicio (IAM-R12)
- ciclo de vida del usuario: invitación, activación, verificación

Laravel (guard de sesión, password broker, rate limiter, Spatie, Mail) queda
detrás de puertos. El comportamiento no cambia (Q-03).

## Estructura

```
src/IdentityAccess/
├── Domain/
│   ├── User/
│   │   ├── User.php                    agregado
│   │   ├── UserId.php · EmailAddress.php · PersonName.php · HashedPassword.php
│   │   ├── UserStatus.php              enum (movido desde App\Enums)
│   │   ├── UserRepository.php          puerto
│   │   └── Events/                     UserInvited · InvitationAccepted · EmailChanged · UserDeactivated · PasswordChanged
│   ├── Role/
│   │   ├── RoleName.php                VO
│   │   ├── RoleSet.php                 colección inmutable, sin duplicados
│   │   ├── BaseRole.php                enum: prioridad, permisos y pantalla de inicio
│   │   ├── Permission.php              enum
│   │   ├── HomeScreen.php              enum: Admin, Director, Coordinator, Teacher, Guardian, Student
│   │   └── RoleCatalog.php             puerto: roles de la institución, roles que conceden un permiso
│   └── Policy/
│       ├── RoleAssignmentPolicy.php    servicio de dominio (IAM-R10)
│       └── AssignmentRequest.php       actor, target, roles pedidos, estado pedido
├── Application/
│   ├── Port/                           Authenticator · LoginThrottle · PasswordHasher · InvitationTokens · PasswordResetTokens
│   ├── Auth/                           LogIn · AcceptInvitation · RequestPasswordReset · ResetPassword
│   │                                   ConfirmPassword · ChangePassword · MarkEmailVerified
│   ├── Profile/                        UpdateProfile · DeleteOwnAccount
│   ├── Users/                          CreateUser · UpdateUser · ResendInvitation
│   │                                   ListUsers (query) · GetUserForEdit (query) · ListAssignableRoles (query)
│   ├── Roles/                          ProvisionBaseRoles
│   └── Home/                           ResolveHomeScreen (query)
└── Infrastructure/
    ├── Persistence/                    UserModel (Eloquent, movido desde App\Models\User) · UserMapper
    │                                   EloquentUserRepository · EloquentUserDirectory (lecturas)
    ├── Permission/                     SpatieRoleCatalog · SpatiePermissionTeamHook (movido desde app/Providers)
    ├── Auth/                           LaravelAuthenticator · RateLimiterLoginThrottle · LaravelPasswordHasher
    │                                   BrokerInvitationTokens · BrokerPasswordResetTokens
    ├── Notifications/                  UserInvitation · ResetPasswordLink (movidas desde App\Notifications)
    └── IdentityAccessServiceProvider.php
```

## Modelo de dominio

| Elemento | Tipo | Invariantes / responsabilidad |
|----------|------|-------------------------------|
| `User` | Agregado | Pertenece a una sola `InstitutionId`. `invite()` lo crea activo, sin verificar, con roles ≥ 1 y registra `UserInvited`. `changeEmail()` lo marca sin verificar (IAM-R08.2, IAM-R09.11). `acceptInvitation(HashedPassword, Clock)` fija la contraseña y verifica el email. `deactivate()` / `activate()`. `hasAcceptedInvitation()` ⇔ email verificado. |
| `EmailAddress` | VO | Minúsculas, formato válido, ≤ 255. |
| `PersonName` | VO | No vacío, ≤ 255. |
| `HashedPassword` | VO | Hash opaco. El dominio nunca ve la contraseña en claro. |
| `RoleSet` | VO | Inmutable, sin duplicados. `contains()`, `highestPriority(): ?BaseRole`. |
| `BaseRole` | Enum | Orden = prioridad (IAM-R12.2). `permissions()` (tabla de IAM-R11.1). `homeScreen()`. |
| `RoleAssignmentPolicy` | Servicio de dominio | Recibe `AssignmentRequest` y el conjunto de roles que conceden `gestionar-usuarios`. Aplica IAM-R10.1, R10.3, R10.4 y R10.5 y **acumula** las violaciones en lugar de cortar en la primera. |

### `RoleAssignmentPolicy` (antes en closures de `UserRequest`)

```
editingSelf = actor.id == target?.id

si editingSelf y actor tiene Administrador y no pide Administrador  → roles: «No puedes quitarte a ti mismo el rol de Administrador.»
si no, si editingSelf y ningún rol pedido concede gestionar-usuarios → roles: «No puedes quitarte a ti mismo la gestión de usuarios.»
si actor no es Administrador y (pide Administrador ≠ target es Administrador)
                                                                    → roles: «Solo un Administrador puede asignar o quitar el rol de Administrador.»
si editingSelf y estado pedido ≠ activo                             → status: «No puedes desactivar tu propia cuenta.»
```

Los mensajes y las claves de campo (`roles`, `status`) son los actuales: los
tests feature los comprueban.

## Casos de uso

| Caso de uso | Puertos | Criterios |
|-------------|---------|-----------|
| `LogInHandler` | `Authenticator`, `LoginThrottle`, `CurrentTenant` | IAM-R02.3 – R02.9 |
| `AcceptInvitationHandler` | `InvitationTokens`, `PasswordHasher`, `UserRepository`, `Clock`, `EventBus` | IAM-R04.2 – R04.6 |
| `RequestPasswordResetHandler` | `PasswordResetTokens` | IAM-R05.2 – R05.4 |
| `ResetPasswordHandler` | `PasswordResetTokens`, `PasswordHasher`, `UserRepository` | IAM-R05.6 – R05.10 |
| `ConfirmPasswordHandler` / `ChangePasswordHandler` | `PasswordHasher`, `UserRepository` | IAM-R07.* |
| `MarkEmailVerifiedHandler` | `UserRepository`, `Clock` | IAM-R06.2 |
| `UpdateProfileHandler` / `DeleteOwnAccountHandler` | `UserRepository`, `PasswordHasher` | IAM-R08.* |
| `CreateUserHandler` | `UserRepository`, `RoleCatalog`, `RoleAssignmentPolicy`, `InvitationTokens`, `TransactionManager`, `EventBus` | IAM-R09.5 – R09.10, IAM-R10.* |
| `UpdateUserHandler` | ídem | IAM-R09.11, IAM-R10.* |
| `ResendInvitationHandler` | `UserRepository`, `InvitationTokens` | IAM-R09.12, R09.13 |
| `ListUsersQuery` / `GetUserForEditQuery` / `ListAssignableRolesQuery` | `UserDirectory`, `RoleCatalog` | IAM-R09.3, R09.4 |
| `ProvisionBaseRolesHandler` | `RoleCatalog` | IAM-R11.1, R11.4 |
| `ResolveHomeScreenQuery` | `UserRepository` | IAM-R12.1 – R12.3 |

Flujo de `CreateUserHandler`:

```
validar unicidad del email (UserRepository::emailExists) ─┐
validar roles existentes (RoleCatalog)                     ├─► BusinessRuleViolations (todas juntas, 422)
RoleAssignmentPolicy                                       ┘
TransactionManager::run:
    User::invite(...) → UserRepository::save()          (Spatie syncRoles dentro del adaptador)
EventBus::publish(UserInvited)  ──(tras commit)──► listener → InvitationTokens::send()
```

El correo de invitación se envía tras confirmar la transacción: si el alta
falla, no sale ningún correo (hoy no está garantizado).

## Puertos y adaptadores

| Puerto | Adaptador | Notas |
|--------|-----------|-------|
| `UserRepository` | `EloquentUserRepository` | Sincroniza `RoleSet` con `syncRoles()` de Spatie al guardar. |
| `UserDirectory` | `EloquentUserDirectory` | Listado paginado con `ILIKE` (query service, A-08). |
| `RoleCatalog` | `SpatieRoleCatalog` | Filtra por `institution_id` (team de Spatie). |
| `Authenticator` | `LaravelAuthenticator` | `attempt(email, password, remember, onlyIf: User → bool)`, `lastAttemptHadValidPassword()`, `login(UserId)`, `currentUser(): ?User`. |
| `LoginThrottle` | `RateLimiterLoginThrottle` | Clave `institution|email|ip`, 5 intentos. |
| `PasswordHasher` | `LaravelPasswordHasher` | `hash()`, `verify()`. |
| `InvitationTokens` | `BrokerInvitationTokens` | Broker `invitations` (7 días); `send(User)`, `redeem(email, token, callback)`. |
| `PasswordResetTokens` | `BrokerPasswordResetTokens` | Broker `users` (60 min, throttle 60 s). |
| `TenantActivationHook` (Tenancy) | `SpatiePermissionTeamHook` | Fija el team de Spatie al activar el tenant. |

## Adaptadores de entrada (`app/`)

- Controladores de `Http/Controllers/Auth/*`, `UserController`,
  `ProfileController` y `HomeController`: validan la forma de la petición,
  llaman al handler y traducen el resultado.
- Las operaciones puramente de sesión HTTP (`session()->regenerate()`,
  `invalidate()`, `regenerateToken()`) se quedan en el controlador: son
  detalles del adaptador, no reglas.
- `UserRequest` se reduce a validación de forma (requeridos, formato de
  email, `roles` como array de strings distintos, `status` del enum). La
  unicidad, la existencia de roles y la política pasan al caso de uso.
- `LoginRequest` se reduce a validación de forma; la lógica de intento y
  throttle pasa a `LogInHandler`.
- `HomeController` mapea `HomeScreen` → nombre de ruta
  (`admin.home`, `director.home`…) en una tabla de presentación en `app/`.
- `EnsureUserIsActive` usa `Authenticator::currentUser()`.
- Se mantienen los middleware `permission:` y `role:` de Spatie en las rutas,
  como primera capa de autorización (S-04).

## Datos

**Riesgo crítico — `model_type` polimórfico.** Spatie guarda la clase del
usuario en `model_has_roles.model_type` y `model_has_permissions.model_type`
(`'App\Models\User'`). Si se mueve el modelo sin migrar esos datos, **todos
los usuarios pierden sus roles**.

1. `Relation::enforceMorphMap(['user' => UserModel::class])` en el provider.
2. Migración reversible que cambia `model_type` de `App\Models\User` a
   `user` con un `UPDATE` por tabla. Es posible porque esas tablas están
   exentas de RLS (`RLS_EXEMPT_TABLES`, ver S-01). Si en el futuro dejan de
   estarlo (IAM-H05), la migración tendría que ir institución por institución.
3. Test de integración: un usuario con rol creado antes de la migración sigue
   teniéndolo después.

Otros cambios: `config/auth.php` → `providers.users.model = UserModel::class`;
`UserFactory` apunta a `UserModel`, que declara `#[UseFactory]`. Sin cambios
de esquema.

## Patrones aplicados

| Patrón | Fuerza que resuelve |
|--------|---------------------|
| Domain Service (`RoleAssignmentPolicy`) | Regla que involucra a dos usuarios (actor y objetivo) y al catálogo de roles: no pertenece a un solo agregado. |
| Notification (acumular violaciones) | Conserva el comportamiento actual de mostrar todos los errores del formulario a la vez, y no solo el primero. |
| Domain Event + publicación tras commit | El correo de invitación solo sale si el usuario se creó. |
| Repository + Mapper | `User` de dominio independiente de `Authenticatable`/`HasRoles`. |
| Adapter (broker, guard, rate limiter) | Reutiliza la seguridad probada de Laravel sin que el núcleo dependa de ella. |
| Enum con comportamiento (`BaseRole`) | Prioridad, permisos y pantalla de inicio en un solo lugar (hoy repartidos entre `User::HOME_ROUTES` y `RolePermissionSeeder`). |

## Modelo de amenazas (STRIDE)

| Amenaza | Vector | Mitigación | Verificado por |
|---------|--------|------------|----------------|
| Spoofing | Fuerza bruta de contraseñas | Bloqueo tras 5 intentos por institución+email+IP | IAM-R02.8 |
| Spoofing | Usar en un colegio la cuenta de otro | Guard sobre `UserModel` con RLS; tokens por institución | IAM-R02.5, IAM-R05.10 |
| Tampering | Asignarse Administrador (escalada) | `RoleAssignmentPolicy` en el caso de uso + middleware `permission:` | IAM-R10.1 – R10.4 |
| Information disclosure / Tampering | Leer o asignar roles de otra institución: las tablas de Spatie no tienen RLS | Scope por *team* de Spatie, fijado por `SpatiePermissionTeamHook`; `SpatieRoleCatalog` filtra por `institution_id` explícitamente | IAM-R11.2, IAM-R11.3; **riesgo residual: IAM-H05** |
| Repudiation | Cambios de rol sin rastro | Eventos de dominio publicados; **auditoría persistente fuera de alcance** (propuesta futura) | — |
| Information disclosure | Enumerar correos por login o recuperación | Respuestas idénticas | IAM-R02.7, IAM-R05.3, IAM-R05.4 |
| Information disclosure | Modelo `User` completo enviado a Inertia | **Abierto: IAM-H02** | — |
| Denial of service | Envío masivo de correos de recuperación | throttle 6/min por cliente + 60 s por cuenta | IAM-R05.4, IAM-R05.5 |
| Elevation of privilege | Institución sin Administrador | **Abierto: IAM-H01** | — |
| Elevation of privilege | Pérdida de roles al migrar `model_type` | Morph map + migración + test | ver «Datos» |

## Errores

| Excepción | HTTP | Campo | Mensaje (actual) |
|-----------|------|-------|------------------|
| `InvalidCredentials` | 422 | `email` | `auth.failed` |
| `AccountInactive` | 422 (login) / redirección con error (sesión, invitación) | `email` | `auth.inactive` |
| `TooManyLoginAttempts` | 422 | `email` | `auth.throttle` |
| `EmailAlreadyInUse` | 422 | `email` | regla `unique` de Laravel |
| `UnknownRole` | 422 | `roles.N` | regla `in` de Laravel |
| `RoleAssignmentDenied` | 422 | `roles` / `status` | los de `RoleAssignmentPolicy` |
| `InvitationAlreadyAccepted` | 409 | — | «Este usuario ya activó su cuenta.» |
| `InvalidToken` | 422 | `email` | `passwords.token` |
| `UserNotFound` | 404 | — | — |

## Estrategia de pruebas

- **Dominio:** `RoleAssignmentPolicyTest` (una matriz de casos que cubra cada
  regla y sus combinaciones), `RoleSetTest` (prioridad), `BaseRoleTest`
  (permisos = tabla IAM-R11.1), `UserTest` (invitar, cambiar email,
  aceptar invitación, desactivar), VOs.
- **Aplicación:** cada handler con repositorios y puertos en memoria
  (`InMemoryUserRepository`, `FakeInvitationTokens`, `FakeAuthenticator`…).
- **Infraestructura:** `EloquentUserRepositoryTest` (mapeo y roles),
  `SpatieRoleCatalogTest` (por institución), migración de `model_type`.
- **Feature:** toda la matriz de `requirements.md`, sin cambiar su lógica.
- **Arquitectura:** controladores de este módulo en `MIGRATED_CONTROLLERS`.

## Decisiones y alternativas

| Decisión | Alternativa descartada | Motivo |
|----------|------------------------|--------|
| Envolver el password broker de Laravel | Implementar tokens propios | El broker ya hashea, expira, limita y se probó en F1-05; reescribirlo añade riesgo sin beneficio. |
| Mantener Spatie detrás de `RoleCatalog` y del repositorio | Sustituir Spatie | Funciona con *teams* por institución; el puerto permite cambiarlo más adelante. |
| Morph map + migración de datos | Alias de clase `App\Models\User` | El alias deja una dependencia invisible hacia una clase que ya no existe. |
| Acumular violaciones | Lanzar en la primera | Lanzar en la primera cambiaría la UX actual. |
| Sesión HTTP en el controlador | Puerto `Session` | Es puro detalle de entrega; un puerto no aportaría nada. |

## Riesgos y plan de rollback

- **Crítico:** la migración de `model_type`. Hay que probarla sobre una copia
  de la base de staging antes de producción. Su `down()` restaura
  `App\Models\User`.
- **Medio:** orden de los errores de validación. Los errores de forma
  (FormRequest) se evalúan antes que los de negocio, así que un formulario con
  ambos tipos de error mostrará primero los de forma. Hay que verificarlo con
  `UserManagementTest` y, si algún test depende del comportamiento actual,
  documentarlo.
- **Rollback:** revertir el PR **y** ejecutar el `down()` de la migración de
  `model_type`. Es un despliegue en dos pasos que debe quedar en las notas de
  la versión.
