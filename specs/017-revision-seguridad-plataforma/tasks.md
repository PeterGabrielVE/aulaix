---
spec: 017-revision-seguridad-plataforma
estado: implementando
fecha: 2026-11-27
diseño: design.md
---

# Tareas — Cierre de la revisión de seguridad de SPEC-014

Reglas:

- Una tarea = un cambio pequeño, revisable y que deja la suite en verde.
- Cada tarea cita los criterios que cubre y el test que la verifica.
- TDD (Q-02): primero el test en rojo.
- Marca `[x]` al terminar y añade el test a la matriz de `requirements.md`.
- Q-03 / RSP-NF07: los tests existentes de SPEC-009, 014 y 015 solo cambian
  su preparación cuando corresponde; las únicas excepciones de comportamiento
  son el comando de consola de IDG-R11 y la resolución sin vista previa.

Tres PRs, independientes entre sí salvo las claves compartidas de bloqueo
(`T-01`, que entra en el PR A):

- **A:** bloqueos de operadores, bloqueo por fallos, huellas HMAC de códigos
  de recuperación y TOTP reciente en los casos de uso (`RSP-R01`, `R02`,
  `R03`, `NF02`, `NF03`).
- **B:** consolidaciones concurrentes, vista previa exigida por el servidor,
  TOTP en consola y bitácora de consultas (`RSP-R04`, `R05`, `R06`, `R07`).
- **C:** planteles inactivos en traslados y trabajos encolados + cierre
  documental (`RSP-R08`, `NF01` – `NF07`).

---

## PR A — Operadores: bloqueos, lockout, códigos con secreto y TOTP reciente

### Fase 1 — Claves compartidas y dominio de Platform

- [x] **T-01** — Centralizar las claves de bloqueos consultivos en
  `src/Shared/Infrastructure/AdvisoryLockKeys.php` (`CALENDAR = 7007`,
  `OPERATOR = 1014`, `OPERATOR_ROSTER = 1015`, `CONSOLIDATION = 4011`,
  `INSTITUTION_STATUS = 1001`) y migrar `DatabaseCalendarLock` a usar esa
  constante.
  - Regla de arquitectura en `tests/Architecture/LayersTest.php`: `pg_advisory`
    solo puede aparecer en los adaptadores de bloqueo dedicados.
  - Criterios: RSP-NF04
  - Test: `tests/Architecture/LayersTest.php` › «pg_advisory is confined to the advisory lock adapters»; suite existente de `AcademicCalendar` en verde.
  - Hecho cuando: todas las claves están definidas en un único sitio y el test
    de arquitectura pasa.

- [x] **T-02** — Ampliar el agregado `Operator` y crear el VO `CodeFailure`
  en `src/Platform/Domain/`:
  - `CodeFailure` (`final readonly`, `occurredAt`, `ip`, sin el código);
  - `Operator` mantiene `codeFailures` (máx. 10) y `blockedUntil`:
    - `failCode(DateTimeImmutable $at, string $ip): bool` (vacía fallos si el
      bloqueo anterior venció; al 10.º fija `blockedUntil = $at + 15 min`,
      sube `sessionVersion` y devuelve `true`);
    - `isBlockedAt(DateTimeImmutable $now): bool`;
    - `useTotpStep()` y un método explícito al usar código de recuperación
      (`recordSuccessfulCode()` o similar) vacían `codeFailures`;
    - `resetMfa()` vacía también `codeFailures` y `blockedUntil`;
    - no existe ningún método de desbloqueo anticipado.
  - Criterios: RSP-R03.2, R03.4, R03.5, R03.8, R03.9, R03.10, R03.11
  - Test: `tests/Unit/Platform/Domain/OperatorTest.php`:
    - «blocks for 15 minutes and bumps session version on the tenth consecutive code failure»;
    - «clears failures after a successful code outside lockout»;
    - «accepts codes again with failures reset once the 15-minute lockout expires»;
    - «resetting MFA lifts an active lockout and clears failures»;
    - «offers no method to lift a lockout early».
  - Hecho cuando: todos los tests unitarios del agregado pasan sin Laravel.

