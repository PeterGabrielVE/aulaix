---
spec: 014-superadministracion
estado: aprobada
fecha: 2026-10-26
aprobado: 2026-10-26 (con las verificaciones V1 – V5)
requisitos: requirements.md
depende-de: 001-tenancy, 004-identidad-global, 005-auditoria
adr: 0010-identidad-y-lecturas-de-plataforma
---

# Diseño — Superadministración de plataforma

## Resumen

Un módulo nuevo, `Platform`, con su propio agregado: `Operator`. Tiene
contraseña, TOTP, nivel y estado, y sus invitaciones y códigos de
recuperación. Vive en tablas invisibles mientras haya un plantel activo y
entra por un guard propio en `admin.APP_DOMAIN` (ADR 0010).

El panel no tiene datos de los planteles. Los pide a la capa Application de
cada módulo dueño (A-06):

- **Tenancy:** la lista de planteles y el cambio de estado.
- **IdentityAccess:** las consolidaciones.
- **Audit:** la bitácora de plataforma.
- **Cifras de un plantel:** consultas de agregados de varios módulos, a
  través de un único adaptador que activa ese plantel solo durante la
  lectura (ADR 0010).

Cada acción y consulta queda en la bitácora de plataforma, con el operador
como actor.

## Módulo y capas

```
src/Platform/                                   (nuevo)
├── Domain/
│   ├── Operator.php                 agregado: datos, nivel, estado, TOTP, versión de sesión
│   ├── OperatorId.php, OperatorEmail.php, OperatorRole.php (Support, SuperAdmin), OperatorStatus.php (Pending, Active, Inactive)
│   ├── OperatorInvitation.php       token (hash), propósito (alta o reinscripción del TOTP), 48 h, un uso
│   ├── RecoveryCodes.php            10 códigos con hash, un uso
│   ├── SuperAdminRules.php          servicio: nunca sin Superadministrador activo, nadie se degrada a sí mismo
│   ├── OperatorRepository.php, OperatorInvitationRepository.php
│   └── PlatformRuleViolation.php    (BusinessRuleViolation con campo)
├── Application/
│   ├── Port/
│   │   ├── TotpCodes.php            secreto nuevo, URI y QR, verificación con ventana ±1
│   │   ├── OperatorPasswords.php    hash y verificación (bcrypt)
│   │   ├── OperatorSession.php      operador actual, confirmación reciente del TOTP, inicio y cierre
│   │   ├── OperatorMailer.php       invitaciones y reinscripciones
│   │   ├── InstitutionUsage.php     cifras de un plantel (adaptador que activa el plantel)
│   │   └── InstitutionNotifier.php  aviso a los Administradores al activar o desactivar
│   ├── Access/            LogInOperatorHandler, LogOutOperatorHandler, ConfirmTotpHandler
│   ├── Operators/         InviteOperatorHandler, AcceptInvitationHandler (contraseña + TOTP),
│   │                      ChangeOperatorRoleHandler, SetOperatorActiveHandler,
│   │                      ResetOperatorMfaHandler, RegenerateRecoveryCodesHandler,
│   │                      ListOperatorsQuery, ActiveOperatorQuery (para IdentityAccess)
│   ├── Institutions/      ListInstitutionsQuery, ShowInstitutionUsageQuery, SetInstitutionActiveHandler
│   ├── Consolidation/     ListCasesQuery, PreviewResolutionQuery, ResolveCaseHandler
│   ├── Activity/          ListPlatformActivityQuery
│   ├── PlatformAccess.php           ensureSupport / ensureSuperAdmin (S-04, PLT-NF05)
│   └── PlatformAudit.php            eventos platform.*
└── Infrastructure/
    ├── Persistence/       OperatorModel, EloquentOperatorRepository, EloquentOperatorInvitationRepository
    ├── Google2faTotpCodes.php, LaravelOperatorPasswords.php, LaravelOperatorSession.php
    ├── MailOperatorMailer.php, MailInstitutionNotifier.php
    ├── TenantScopedInstitutionUsage.php   el único adaptador que activa un plantel (ADR 0010)
    └── PlatformServiceProvider.php        bindings, guard «platform», console command
```

Consultas nuevas en los módulos dueños (capa Application):

