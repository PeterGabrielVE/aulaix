---
spec: 014-superadministracion
estado: implementando
fecha: 2026-10-26
aprobado: 2026-10-26
diseño: design.md
depende-de: 001-tenancy, 004-identidad-global, 005-auditoria (implementadas); ADR 0010 (aceptado)
---

# Tareas — Superadministración de plataforma

Regla transversal: al cerrar cada tarea, `php artisan test --compact`
(incluidos `--group=rls` y la suite `Architecture`) sigue en verde. TDD
(Q-02): primero el test en rojo, por el motivo correcto. Al cerrar una tarea,
sus tests se añaden a la matriz de `requirements.md`. Las verificaciones V1
a V5 del diseño son condición de la aprobación: cada una tiene su tarea.

Cuatro PRs, cada uno usable por sí mismo:

- **PR A (fases 1 – 3):** operadores, inicio de sesión con TOTP, invitación
  y primer Superadministrador.
- **PR B (fase 4):** gestión de operadores, restablecimiento del MFA,
  códigos de recuperación y TOTP reciente.
- **PR C (fases 5 – 6):** planteles, su uso con la lectura acotada, y
  activar y desactivar.
- **PR D (fases 7 – 8):** consolidaciones en el panel, corte de
  `IDENTITY_CONSOLIDATION_OPERATORS`, bitácora de plataforma, accesibilidad
  y rendimiento.

## PR A — Identidad de plataforma

### Fase 1 — Dominio (PHP puro)

- [x] **T-01** — Esqueleto del módulo `src/Platform` (ServiceProvider
  registrado):
  - `OperatorId`, `OperatorEmail`, `OperatorRole` (`support`,
    `super_admin`), `OperatorStatus` (`pending`, `active`, `inactive`);
  - agregado `Operator`:
    - solo un `active` con TOTP confirmado puede entrar;
    - el TOTP se confirma una vez;
    - un paso de tiempo usado no se acepta otra vez;
    - la versión de sesión sube al desactivar, cambiar de nivel o
      restablecer el MFA.
  - Criterios: PLT-R01.4, R01.6, NF03 (código de un solo uso)
  - Test: `tests/Unit/Platform/Domain/OperatorTest.php`
- [x] **T-02** — `OperatorInvitation` y `RecoveryCodes`:
  - invitación con propósito (`onboarding` o `mfa_reset`), caducidad a las
    48 h y un solo uso;
  - 10 códigos de recuperación con hash, cada uno de un solo uso;
    regenerarlos anula los anteriores.
  - Criterios: PLT-R01.5, R01.10, R03.2 (V4: caducidad y uso único en el
    dominio)
  - Test: `tests/Unit/Platform/Domain/OperatorInvitationTest.php`,
    `tests/Unit/Platform/Domain/RecoveryCodesTest.php`

### Fase 2 — Datos e infraestructura

- [x] **T-03** — Migración `create_platform_operator_tables`:
  - tablas `platform_operators`, `platform_operator_invitations` y
    `platform_operator_recovery_codes`, con sus CHECK;
  - `RowLevelSecurity::enableOutsideTenants` en las tres;
  - `TenantTableTest` las clasifica como tablas de plataforma;
  - prueba de `up` y `down`.
  - Criterios: PLT-NF01 (las tablas son invisibles con un plantel activo),
    NF02
  - Test: `tests/Feature/Platform/Infrastructure/OperatorTablesTest.php`
    (`group=rls`: invisibles y no escribibles con un plantel activo;
    rollback)
- [x] **T-04** — Persistencia:
  - `OperatorModel` con el secreto TOTP cifrado (cast `encrypted`);
  - `EloquentOperatorRepository` y `EloquentOperatorInvitationRepository`.
    El uso único es atómico (`UPDATE … WHERE used_at IS NULL`), y emitir un
    enlace nuevo anula los pendientes.
  - Criterios: PLT-NF02, R03.2 (V4: uso único simultáneo, anulación, token
    solo con hash)
  - Test: `tests/Feature/Platform/Infrastructure/OperatorPersistenceTest.php`