- [x] **T-03** — Interfaz de dominio `RecoveryCodeHasher` y actualización de
  `RecoveryCodes` para recibir el hasher en `fromPlain()`, `accepts()` y
  `use()` manteniendo la normalización actual.
  - Criterios: RSP-NF02
  - Test: `tests/Unit/Platform/Domain/RecoveryCodesTest.php` (con un hasher
    de prueba en memoria).
  - Hecho cuando: `RecoveryCodes` no calcula hashes sin `RecoveryCodeHasher`
    y conserva su comportamiento de normalización y uso único.

### Fase 2 — Aplicación de Platform (bloqueos, intentos y TOTP reciente)

- [x] **T-04** — Nuevos puertos y guardas de acceso en `src/Platform/Application/`:
  - `Port/OperatorLock` (`lockOperator(OperatorId)`, `lockRoster()`);
  - `Port/OperatorMailer` (+ `operatorBlocked(Operator, list<CodeFailure>, list<OperatorEmail>)`);
  - `PlatformAudit` (+ `operatorBlocked(OperatorId)` → `platform.operator.blocked`);
  - `PlatformAccess`:
    - `operator()` comprueba además `! $operator->isBlockedAt($now)`;
    - `withRecentTotp()` (y variante para `superAdmin`) comprueba la ventana
      de 10 minutos con `Clock` y lanza `AuthorizationDenied` si venció o falta.
  - Actualizar todos los casos de uso sensibles para exigir TOTP reciente:
    `SetOperatorActiveHandler`, `ChangeOperatorRoleHandler`,
    `ResetOperatorMfaHandler`, `RegenerateRecoveryCodesHandler`,
    `SetInstitutionActiveHandler`, `ResolveCaseHandler` e
    `InviteOperatorHandler` (cuando `$invitedBy !== null`).
  - Criterios: RSP-R03.4, R03.7, NF03
  - Test: `tests/Unit/Platform/Application/SensitiveActionsNeedRecentTotpTest.php`
    (un caso por cada handler sensible, incluida la excepción de consola en
    `InviteOperatorHandler`).
  - Hecho cuando: todos los handlers sensibles rechazan con
    `AuthorizationDenied` sin TOTP reciente y la suite existente pasa
    ajustando solo la preparación de TOTP en los tests unitarios que lo
    necesiten.

- [x] **T-05** — `CodeAttempt` y actualización de `LogInOperatorHandler` y
  `ConfirmTotpHandler`:
  - `CodeAttempt` ejecuta bajo transacción: `OperatorLock::lockOperator()` →
    relectura con `byId()` → verificación de `canLogIn()` e `isBlockedAt()` →
    validación de código → `failCode()` (con registro de
    `platform.operator.blocked` en la transacción) o consumo del código;
  - `LogInOperatorHandler` recibe `$ip`, mantiene tiempo constante y no
    cuenta fallos cuando el correo no existe o la contraseña falla (R03.3);
    envía el correo de bloqueo después del commit (R03.6); abre sesión con la
    versión releída (R01.3);
  - `ConfirmTotpHandler` recibe `$ip`, usa `CodeAttempt` (solo TOTP), cierra
    la sesión actual tras el commit si el operador se bloquea (R03.5) y envía
    el aviso por correo (R03.6).
  - Criterios: RSP-R01.1 – R01.5, R03.2 – R03.9, NF05
  - Test: `tests/Unit/Platform/Application/LogInOperatorTest.php` y
    `tests/Unit/Platform/Application/ConfirmTotpTest.php` (con un
    `OperatorLock` que cuenta y verifica que bloquea antes de releer).
  - Hecho cuando: los tests unitarios de aplicación verifican bloqueo previo
    a relectura, recuento, lockout, correo post-commit y mensaje único.