| Módulo | Consulta o caso de uso | Devuelve o hace | Permiso |
|--------|------------------------|-----------------|---------|
| `Tenancy` | `ListAllInstitutionsQuery(search, status, page)` | Nombre, subdominio, tipo, estado y alta de cada plantel. `institutions` no tiene RLS. Escapa el LIKE (S-09). | La comprueba Platform |
| `Tenancy` | `SetInstitutionStatusHandler(id, active)` | Activa o desactiva un plantel. Es la única vía para cambiar el estado (INS-R01.6). | Solo lo llama Platform; lo vigila un test de arquitectura |
| `Tenancy` | `InstitutionFiles::usedBytes()` | Bytes de la carpeta del plantel activo | — |
| `IdentityAccess` | `MemberCountsQuery` | Miembros activos por rol base del plantel activo | `PlatformReadScope` + arch (V2) |
| `IdentityAccess` | `AdministratorContactsQuery` | Nombre y correo de los Administradores activos del plantel activo, solo para el aviso de PLT-R05.3 | `PlatformReadScope` + arch (V2) |
| `Students` | `ActiveStudentCountQuery` | Número de estudiantes activos | `PlatformReadScope` + arch (V2) |
| `Audit` | `LastActivityQuery` | Fecha de la última entrada de la bitácora del plantel activo | `PlatformReadScope` + arch (V2) |
| `Audit` | `ListPlatformEntriesQuery(filtros, página)` | Entradas de la bitácora de plataforma | La comprueba Platform |
| `AcademicCalendar` | `CalendarContextQuery` (existe) | Año activo | — |

## Modelo de dominio

| Elemento | Tipo | Invariantes / responsabilidad |
|----------|------|-------------------------------|
| `Operator` | Agregado | Nombre, correo único, nivel y estado. Hash de contraseña. Secreto TOTP, solo ya confirmado, y último paso de tiempo usado, para no aceptar el mismo código dos veces (PLT-NF03). Versión de sesión: cambia al desactivar, cambiar de nivel o restablecer el MFA, y así invalida sus sesiones (PLT-R03.4, R03.7). `Pending` hasta aceptar la invitación; solo un `Active` con TOTP puede entrar. |
| `OperatorInvitation` | Entidad | Propósito `onboarding` o `mfa_reset`. Hash del token. Caduca a las 48 h. Se usa una vez (PLT-R03.1, R03.2). |
| `RecoveryCodes` | Value Object | 10 códigos con hash. Usar uno lo marca gastado. Regenerarlos anula los anteriores (PLT-R01.5, R01.10). |
| `SuperAdminRules` | Servicio de dominio | Rechaza un cambio que deje cero Superadministradores activos (PLT-R03.5) y que un Superadministrador se desactive o se degrade a sí mismo (PLT-R03.6). |
| `OperatorRole` | Enum | `support`, `super_admin`. `canManage()` solo para `super_admin`. |

## Casos de uso (Application)

| Caso de uso | Entrada | Salida | Criterios |
|-------------|---------|--------|-----------|
| `LogInOperatorHandler` | correo, contraseña, código (TOTP o de recuperación), IP | sesión iniciada | PLT-R01.2 – R01.5, R01.7, NF03, R07.1 |
| `LogOutOperatorHandler` | — | sesión invalidada | PLT-R01.9 |
| `ConfirmTotpHandler` | código | confirmación reciente | PLT-NF04 |
| `AcceptInvitationHandler` | token, contraseña, código del TOTP nuevo | operador activo, 10 códigos que se muestran una vez | PLT-R01.6, R01.10, R02.1, R03.1, R03.2 |
| `InviteOperatorHandler` | nombre, correo, nivel; actor (operador o consola) | invitación enviada | PLT-R02.1 – R02.3, R03.1, R03.8 |
| `ChangeOperatorRoleHandler`, `SetOperatorActiveHandler` | operador, nivel o estado | — | PLT-R03.3 – R03.6, R03.8 |
| `ResetOperatorMfaHandler` | operador | TOTP y códigos anulados, sesiones cerradas, enlace de reinscripción enviado | PLT-R03.7 |
| `RegenerateRecoveryCodesHandler` | — (el propio operador) | 10 códigos nuevos | PLT-R01.10 |
| `ListInstitutionsQuery` | búsqueda, estado, página | lista | PLT-R04.1, R04.2, NF06 |
| `ShowInstitutionUsageQuery` | plantel | cifras | PLT-R04.3, R04.4, NF01 |
| `SetInstitutionActiveHandler` | plantel, activo, motivo | estado cambiado y aviso | PLT-R05.1 – R05.6 |
| `ListCasesQuery`, `PreviewResolutionQuery`, `ResolveCaseHandler` | caso, decisión, motivo, referencia | resolución | PLT-R06.1 – R06.5 |
| `ActiveOperatorQuery` | correo | ¿operador activo? | PLT-R06.6 |
| `ListPlatformActivityQuery` | filtros | entradas | PLT-R07.2, R07.4 |