- [x] **T-05** — Puertos y adaptadores de acceso:
  - **TOTP:** `TotpCodes` → `Google2faTotpCodes`, con las dependencias
    `pragmarx/google2fa` y `bacon/bacon-qr-code` (ADR 0010);
  - **contraseñas:** `OperatorPasswords` → `LaravelOperatorPasswords`;
  - **sesión:** `OperatorSession` → `LaravelOperatorSession`, con el guard
    `platform` y el proveedor `operators` en `config/auth.php`;
  - **bitácora:** `AuditOrigin::platform` y
    `AuditContext::actAsPlatformOperator`.
  - Criterios: PLT-R01.2, R07.3
  - Test: `tests/Feature/Platform/Infrastructure/Google2faTotpCodesTest.php`
    (vectores de RFC 6238, ventana ±1);
    `tests/Feature/Audit/Infrastructure/PlatformOriginTest.php`

### Fase 3 — Acceso, invitación y arranque

- [x] **T-06** — `LogInOperatorHandler` y `LogOutOperatorHandler`:
  - correo, contraseña y código en un solo paso;
  - código TOTP o de recuperación, y aviso de cuántos códigos quedan;
  - errores uniformes (V5): mismo resultado, hash ficticio si el correo no
    existe, el TOTP se comprueba siempre;
  - `platform.login.succeeded` y `platform.login.failed` en la bitácora,
    sin el correo tecleado ni la causa.
  - Criterios: PLT-R01.2 – R01.5, R01.9, R07.1, R07.3
  - Test: `tests/Unit/Platform/Application/LogInOperatorTest.php`
- [x] **T-07** — `AcceptInvitationHandler` e `InviteOperatorHandler`:
  - la aceptación fija la contraseña y confirma el TOTP con un código;
  - se muestran los 10 códigos una sola vez;
  - el operador queda activo y la bitácora lo registra;
  - el actor de la invitación es un operador o la consola.
  - Criterios: PLT-R01.6, R01.10, R02.1, R03.1, R03.2
  - Test: `tests/Unit/Platform/Application/OperatorInvitationTest.php`
- [x] **T-08** — Rutas de `admin.APP_DOMAIN`, antes del grupo `{tenant}`, con
  sus middlewares:
  - `platform.auth`, `platform.session` (versión de sesión) y
    `platform.idle` (30 minutos);
  - `throttle` de 5 por minuto por correo e IP en el inicio de sesión y en
    la aceptación;
  - `Platform/LoginController` e `InvitationController`;
  - `PlatformLayout.vue` y las páginas de inicio de sesión, invitación
    (contraseña, QR y códigos) y una portada vacía;
  - datos compartidos `platform.operator` (nombre y nivel).
  - Criterios: PLT-R01.1, R01.2, R01.3, R01.4, R01.5, R01.6, R01.7,
    R01.8, R01.9, R01.10, R03.2, NF03, NF05
  - Test: `tests/Feature/Platform/OperatorLoginTest.php`:
    - los siete fallos dan la misma respuesta y el mismo evento (V5);
    - un correo inexistente también compara una contraseña (V5);
    - el sexto intento responde 429;
    - un operador desactivado no entra;
    - las credenciales de un plantel no abren el panel, ni las de un
      operador un plantel;
    - la sesión caduca tras 30 minutos sin actividad;
    - la cookie es solo de `admin`.

    `tests/Feature/Platform/OperatorInvitationTest.php`:
    - caduca a las 48 h y no antes;
    - doble uso, también simultáneo;
    - un enlace nuevo anula el anterior;
    - caducado, usado, anulado e inexistente responden igual (V4).