- [x] **T-06** — Bloqueo y relectura en todos los handlers de gestión de
  operadores:
  - `SetOperatorActiveHandler` y `ChangeOperatorRoleHandler`: dentro de una
    sola transacción toman `lockRoster()` y luego `lockOperator($target)`
    (orden fijo de ADR 0014), releen el operador y el recuento de
    Superadministradores activos, comprueban `SuperAdminRules::check` y
    guardan;
  - `ResetOperatorMfaHandler`, `RegenerateRecoveryCodesHandler` y
    `AcceptInvitationHandler`: toman `lockOperator()` dentro de la
    transacción y releen antes de modificar/guardar (y pasan
    `RecoveryCodeHasher` donde generan códigos).
  - Regla de arquitectura en `tests/Architecture/LayersTest.php`: ningún caso
    de uso llama a `OperatorRepository::save` (salvo `InviteOperatorHandler`)
    ni a `saveRecoveryCodes` sin recibir `OperatorLock`.
  - Criterios: RSP-R01.1 – R01.3, R01.6, R02.1, R02.2, R03.11, NF02
  - Test: `tests/Unit/Platform/Application/OperatorManagementTest.php` (orden
    `lockRoster` → `lockOperator`, relectura bajo bloqueo);
    `tests/Unit/Platform/Application/OperatorInvitationTest.php`;
    `tests/Architecture/LayersTest.php`.
  - Hecho cuando: los tests verifican el orden de bloqueos y ninguna ruta de
    escritura sobre operadores existentes queda sin `OperatorLock`.

### Fase 3 — Infraestructura, migraciones y HTTP de Platform (PR A)

- [x] **T-07** — Migración de esquema en `platform_operators`:
  - añade `code_failures jsonb NOT NULL DEFAULT '[]'`,
    `blocked_until timestamptz NULL` y
    `CHECK (jsonb_array_length(code_failures) <= 10)`;
  - `down()` elimina la restricción y las dos columnas;
  - actualizar `OperatorModel` (`casts` para `code_failures` y
    `blocked_until`).
  - Criterios: RSP-R03.2, NF04
  - Test: `tests/Feature/Platform/Infrastructure/OperatorTablesTest.php` ›
    «code_failures check constraint rejects more than 10 entries», más prueba
    de `migrate:rollback` y `migrate`.
  - Hecho cuando: la migración sube y baja limpiamente en PostgreSQL sin
    `SECURITY DEFINER` ni cambios en RLS de planteles.

- [x] **T-08** — Adaptadores `DatabaseOperatorLock`,
  `KeyedRecoveryCodeHasher`, `OperatorBlockedMail` + `NotificationOperatorMailer`
  y exigencia de bloqueo en `EloquentOperatorRepository`:
  - `DatabaseOperatorLock`: exige transacción abierta y adquiere
    `pg_advisory_xact_lock` usando `AdvisoryLockKeys::OPERATOR` y
    `AdvisoryLockKeys::OPERATOR_ROSTER`;
  - `EloquentOperatorRepository`: persiste `code_failures` y `blocked_until`;
    en `save()` cuando `$operator->id !== null` y en `saveRecoveryCodes()`
    comprueba en `pg_locks` que la transacción posee el bloqueo del operador
    y si no lanza `LogicException`;
  - `KeyedRecoveryCodeHasher`:
    `hash_hmac('sha256', hash('sha256', $normalized), $secret)` leyendo
    `config('platform.recovery_code_key')` (`PLATFORM_RECOVERY_CODE_KEY`,
    obligatorio fuera de `local` y `testing`; añadir a `.env.example` y
    `config/platform.php`);
  - `OperatorBlockedMail`: incluye fecha y las IP de los intentos, sin
    secretos ni códigos; enviado al operador y a los Superadministradores
    activos;
  - registrar enlaces en `PlatformServiceProvider`.
  - Criterios: RSP-R01.1, R03.6, NF02, NF04
  - Test: `tests/Feature/Platform/Infrastructure/OperatorPersistenceTest.php`
    («save without the lock fails», «saveRecoveryCodes without the lock fails»,
    «persists code failures and blocked_until»);
    `tests/Feature/Platform/Infrastructure/KeyedRecoveryCodeHasherTest.php`.
  - Hecho cuando: todos los tests de infraestructura pasan contra PostgreSQL
    real.