Notas de los casos de uso:

- **Inicio de sesión en un solo paso.** Correo, contraseña y código van en
  el mismo formulario y se comprueban juntos. Cualquier fallo da el mismo
  mensaje, «Datos de acceso incorrectos.» (PLT-R01.3). Así no se revela que
  la contraseña era buena antes de pedir el código.
- **Operador sin TOTP.** Ocurre al aceptar la invitación o tras un
  restablecimiento. No puede iniciar sesión: solo puede usar el enlace que
  le llegó por correo, cuya única pantalla es configurar el TOTP (PLT-R01.6,
  R03.7). Con la contraseña sola nadie inscribe un TOTP propio.
- **Bitácora.** `PlatformAudit` registra con origen `platform`, sin IP del
  plantel, y con el operador como actor: nuevo `AuditOrigin::platform(ip,
  operador)` y `AuditContext::actAsPlatformOperator`.
  - Consultas: `platform.institution.viewed`,
    `platform.consolidation_case.viewed`, `platform.activity.viewed`.
  - Accesos: `platform.login.succeeded`, `platform.login.failed` (sin el
    correo tecleado; el operador solo si existe), `platform.logout`.
  - Cambios: operadores, planteles y resoluciones.
  - Nunca contraseñas, secretos, códigos ni tokens (PLT-R07.1, R07.3).
- **Cifras de un plantel** (`TenantScopedInstitutionUsage`). Recuerda el
  plantel activo (normalmente ninguno), activa el pedido, llama a
  `MemberCountsQuery`, `ActiveStudentCountQuery`, `LastActivityQuery`,
  `CalendarContextQuery` y `InstitutionFiles::usedBytes()`, y restaura en
  `finally`. La entrada de bitácora se escribe después, ya fuera del plantel
  (ADR 0010).
- **Desactivar un plantel.** El orden es:
  1. `SetInstitutionStatusHandler` y la bitácora, en una transacción;
  2. después, `MailInstitutionNotifier` lee los Administradores con
     `AdministratorContactsQuery`, con el plantel activado por un momento
     aunque esté inactivo, y les envía el correo por la cola.

  Un fallo del correo no revierte el cambio: queda registrado en el log.
- **Consolidaciones.**
  - `PreviewResolutionQuery` llama a `ResolveConsolidationCaseHandler` con
    `dryRun: true`, y `ResolveCaseHandler` con `dryRun: false`. El
    `operator` es «operador #{id}». Las reglas siguen en IdentityAccess
    (IDG-NF07).
  - El adaptador `ConfigConsolidationOperators` se sustituye por
    `PlatformConsolidationOperators` (IdentityAccess Infrastructure →
    `ActiveOperatorQuery`, A-06). El comando acepta al operador por su
    correo, si es un operador activo.

## Puertos y adaptadores

| Puerto (interfaz) | Capa | Adaptador | Notas |
|-------------------|------|-----------|-------|
| `OperatorRepository`, `OperatorInvitationRepository` | Domain | `Eloquent…` | Tablas con `enableOutsideTenants`. |
| `TotpCodes` | Application | `Google2faTotpCodes` | `pragmarx/google2fa` y QR SVG con `bacon/bacon-qr-code` (ADR 0010). |
| `OperatorPasswords` | Application | `LaravelOperatorPasswords` | `Hasher` de Laravel (bcrypt). |
| `OperatorSession` | Application | `LaravelOperatorSession` | Guard `platform`; regenera la sesión al entrar. Guarda la versión de sesión y la hora del último TOTP. |
| `OperatorMailer` | Application | `MailOperatorMailer` | Correos en cola. El enlace va al dominio del panel. |
| `InstitutionUsage` | Application | `TenantScopedInstitutionUsage` | Único usuario de `TenantActivator` en Platform (test de arquitectura). |
| `InstitutionNotifier` | Application | `MailInstitutionNotifier` | Activa el plantel solo para leer los contactos. |
| `ConsolidationOperators` (IdentityAccess) | Application | `PlatformConsolidationOperators` (nuevo) | Sustituye a `ConfigConsolidationOperators`. |

**Por qué estos puertos:**
- `TotpCodes` y `OperatorSession` dejan probar el inicio de sesión y las
  reglas del MFA sin reloj real, librería ni HTTP.
- `InstitutionUsage` aísla la única activación de plantel, que es lo más
  delicado del diseño.

## Adaptadores de entrada (`app/`)