- [x] **T-09** — Comando `platform:create-super-admin {nombre} {correo}`:
  operador pendiente, enlace por correo y bitácora con origen consola,
  usuario del sistema y correo; rechaza un correo ya usado (V3, paso 1).
  - Criterios: PLT-R02.1 – R02.3
  - Test: `tests/Feature/Platform/PlatformBootstrapCommandTest.php`

## PR B — Gestión de operadores

### Fase 4 — Operadores, MFA y TOTP reciente

- [ ] **T-10** — `SuperAdminRules` (nunca sin un Superadministrador activo;
  nadie sobre sí mismo), `PlatformAccess` (`ensureSupport`,
  `ensureSuperAdmin`) y los casos de uso `ChangeOperatorRoleHandler`,
  `SetOperatorActiveHandler` y `ListOperatorsQuery`. Desactivar a un
  operador anula sus enlaces pendientes (V4).
  - Criterios: PLT-R03.3 – R03.6, R03.8, NF05
  - Test: `tests/Unit/Platform/Domain/SuperAdminRulesTest.php`;
    `tests/Unit/Platform/Application/OperatorManagementTest.php`
- [ ] **T-11** — `ResetOperatorMfaHandler`:
  - anula el TOTP y los códigos y sube la versión de sesión;
  - anula los enlaces pendientes y envía uno de reinscripción;
  - lo registra en la bitácora.

  Además, `RegenerateRecoveryCodesHandler` para el propio operador.
  - Criterios: PLT-R03.7, R01.10
  - Test: `tests/Unit/Platform/Application/OperatorMfaResetTest.php`
- [ ] **T-12** — `ConfirmTotpHandler` y el middleware `platform.recent-totp`
  (un TOTP de los últimos 10 minutos para las acciones sensibles).
  - Criterios: PLT-NF04
  - Test: `tests/Feature/Platform/RecentTotpTest.php`
- [ ] **T-13** — HTTP y pantallas: `OperatorController` (invitar, nivel,
  activar, restablecer el MFA), detrás de `platform.role:super_admin` y
  `platform.recent-totp`, y `RecoveryCodesController`. Las sesiones de un
  operador cambiado se cierran en su siguiente petición.
  - Criterios: PLT-R03.1, R03.3 – R03.8, R01.10, NF04, NF05
  - Test: `tests/Feature/Platform/OperatorManagementTest.php`;
    `tests/Feature/Platform/OperatorMfaResetTest.php`:
    - el restablecimiento cierra sus sesiones y deja como única salida el
      enlace;
    - desactivar al operador anula su enlace (V4);
    - un Soporte recibe 403.

## PR C — Planteles

### Fase 5 — Consultas de los módulos dueños y lectura acotada

- [ ] **T-14** — `Shared\Application\PlatformReadScope` (V2) y
  `TenantScopedInstitutionUsage` (V1):
  - guarda el estado anterior y activa el plantel;
  - lee en una transacción de solo lectura;
  - restaura en `finally` y comprueba después que no queda un plantel
    activo;
  - crea el `PlatformReadScope` solo dentro de la lectura.

  Regla de arquitectura: en Platform, solo este adaptador y
  `MailInstitutionNotifier` usan `TenantActivator`.
  - Criterios: PLT-NF01
  - Test: `tests/Feature/Platform/Infrastructure/TenantScopedInstitutionUsageTest.php`:
    - restaura tras leer y tras una excepción;
    - dos lecturas seguidas no se mezclan;
    - la lectura no puede escribir;
    - la bitácora del operador va a la plataforma (V1);

    y la regla de arquitectura en `tests/Architecture/LayersTest.php`.