- [x] **T-09** — Migración de datos de `platform_operator_recovery_codes`
  (en su propia tarea):
  - rehashea cada `code_hash` existente a `hash_hmac('sha256', $codeHash, $secret)`
    por lotes dentro de una transacción;
  - falla antes de tocar ninguna fila si `PLATFORM_RECOVERY_CODE_KEY` falta;
  - `down()` lanza una excepción explicando las opciones A y B del plan de
    rollback de `design.md` (no deja huellas rotas).
  - Criterios: RSP-NF02
  - Test: `tests/Feature/Platform/Infrastructure/RecoveryCodeRehashMigrationTest.php`:
    - «recovery codes generated before migration remain valid afterwards»;
    - «fails before modifying rows when the key is missing»;
    - «down throws explaining the rollback procedure».
  - Hecho cuando: los tests de `up` y `down` pasan y los códigos previos al
    despliegue siguen siendo aceptados.

- [x] **T-10** — Adaptadores HTTP, limitador `platform-confirm`, tests feature
  de lockout y tests de concurrencia reales de operadores (`tests/Isolation`):
  - `AppServiceProvider`: limitador `platform-confirm` (5/min por
    `operador de la sesión | IP`), reemplazando `throttle:5,1` en
    `POST /confirmar-codigo` (`routes/web.php`);
  - `PlatformLoginController::store` y `PlatformTotpController::store` pasan
    `$request->ip()`;
  - feature tests de lockout y concurrencia real con dos conexiones PDO
    (`pg_try_advisory_xact_lock`, patrón `EnrollmentConcurrencyTest`).
  - Criterios: RSP-R01.1 – R01.6, R02.1, R02.2, R03.1 – R03.11, NF01, NF05,
    NF07
  - Test:
    - `tests/Feature/Platform/OperatorLockoutTest.php` (10 fallos desde IP
      distintas, mismo mensaje para correo inexistente/bloqueado, contraseña
      incorrecta no cuenta, cierre de sesiones, correo, bitácora, vencimiento
      a los 15 min, levantamiento solo al restablecer MFA, límite
      `platform-confirm`);
    - `tests/Isolation/OperatorLockTest.php` (desactivación vs. inicio de
      sesión en ambos órdenes, cambio de nivel vs. login, dos logins con el
      mismo TOTP, dos logins con el mismo recovery code, regeneración vs.
      código antiguo, dos degradaciones simultáneas del último par de
      Superadministradores).
  - Hecho cuando: todos los tests feature e `Isolation` pasan en Postgres y
    contra PgBouncer (`phpunit.pgbouncer.xml`).

---

## PR B — Consolidaciones: concurrencia, vista previa, consola y bitácora

### Fase 4 — Consolidaciones concurrentes y vista previa auditada (`IdentityAccess`)

- [ ] **T-11** — Puerto `ConsolidationLock` y refactor de
  `AccountConsolidator`, `ResolveConsolidationCaseHandler` y
  `CompleteConsolidationHandler` en `src/IdentityAccess/`:
  - nuevo puerto `ConsolidationLock::lockEmail(EmailAddress)`;
  - `AccountConsolidator::consolidate(EmailAddress $email, Closure $check, ?HashedPassword $newPassword, array $context = [])`:
    dentro de la transacción toma `lockEmail()`, relee `byEmail()`, ejecuta
    `$check($group)`, mueve membresías, borra las demás, guarda la
    superviviente y registra en la bitácora; **después del commit** revoca
    sesiones y envía el correo (R04.4);
  - `ResolveConsolidationCaseHandler`:
    - `consolidate` delega a `AccountConsolidator` con la comprobación de
      IDG-R11.5 (todas en `Review` y exactamente las cuentas pedidas);
    - `separate` en su propia transacción toma `lockEmail()`, relee, valida
      lo mismo y marca separadas;
    - `dryRun` toma `lockEmail()`, relee, valida lo mismo, no modifica
      cuentas y registra `identity.consolidation.previewed` con operador,
      decisión e identificadores (sin nombres, correos ni planteles, R07.2,
      R07.3);
  - `CompleteConsolidationHandler` (IDG-R09) usa `ConsolidationLock` y la
    nueva firma (incluido el caso de 1 sola cuenta pendiente bajo el mismo
    bloqueo).
  - Adaptador `DatabaseConsolidationLock` usando
    `AdvisoryLockKeys::CONSOLIDATION` (`4011`, `hashtext(lower(email))`),
    registrado en `IdentityAccessServiceProvider`.
  - Criterios: RSP-R04.1 – R04.4, R07.2, R07.3, NF04, NF07
  - Test:
    - `tests/Unit/IdentityAccess/Application/AccountConsolidatorTest.php`
      (con grupo cambiado no escribe, no revoca sesiones ni envía correo);
    - `tests/Unit/IdentityAccess/Application/ResolveConsolidationCaseTest.php`
      (preview auditado sin PII, rechazos registrados);
    - `tests/Isolation/ConsolidationLockTest.php` («separar» vs.
      «consolidar» en ambos órdenes, con dos conexiones reales).
  - Hecho cuando: los tests unitarios y de concurrencia pasan y los tests de
    IDG-R09 siguen en verde sin cambiar su lógica.