- **Rutas.** En `routes/web.php`, `Route::domain('admin.'.config('app.domain'))`
  va antes del grupo `{tenant}`, como `www`. Sus middlewares:
  - `platform.idle`: cierra la sesión tras 30 minutos sin actividad
    (PLT-R01.8).
  - `platform.session`: compara la versión de sesión del operador con la
    guardada (PLT-R03.4, R03.7).
  - `platform.auth` (`auth:platform`) y `platform.role:super_admin`
    (PLT-NF05).
  - `platform.recent-totp`: exige un TOTP de los últimos 10 minutos; si no,
    redirige a confirmarlo (PLT-NF04).
  - `throttle`: 5 por minuto por correo e IP en el inicio de sesión
    (PLT-NF03), y también en la aceptación de invitaciones.
- **Controladores** en `app/Http/Controllers/Platform/`: `LoginController`,
  `InvitationController`, `OperatorController`, `InstitutionController`,
  `ConsolidationController`, `ActivityController`,
  `RecoveryCodesController`. Se añaden a `MIGRATED_CONTROLLERS`.
- **Páginas Inertia** en `resources/js/Pages/Platform/`, con su propio
  layout `PlatformLayout.vue`: no comparte el menú de los planteles y cumple
  NAV-NF04 y NF05 (PLT-NF07).
- **Datos compartidos.** `HandleInertiaRequests` no envía datos de plantel
  en este dominio. Envía `platform.operator`: nombre y nivel, nunca el
  modelo entero (S-10).
- **Consola.** `platform:create-super-admin {nombre} {correo}` (PLT-R02).
  `identity:resolve-consolidation-case` ahora valida al operador contra
  Platform.
- **Configuración.**
  - `config/auth.php` añade el guard `platform` con el proveedor
    `operators`.
  - Desaparecen `identity.consolidation_operators` y
    `IDENTITY_CONSOLIDATION_OPERATORS` de `.env.example`.

## Datos

Migración `create_platform_operator_tables`:

- **`platform_operators`:**
  - `id`, `name`, `email` (único);
  - `password` (nulo hasta aceptar);
  - `role`, `status` (CHECK con los valores de los enums);
  - `totp_secret` (texto cifrado, nulo), `totp_last_step` (nulo);
  - `session_version` (entero), `timestamps`.
- **`platform_operator_invitations`:**
  - `id`, `operator_id` (FK), `purpose` (CHECK);
  - `token_hash` (único), `expires_at`, `used_at`;
  - `invited_by` (operador o nulo si fue por consola), `timestamps`.
- **`platform_operator_recovery_codes`:** `id`, `operator_id` (FK),
  `code_hash`, `used_at`.
- **RLS.** Las tres tablas usan `RowLevelSecurity::enableOutsideTenants`:
  invisibles mientras haya un plantel activo, como la bitácora de
  plataforma. `TenantTableTest` las clasifica como tablas de plataforma.
- **Sin migración de datos.** El rollback (`down`) borra las tres tablas.

`AuditOrigin` gana el tipo `platform`, sin cambio de esquema: `origin` y
`operator` ya existen en `platform_audit_entries`.

## Patrones aplicados

| Patrón | Fuerza que resuelve (C-05) |
|--------|----------------------------|
| Aggregate (`Operator`) | El estado, el nivel, el TOTP y la versión de sesión cambian juntos. Las reglas («solo activo con TOTP entra», «un código una vez») viven en un solo sitio. |
| Domain Service (`SuperAdminRules`) | La regla «nunca sin Superadministrador» mira a todos los operadores, no a uno. |
| Adapter único (`TenantScopedInstitutionUsage`) | Concentra en un solo punto revisable la activación temporal de un plantel (ADR 0010). |
| Adapter (`PlatformConsolidationOperators`) | IdentityAccess pregunta quién es operador sin conocer Platform por dentro (A-06). |

## Modelo de amenazas (STRIDE)