- [ ] **T-15** — Consultas de agregados, cada una exigiendo
  `PlatformReadScope` (V2):
  - `MemberCountsQuery` y `AdministratorContactsQuery` (IdentityAccess);
  - `ActiveStudentCountQuery` (Students);
  - `LastActivityQuery` (Audit);
  - `InstitutionFiles::usedBytes()` (Tenancy).

  Regla de arquitectura: solo desde `AulaX\Platform\Infrastructure`.
  - Criterios: PLT-R04.3, R04.4, NF01
  - Test: `tests/Feature/Platform/Infrastructure/AggregateQueriesTest.php`
    (cifras exactas, y cada consulta se niega fuera de una lectura de
    plataforma); `tests/Architecture/LayersTest.php`
- [ ] **T-16** — `ListAllInstitutionsQuery` y `SetInstitutionStatusHandler`
  (Tenancy). Regla de arquitectura: `SetInstitutionStatusHandler`, solo
  desde Platform.
  - Criterios: PLT-R04.1, R04.2, R05.1, R05.4, R05.5
  - Test: `tests/Feature/Tenancy/Infrastructure/InstitutionAdministrationTest.php`

### Fase 6 — Casos de uso, HTTP y pantallas

- [ ] **T-17** — `ListInstitutionsQuery`, `ShowInstitutionUsageQuery` y
  `SetInstitutionActiveHandler`:
  - nivel del operador comprobado;
  - motivo obligatorio para desactivar;
  - transacción con la bitácora, y aviso después con
    `MailInstitutionNotifier`;
  - `platform.institution.viewed` en la bitácora.
  - Criterios: PLT-R04.1 – R04.4, R05.1 – R05.6, R07.1
  - Test: `tests/Unit/Platform/Application/InstitutionsTest.php`
- [ ] **T-18** — `InstitutionController` y sus pantallas (lista con búsqueda
  y filtro; detalle con el uso; activar y desactivar con motivo, detrás de
  `platform.recent-totp`).
  - Criterios: PLT-R04.1 – R04.4, R05.1 – R05.6, NF04
  - Test: `tests/Feature/Platform/InstitutionsTest.php`:
    - el uso no contiene nombres, correos ni cédulas (V2);
    - al desactivar, el subdominio responde 404 y los Administradores
      reciben el correo;
    - el motivo es obligatorio;
    - Soporte recibe 403 al desactivar;
    - al reactivar, el subdominio vuelve a responder.

    `tests/Feature/Platform/PlatformAuthorizationTest.php`:
    - un miembro de un plantel no llega al panel;
    - sin sesión de operador, redirige al inicio de sesión.
- [ ] **T-19** — Aislamiento: con varios planteles, el uso de uno solo
  cuenta los suyos, y el panel nunca deja un plantel activo al terminar la
  petición.
  - Criterios: PLT-NF01
  - Test: `tests/Feature/Platform/PlatformIsolationTest.php` (`group=rls`)

## PR D — Consolidaciones y bitácora

### Fase 7 — Consolidaciones y corte de la configuración

- [ ] **T-20** — `ListCasesQuery`, `PreviewResolutionQuery` (`dryRun`) y
  `ResolveCaseHandler`, sobre los casos de uso de IdentityAccess, con el
  operador de la sesión y `platform.consolidation_case.viewed`.
  `ConsolidationController` y sus pantallas: lista, detalle, vista previa y
  confirmación.
  - Criterios: PLT-R06.1 – R06.5, R07.1
  - Test: `tests/Unit/Platform/Application/ConsolidationTest.php`;
    `tests/Feature/Platform/ConsolidationTest.php`
- [ ] **T-21** — Corte de `IDENTITY_CONSOLIDATION_OPERATORS` (V3):
  - `ActiveOperatorQuery` y `PlatformConsolidationOperators` sustituyen a
    `ConfigConsolidationOperators`;
  - el comando registra cada rechazo (`identity.consolidation.refused`);
  - desaparecen `identity.consolidation_operators` y la variable de
    `.env.example`;
  - se actualiza IDG-R11.3 en `specs/004-identidad-global/requirements.md`.

  `ConsolidationCommandsTest` solo cambia su preparación.
  - Criterios: PLT-R06.6
  - Test: `tests/Feature/IdentityAccess/ConsolidationCommandsTest.php`:
    - acepta solo a un operador activo de Platform;
    - rechaza a uno inactivo, pendiente o desconocido y lo registra.

    `tests/Feature/Platform/ConsolidationOperatorsConfigTest.php`: la
    configuración vieja ya no existe.