### Fase 5 — Vista previa en servidor y bitácora del panel (`Platform`)

- [ ] **T-12** — Puerto `ConsolidationPreviews` +
  `SessionConsolidationPreviews`, `ListCasesQuery`, `PreviewResolutionQuery`,
  `ResolveCaseHandler` y `ConsolidationController`:
  - `ConsolidationPreviews` (`remember`, `matches`, `forget`) implementado en
    sesión del guard `platform` (`SessionConsolidationPreviews`, registrado
    en `PlatformServiceProvider`);
  - `PlatformAudit` (+ `consolidationCasesListed(list<int>)` →
    `platform.consolidation_cases.listed`,
    `consolidationResolveRefused(list<int>, string $reason)` →
    `platform.consolidation.resolve_refused`);
  - `ListCasesQuery` registra `platform.consolidation_cases.listed` en la
    bitácora con los IDs de las cuentas mostradas (sin PII, R07.1, R07.3);
  - `PreviewResolutionQuery` recibe el `$caseAccountId` de la ruta, verifica
    que pertenezca a `$accountIds` (R05.4), simula y recuerda la vista previa
    con cuentas ordenadas, decisión y hora (R05.1);
  - `ResolveCaseHandler` verifica operador + TOTP reciente (NF03), verifica
    `$caseAccountId` en `$accountIds` (R05.4), exige vista previa coincidente
    de los últimos 10 min (si falta o caducó registra
    `platform.consolidation.resolve_refused` y lanza `BusinessRuleViolation`
    en `preview`, R05.1, R05.2), resuelve y olvida la vista previa solo al
    aplicarse con éxito (R05.3);
  - `ConsolidationController` pasa `{account}` a `preview` y `resolve`.
  - Criterios: RSP-R05.1 – R05.4, R07.1 – R07.3, NF03, NF07
  - Test:
    - `tests/Unit/Platform/Application/ConsolidationTest.php` (vista previa
      ausente, caducada, otra decisión, otras cuentas, consumo único,
      conservación si falla);
    - `tests/Feature/Platform/ConsolidationTest.php` (actualizando la
      preparación de los tests existentes para generar vista previa antes de
      resolver, NF07; más POST directo sin vista previa y cuenta de la ruta
      distinta);
    - `tests/Feature/Platform/ConsolidationAuditTest.php` (lista de casos y
      vista previa auditadas, sin nombres/correos/planteles).
  - Hecho cuando: todos los tests unitarios y feature pasan.

### Fase 6 — Autenticación TOTP y confirmación en la consola