| Amenaza | Vector | Mitigación | Verificado por |
|---------|--------|------------|----------------|
| **S**poofing | Robo de contraseña de un operador; phishing; reutilizar un TOTP; cuenta de un plantel usada en el panel | TOTP obligatorio en el mismo formulario y paso de tiempo de un solo uso. Guard y tabla propios. Límite de 5 intentos por minuto. Mensaje único. | `OperatorLoginTest` (mensaje único, código reutilizado, desactivado, cuenta de plantel rechazada); unit de `Operator` |
| **S**poofing | Inscribir un TOTP propio conociendo solo la contraseña | Sin TOTP no se puede iniciar sesión: solo el enlace de un uso enviado por correo permite inscribirlo. | `OperatorInvitationTest`; `OperatorMfaResetTest` |
| **T**ampering | Cambiar de nivel, desactivar un plantel o restablecer el MFA sin ser Superadministrador | Middleware de nivel y `PlatformAccess::ensureSuperAdmin` en el caso de uso, más un TOTP de los últimos 10 minutos. | `PlatformAuthorizationTest` (403 por ruta y por caso de uso); `RecentTotpTest` |
| **R**epudiation | Un operador niega haber desactivado un plantel o consultado datos | Toda acción y consulta queda en la bitácora de plataforma, inmutable (SPEC-005), con operador e IP. | `PlatformAuditTest` |
| **I**nformation disclosure | El panel muestra datos personales de un plantel; lecturas sin RLS; el uso se escribe en la bitácora del plantel | Solo consultas de agregados, que devuelven números. La activación es acotada y el plantel se restaura en `finally`. Ni `BYPASSRLS` ni `SECURITY DEFINER`. Las tablas de operadores son invisibles con un plantel activo. | `InstitutionUsageTest` (solo cifras, plantel restaurado, también con excepción); `PlatformIsolationTest` (`--group=rls`); arch: solo `TenantScopedInstitutionUsage` usa `TenantActivator` |
| **I**nformation disclosure | Secretos en la bitácora, en los props o en los logs | Secreto TOTP cifrado; códigos y tokens con hash; DTO explícitos (S-10). | `PlatformAuditTest` (sin secretos); `OperatorPersistenceTest` (secreto cifrado en la base de datos) |
| **D**enial of service | Fuerza bruta del inicio de sesión | `throttle` por correo e IP | `OperatorLoginTest` (sexto intento: 429) |
| **E**levation of privilege | Un Soporte se convierte en Superadministrador; el último Superadministrador se degrada; sesiones viejas tras cambiar de nivel | `SuperAdminRules`; versión de sesión; doble capa de autorización. | Unit de `SuperAdminRules`; `OperatorManagementTest` (sesión cerrada al cambiar de nivel) |

## Errores

| Excepción de dominio | HTTP | Mensaje al usuario |
|----------------------|------|--------------------|
| `InvalidOperatorCredentials` | 422 | «Datos de acceso incorrectos.» |
| `PlatformRuleViolation` (último Superadministrador, sobre sí mismo, correo repetido) | 422 | Mensaje de la regla en su campo |
| `OperatorInvitationRejected` (caducado, usado o inexistente) | 404 | «El enlace no es válido o ha caducado.» |
| `AuthorizationDenied` | 403 | Respuesta estándar |
| `NotFound` (plantel u operador) | 404 | Respuesta estándar |
| Reglas de consolidación (IdentityAccess) | 422 | Las de IDG-R11.5 |

Se registran en `bootstrap/app.php` (C-06).

## Estrategia de pruebas

- **Dominio (unit, `tests/Unit/Platform/Domain`):**
  - `Operator`: estados, TOTP de un solo uso, versión de sesión;
  - `RecoveryCodes`;
  - `OperatorInvitation`: 48 h y un solo uso;
  - `SuperAdminRules`.
- **Aplicación (unit, fakes de `TotpCodes`, `OperatorSession`, reloj y
  repositorios):** inicio de sesión, invitación y aceptación,
  restablecimiento del MFA, gestión de operadores, desactivación con aviso,
  consolidaciones (vista previa y resolución) y `PlatformAudit`.
- **Infraestructura (PostgreSQL):**
  - repositorios, con el secreto cifrado;
  - RLS de las tablas de operadores, invisibles con un plantel activo
    (`--group=rls`);
  - `TenantScopedInstitutionUsage`: cifras exactas y plantel restaurado,
    también si algo falla;
  - las consultas de agregados nuevas;
  - `ListAllInstitutionsQuery`;
  - `Google2faTotpCodes` contra vectores de RFC 6238.
- **Feature (HTTP, `admin.aulaix.test`):**
  - inicio de sesión: mensaje único, 429, desactivado, cuenta de plantel;
  - sesión: inactividad y cierre;
  - autorización por nivel y TOTP reciente;
  - invitaciones y restablecimiento;
  - planteles y su uso;
  - activar y desactivar, con correo y 404 del subdominio;
  - consolidaciones;
  - bitácora;
  - el comando `platform:create-super-admin` y el de consolidación con
    operadores de Platform.
  - `ConsolidationCommandsTest` cambia solo su preparación: crea un
    operador en vez de la lista del entorno, como pide PLT-R06.6.