### Fase 8 — Bitácora de plataforma, accesibilidad y rendimiento

- [ ] **T-22** — `ListPlatformEntriesQuery` (Audit), `ListPlatformActivityQuery`
  y `ActivityController`, con filtros por fecha, operador y tipo. Nunca la
  bitácora de un plantel. Cierra la dependencia D2 de SPEC-005.
  - Criterios: PLT-R07.2, R07.4
  - Test: `tests/Feature/Platform/PlatformActivityTest.php`
- [ ] **T-23** — Bitácora completa de las acciones y consultas del panel,
  sin secretos (contraseñas, secreto TOTP, códigos ni tokens).
  - Criterios: PLT-R07.1, R07.3
  - Test: `tests/Feature/Platform/PlatformAuditTest.php`
- [ ] **T-24** — Navegador: axe y 360 px sin desplazamiento horizontal en el
  inicio de sesión, la lista de planteles y el detalle de uno, abiertos en
  `admin.localhost`.
  - Criterios: PLT-NF07
  - Test: `tests/Browser/PlatformAccessibilityTest.php`
- [ ] **T-25** — Rendimiento: lista con 500 planteles en menos de 500 ms y
  uso de un plantel en menos de 300 ms (p95).
  - Criterios: PLT-NF06
  - Test: `tests/Feature/Platform/PlatformPerformanceTest.php`
    (`group=perf`)

## Verificación final

- [ ] `php artisan test --compact` en verde (incluidos `--group=rls` y
  `--group=perf`) y tests de navegador en verde.
- [ ] Tests de arquitectura en verde: activación de planteles y consultas de
  agregados solo desde Platform (ADR 0010).
- [ ] `vendor/bin/pint --dirty` sin cambios; `npm run build` sin errores;
  `composer audit` sin avisos de las dependencias nuevas.
- [ ] `migrate:fresh --seed` dos veces y rollback de la migración nueva.
- [ ] Verificaciones V1 a V5 cubiertas, cada una con sus tests en la matriz.
- [ ] Notas del PR con el procedimiento de despliegue de V3: crear el
  Superadministrador, invitar a los operadores y probar con `--dry-run`.
- [ ] Matriz de trazabilidad completa.
- [ ] `/security-review` de cada PR (autenticación, MFA y activación de
  planteles; S-12).
- [ ] `estado` de requirements/design/tasks actualizado.

## Cobertura de criterios

| Grupo | Tareas |
|-------|--------|
| PLT-R01 | T-01, T-02, T-05, T-06, T-07, T-08, T-11, T-13 |
| PLT-R02 | T-07, T-09 |
| PLT-R03 | T-02, T-04, T-07, T-08, T-10, T-11, T-13 |
| PLT-R04 | T-15, T-16, T-17, T-18 |
| PLT-R05 | T-16, T-17, T-18 |
| PLT-R06 | T-20, T-21 |
| PLT-R07 | T-05, T-06, T-17, T-20, T-22, T-23 |
| PLT-NF01 | T-03, T-14, T-15, T-19 |
| PLT-NF02 | T-03, T-04 |
| PLT-NF03 | T-01, T-08 |
| PLT-NF04 | T-12, T-13, T-18 |
| PLT-NF05 | T-08, T-10, T-13 |
| PLT-NF06 | T-25 |
| PLT-NF07 | T-24 |
| V1 – V5 | V1: T-14; V2: T-14, T-15, T-18; V3: T-09, T-21; V4: T-02, T-04, T-08, T-10, T-13; V5: T-06, T-08 |