- [ ] **T-13** — `AuthenticateConsoleOperatorHandler` en `src/Platform/` y
  actualización del comando `app/Console/Commands/ResolveConsolidationCase.php`:
  - `AuthenticateConsoleOperatorHandler` usa `CodeAttempt` sin contraseña,
    aceptando únicamente TOTP (un código de recuperación cuenta como fallo y
    se rechaza, R06.7), comparte `totpLastStep` con el panel (R06.6), cuenta
    fallos y dispara bloqueo (R06.5), y registra cada rechazo en la bitácora
    de plataforma con origen «consola» (R06.2, R06.4);
  - `ResolveConsolidationCase`:
    1. valida `--decision`;
    2. si `! $this->input->isInteractive()`, llama a
       `AuthenticateConsoleOperatorHandler` con `code = null` (registra el
       rechazo) y devuelve `FAILURE` (R06.4);
    3. pide el TOTP con `$this->secret()` — sin argumento ni opción en la
       firma del comando (R06.1, R06.3);
    4. autentica con `AuthenticateConsoleOperatorHandler` y fija
       `AuditContext::actAsPlatformOperator($operatorId->value)` (o consola
       en rechazos);
    5. ejecuta simulación (`dryRun: true`), imprime el plan y queda
       registrada en la bitácora (R07.2);
    6. si se pasó `--dry-run`, termina con `SUCCESS`;
    7. pide confirmación expresa con `$this->confirm(..., false)` — sin un
       «sí» termina sin aplicar nada (R06.8);
    8. ejecuta la resolución real.
  - Criterios: RSP-R06.1 – R06.8, R07.2, R07.3, NF07
  - Test:
    - `tests/Unit/Platform/Application/AuthenticateConsoleOperatorTest.php`;
    - `tests/Feature/Platform/ConsoleOperatorTest.php` (sin terminal, sin
      opción `--totp`, TOTP incorrecto/reusado entre panel y consola, código
      de recuperación rechazado, operador inactivo/bloqueado, acumulación de
      fallos hacia lockout, confirmación «no», `--dry-run`, bitácora de
      rechazos y previews);
    - actualizar la preparación de los tests existentes del comando de IDG-R11
      (NF07).
  - Hecho cuando: todos los tests de consola e identidad pasan.

---

## PR C — Planteles inactivos y cierre de la spec

### Fase 7 — Bloqueo del estado del plantel (`Tenancy`) y escrituras protegidas (`Students`, cola)

- [ ] **T-14** — Puerto `InstitutionStatusLock`, `InstitutionUnavailable`,
  `InstitutionContexts::writableOf()`, `SetInstitutionStatusHandler` y
  `DatabaseInstitutionStatusLock` en `src/Tenancy/`:
  - `InstitutionStatusLock` (`shared(InstitutionId)`,
    `exclusive(InstitutionId)`) implementado en `DatabaseInstitutionStatusLock`
    con `pg_advisory_xact_lock_shared` / `pg_advisory_xact_lock` y
    `AdvisoryLockKeys::INSTITUTION_STATUS` (`1001`, id);
  - `InstitutionContexts::writableOf(InstitutionId)`: toma el bloqueo
    compartido, lee el plantel y lanza `InstitutionUnavailable` si no existe
    o está inactivo; `of()` permanece intacto para lecturas de plataforma
    (`TenantScopedReader`, R08.6);
  - `SetInstitutionStatusHandler`: toma `exclusive()` antes de leer y cambiar
    el estado;
  - registrar `InstitutionStatusLock` en `TenancyServiceProvider`.
  - Criterios: RSP-R08.1, R08.5, R08.6, NF04
  - Test: `tests/Unit/Tenancy/Application/InstitutionContextsTest.php` y
    `tests/Feature/Tenancy/Infrastructure/DatabaseInstitutionStatusLockTest.php`.
  - Hecho cuando: `writableOf()` rechaza planteles inactivos bajo bloqueo
    compartido y `of()` sigue leyendo planteles inactivos para plataforma.

- [ ] **T-15** — Traslados hacia un plantel inactivo (`Students`) y trabajos
  encolados (`Tenancy/Infrastructure/Queue/RunInInstitution`):
  - `TenantSwitchingTransferCopier` usa `InstitutionContexts::writableOf()`;
  - `ApproveTransferHandler` captura `InstitutionUnavailable` y lanza
    `BusinessRuleViolation::onField('transfer', 'El plantel destino no puede recibir el traslado por ahora.')`
    sin revelar el motivo (R08.2, NF05), revirtiendo toda la transacción para
    dejar al estudiante activo y la solicitud pendiente con su plazo original
    (R08.1, R08.3, R08.4);
  - `RunInInstitution` usa `writableOf()` dentro de una transacción; ante
    `InstitutionUnavailable`, registra el error con la clase del trabajo y el
    id del plantel, no invoca `$next` y retorna sin lanzar para evitar
    reintentos (R08.5).
  - Criterios: RSP-R08.1 – R08.6, NF01, NF05, NF07
  - Test:
    - `tests/Feature/Students/TransferToInactiveTest.php` (rechazo sin
      cambios en ambos planteles, sin motivo de desactivación, solicitud sigue
      pendiente con su plazo de 15 días, aprobación exitosa tras reactivar,
      caducidad normal);
    - `tests/Isolation/InstitutionStatusLockTest.php` (desactivación
      simultánea contra aprobación de traslado con dos conexiones reales);
    - `tests/Isolation/QueueTenantTest.php` › «discards a job for an inactive institution without retrying and logs the error»;
    - `tests/Feature/Platform/InstitutionsTest.php` (lecturas de plataforma
      sobre plantel inactivo siguen funcionando, R08.6).
  - Hecho cuando: todos los tests de `Students`, `Tenancy`, `Platform` e
    `Isolation` pasan (incluyendo contra PgBouncer).