- **Navegador:** axe y 360 px en el inicio de sesión, la lista de
  planteles y el detalle de uno (PLT-NF07).
- **Rendimiento (`perf`):** la lista con 500 planteles y el uso de uno
  (PLT-NF06).

## Verificaciones exigidas en la aprobación (2026-10-26)

La aprobación del diseño quedó sujeta a cinco garantías. Cada una tiene su
mecanismo y su test, y es una tarea propia en `tasks.md`.

### V1 — El plantel activado temporalmente no se filtra entre operaciones

- **Mecanismo.** `TenantScopedInstitutionUsage::read(institutionId,
  callable)`:
  - guarda el estado anterior: plantel activo o ninguno;
  - activa el pedido con `TenantActivator`;
  - ejecuta la lectura dentro de una transacción de solo lectura (`SET
    TRANSACTION READ ONLY`);
  - restaura el estado en `finally`, ocurra lo que ocurra.

  La variable de RLS se fija dentro de esa transacción, así que un rollback
  también la deshace. Ninguna escritura es posible durante la lectura.
- **Después de cada lectura**, el adaptador comprueba que no queda ningún
  plantel activo (o que sigue el que había). Si no, lanza `LogicException`:
  falla de forma ruidosa, sin seguir con un plantel equivocado.
- **Tests:**
  - `TenantScopedInstitutionUsageTest`:
    - «restaura el estado sin plantel después de leer»;
    - «restaura el estado aunque la lectura lance una excepción»;
    - «dos lecturas seguidas de planteles distintos no se mezclan»;
    - «la lectura no puede escribir»;
    - «la bitácora del operador va a la plataforma y no al plantel leído»;
  - arch: en Platform, solo este adaptador y `MailInstitutionNotifier` usan
    `TenantActivator`.

### V2 — Las consultas de agregados mantienen su autorización

Las consultas de agregados nuevas (`MemberCountsQuery`,
`ActiveStudentCountQuery`, `LastActivityQuery`,
`AdministratorContactsQuery`) no usan los permisos de plantel, porque el
operador no los tiene. A cambio tienen tres capas:

1. **Quién las llama.** Un test de arquitectura impide usarlas fuera de
   `AulaX\Platform\Infrastructure`. Ningún controlador de plantel ni otro
   módulo puede llegar a ellas.
2. **Quién lo pide.** Los casos de uso de Platform que las disparan
   (`ShowInstitutionUsageQuery`, `SetInstitutionActiveHandler`) comprueban
   antes el nivel del operador con `PlatformAccess` (S-04).
3. **Desde dónde.** Cada consulta exige una prueba de que la llamada viene
   de una lectura de plataforma: un `PlatformReadScope` que solo crea
   `TenantScopedInstitutionUsage` dentro de su activación. Fuera de una
   lectura de plataforma, lanzan `AuthorizationDenied`.

   El value object vive en `Shared` (`Shared\Application\PlatformReadScope`)
   y se crea con un constructor interno. Así ningún módulo depende de
   Platform.

Además, solo devuelven números, salvo `AdministratorContactsQuery`, que
devuelve nombre y correo de los Administradores activos para el aviso de
PLT-R05.3, y nunca llega a la pantalla.

- **Tests:**
  - arch: las cuatro consultas, solo desde `AulaX\Platform\Infrastructure`;
  - por cada consulta: «se niega fuera de una lectura de plataforma»;
  - `PlatformAuthorizationTest`: «un operador de Soporte ve el uso; sin
    sesión de operador, 302 al inicio de sesión»; «un miembro de un plantel
    no llega a ninguna ruta del panel»;
  - `InstitutionUsageTest`: «el uso de un plantel no contiene nombres,
    correos ni cédulas».

### V3 — Procedimiento seguro y auditable para quitar `IDENTITY_CONSOLIDATION_OPERATORS`

1. **Primer Superadministrador.** Se crea con `platform:create-super-admin`
   desde el servidor. Queda en la bitácora de plataforma con origen
   `console`, el nombre del usuario del sistema que lo ejecuta y el correo
   del nuevo operador. Nunca la contraseña ni el token.
2. **Resto de operadores de consolidación.** Los invita el
   Superadministrador desde el panel: `platform.operator.invited`, con el
   actor. Cada uno acepta con contraseña y TOTP:
   `platform.operator.activated`.
3. **Comando de consolidación.** Solo acepta como `--operator` el correo de
   un operador activo. Un rechazo se registra en la bitácora de plataforma
   (`identity.consolidation.refused`, con el operador indicado y el
   motivo), igual que una resolución.