### Fase 8 — Rendimiento y documentación cruzada

- [ ] **T-16** — Verificación de umbrales de rendimiento y actualización de
  specs relacionadas (P-04):
  - confirmar que los tests `--group=perf` de PLT-NF06 y RLT-NF03 siguen en
    verde (RSP-NF06);
  - aplicar los cambios documentales indicados en `requirements.md` §Cambios
    en otras specs (en el PR que corresponda a cada cambio):
    - `specs/014-superadministracion/requirements.md` y `tasks.md` (marcar
      `/security-review`);
    - `specs/004-identidad-global/requirements.md` (`IDG-R11.2` – `R11.5`);
    - `specs/009-estudiantes/requirements.md` (`EST-R07.5`);
    - `specs/015-rls-transaccion/requirements.md` (`RLT-R03.2`);
    - `specs/roadmap.md` y `specs/README.md`.
  - Criterios: RSP-NF06, NF07
  - Test: `tests/Architecture/SpecsTest.php`; `tests/Feature/Platform/PlatformPerformanceTest.php`; `tests/Feature/Tenancy/RlsOverheadTest.php`.
  - Hecho cuando: `SpecsTest` y `--group=perf` están en verde y la
    documentación refleja el estado final.

---

## Verificación final

- [ ] `php artisan test --compact` en verde (incluidos `--group=rls`,
  `--group=perf` y `tests/Isolation`).
- [ ] `--group=rls` y `tests/Isolation` en verde contra PgBouncer
  (`phpunit.pgbouncer.xml`) y en el job de CI.
- [ ] Tests de arquitectura (`tests/Architecture/`) en verde:
  - `pg_advisory` solo en los adaptadores de bloqueo;
  - `OperatorRepository::save` (salvo alta en `InviteOperatorHandler`) y
    `saveRecoveryCodes` solo desde casos de uso con `OperatorLock`;
  - `SpecsTest` en verde.
- [ ] `vendor/bin/pint --dirty --format agent` sin cambios; `npm run build`
  sin errores si hay cambios de interfaz.
- [ ] `migrate:fresh --seed`, rollback de la migración de esquema y prueba de
  `up`/`down` de la migración de datos.
- [ ] Matriz de trazabilidad de `requirements.md` completa (todos los
  criterios `RSP-R*` y `RSP-NF*`).
- [ ] `/security-review` de los cambios de SPEC-017 (toca autenticación,
  tenancy y permisos; S-12).
- [ ] `estado` de `requirements.md`, `design.md`, `tasks.md` y el índice de
  `specs/README.md` actualizados a `implementada`.

## Cobertura de criterios

| Grupo | Tareas |
|-------|--------|
| RSP-R01 | T-05, T-06, T-08, T-10 |
| RSP-R02 | T-06, T-10 |
| RSP-R03 | T-02, T-04, T-05, T-06, T-07, T-08, T-10 |
| RSP-R04 | T-11 |
| RSP-R05 | T-12 |
| RSP-R06 | T-13 |
| RSP-R07 | T-11, T-12, T-13 |
| RSP-R08 | T-14, T-15 |
| RSP-NF01 | T-10, T-11, T-15 |
| RSP-NF02 | T-03, T-06, T-08, T-09 |
| RSP-NF03 | T-04, T-12 |
| RSP-NF04 | T-01, T-07, T-08, T-11, T-14 |
| RSP-NF05 | T-05, T-10, T-15 |
| RSP-NF06 | T-16 |
| RSP-NF07 | T-10, T-11, T-12, T-13, T-15, T-16 |