4. **Corte.** La variable y la configuración desaparecen en el mismo PR; no
   hay convivencia de las dos listas. En las notas del PR va el
   procedimiento de despliegue: crear el Superadministrador, invitar a los
   operadores y verificar con `--dry-run` antes de la primera resolución
   real.

- **Tests:**
  - `PlatformBootstrapCommandTest`: «crea el Superadministrador pendiente,
    envía el enlace y lo registra con origen consola»; «rechaza un correo
    ya usado»;
  - `ConsolidationCommandsTest`: «acepta solo a un operador activo de
    Platform»; «rechaza a uno inactivo, pendiente o desconocido y lo
    registra»;
  - `ConfigTest`: «`identity.consolidation_operators` ya no existe».

### V4 — Enlaces de invitación y de reinscripción del TOTP

| Garantía | Mecanismo |
|----------|-----------|
| Caducidad | `expires_at` = emisión + 48 h; comprobada al abrir el enlace y otra vez al enviar el formulario. |
| Uso único | Se marca `used_at` en la misma transacción que activa el operador, con `UPDATE … WHERE used_at IS NULL`, de modo que dos envíos simultáneos no pasan los dos. |
| Invalidación | Emitir un enlace nuevo de un operador anula sus enlaces pendientes. Desactivar al operador anula todos los suyos. Restablecer el MFA anula el de alta si seguía pendiente. |
| Secreto del token | Solo se guarda su SHA-256; el token sale una vez, en el correo. |
| Respuesta | Un enlace caducado, usado, anulado o inexistente da la misma página y el mismo 404, sin decir cuál de los casos es. |

- **Tests (`OperatorInvitationTest`, `OperatorMfaResetTest`):**
  - «caduca a las 48 h, no a las 47 h 59 min»;
  - «no se puede usar dos veces, ni con dos envíos simultáneos»;
  - «un enlace nuevo anula el anterior»;
  - «desactivar al operador anula su enlace»;
  - «el restablecimiento cierra sus sesiones y deja como única salida el
    enlace»;
  - «caducado, usado, anulado e inexistente responden igual»;
  - «el token no se guarda en claro».

### V5 — Errores de autenticación uniformes

- **Mismo mensaje y mismo estado** (422, «Datos de acceso incorrectos.»)
  para todos los fallos:
  - correo inexistente;
  - contraseña incorrecta;
  - código incorrecto;
  - código ya usado;
  - código de recuperación gastado;
  - operador desactivado o pendiente;
  - cuenta de un plantel.
- **Mismo trabajo.** Si el correo no existe, se compara la contraseña contra
  un hash ficticio, para no responder más rápido. El TOTP se comprueba
  siempre, aunque la contraseña ya haya fallado.
- **Misma bitácora.** `platform.login.failed` sin el correo tecleado y sin
  la causa. La causa solo queda en el log de la aplicación, con el id del
  operador si existe (S-07).
- **El límite de intentos** cuenta todos los fallos por igual.
- **Tests (`OperatorLoginTest`):**
  - «los siete fallos dan la misma respuesta y el mismo evento»;
  - «un correo inexistente también compara una contraseña»;
  - «el sexto intento en un minuto responde 429, sea cual sea el fallo».

## Decisiones y alternativas

| Decisión | Alternativa descartada | Motivo |
|----------|------------------------|--------|
| ADR 0010: identidad de plataforma aparte | Marca de operador en la cuenta global | Separación de identidades (roadmap) |
| ADR 0010: activación temporal y acotada para leer el uso | `BYPASSRLS`, `SECURITY DEFINER`, cifras precalculadas | S-03, ADR 0006; las cifras precalculadas se dejan para cuando haga falta |
| Inicio de sesión en un solo paso (correo, contraseña y código) | Contraseña y después el código | Con dos pasos se revelaría que la contraseña era correcta (PLT-R01.3) |
| Sin TOTP, solo se entra por el enlace de reinscripción | Entrar con la contraseña e inscribir el TOTP | Quien tuviera la contraseña podría inscribir su propio TOTP |
| Versión de sesión en el operador | Borrar sus filas de `sessions` | La tabla `sessions` guarda el id del guard `web`, no del `platform` |
| El correo de desactivación va después de la transacción | Dentro de la transacción | Un fallo del correo no debe deshacer el bloqueo |

## Cambios en otras specs y módulos

- **SPEC-004 (IdentityAccess):**
  - IDG-R11.3, sustituido por PLT-R06.6;
  - `PlatformConsolidationOperators`;
  - `MemberCountsQuery` y `AdministratorContactsQuery`;
  - desaparece `identity.consolidation_operators`.
- **SPEC-001 y 006 (Tenancy):** `ListAllInstitutionsQuery`,
  `SetInstitutionStatusHandler` e `InstitutionFiles::usedBytes()`.
- **SPEC-005 (Audit):** `AuditOrigin::platform`,
  `AuditContext::actAsPlatformOperator`, `LastActivityQuery` y
  `ListPlatformEntriesQuery`; se cierra la dependencia D2.
- **SPEC-009 (Students):** `ActiveStudentCountQuery`.
- **Tests de arquitectura:**
  - en Platform, solo `TenantScopedInstitutionUsage` y
    `MailInstitutionNotifier` usan `TenantActivator`;
  - `SetInstitutionStatusHandler` y `AdministratorContactsQuery`, solo
    desde Platform.

## Riesgos y plan de rollback

- **Activar un plantel en una petición del panel.** Si el plantel no se
  restaura, la bitácora del operador iría al plantel, o una consulta
  siguiente leería sus datos. Mitigación:
  - `finally` y un test que lanza una excepción a mitad de la lectura;
  - la bitácora se escribe después de restaurar;
  - la variable de RLS va por conexión, la misma nota de la revisión de
    seguridad: el panel no debe ir detrás de un pool en modo transacción.
- **Pérdida del único dispositivo TOTP del único Superadministrador.**
  Quedan los códigos de recuperación y, como último recurso, el comando
  `platform:create-super-admin` con acceso al servidor. Se documenta como
  procedimiento de soporte.
- **Dependencias nuevas de producción** (`pragmarx/google2fa`,
  `bacon/bacon-qr-code`): pasan por `composer audit` en CI (S-11).
- **Fin de `IDENTITY_CONSOLIDATION_OPERATORS`.** Al desplegar, los
  operadores de soporte tienen que darse de alta en Platform antes de usar
  el comando. Va en las notas del PR.
- **Rollback:** revertir el PR y ejecutar el `down` de la migración (borra
  las tres tablas). La bitácora conserva las entradas `platform.*`, porque
  es inmutable. No hay datos de planteles afectados.

## Notas de implementación

### PR A

- **`TenantTableTest` no cambia.** Solo vigila tablas con `institution_id`,
  y las de operadores no lo tienen. Su aislamiento lo prueba
  `OperatorTablesTest` (`group=rls`).
- **Columna `revoked`** en `platform_operator_invitations`: un enlace nuevo
  y la desactivación anulan los pendientes (V4).
- **Dos puertos más:**
  - `PendingTotpSecrets`: el secreto mostrado con el QR vive cifrado en la
    sesión hasta que el primer código lo confirma; solo entonces se guarda.
  - `SecurityLog`: la causa de un acceso fallido va al log de la
    aplicación, con el id del operador, y no a la bitácora (V5).
- **Un solo middleware `platform`**, en lugar de tres. Llama a
  `CurrentOperatorQuery`, que en un único sitio comprueba que el operador
  sigue activo, que la versión de sesión coincide y que han pasado menos de
  30 minutos sin actividad. Si algo falla, cierra la sesión; si no, nombra
  al operador como actor de la bitácora.
- **Eventos:** el cierre de sesión es `platform.session.ended`, porque los
  tipos son de tres partes. Un acceso fallido usa siempre el sujeto
  `platform_login/panel`. La invitación registra el correo y el nivel del
  invitado (V3).
- **QR como imagen** (`data:image/svg+xml`) en un `<img>`, nunca insertado
  como marcado: el frontend no tiene ningún `v-html`.
- **Limitadores con nombre:**
  - `platform-login`: 5 por minuto por correo e IP (PLT-NF03).
  - `platform-links`: 10 por minuto por IP, para abrir y aceptar enlaces.

### PR B

- **Middlewares** `platform.super` (`RequireSuperAdmin`) y
  `platform.recent-totp` (`RequireRecentTotp`). La comprobación de los 10
  minutos vive en `RecentTotpQuery`. Si falta un TOTP reciente, se guarda
  la página de origen y se vuelve a ella tras confirmar el código en
  `/confirmar-codigo`.
- **El código de confirmación cuenta como usado,** igual que en el inicio de
  sesión (PLT-NF03).
- **Nadie restablece su propio segundo factor:** lo hace otro
  Superadministrador. Así se cumple PLT-R03.7 («de otro operador») y nadie
  se queda fuera del panel por error.
- **Regenerar los códigos de recuperación** también exige un TOTP reciente.
- **Ver la lista de operadores no exige TOTP reciente;** cualquier cambio sí.
