---
spec: 017-revision-seguridad-plataforma
estado: borrador
fecha: 2026-11-11
requisitos: requirements.md
adr: 0014
---

# Diseño — Cierre de la revisión de seguridad de SPEC-014

## Resumen

Las carreras (RSP-R01, R02, R04, R08.1) se cierran con bloqueos consultivos
de PostgreSQL por transacción ([ADR 0014](../adr/0014-carreras-de-plataforma-e-identidad.md)).
Cada caso de uso toma el bloqueo, relee y solo entonces comprueba la regla.

El operador guarda sus códigos fallidos y su bloqueo en su propia fila,
siempre bajo ese bloqueo (R03). Los códigos de recuperación pasan a una
huella con secreto, que se migra sin invalidar los actuales (NF02). La
vista previa queda en la sesión del operador y el caso de uso la exige
(R05). El comando de consola pide un TOTP con un caso de uso de `Platform`
(R06).

Módulos: `Platform` (la mayor parte), `IdentityAccess` (consolidaciones),
`Tenancy` (estado del plantel) y `Students` (traslados). No hay tablas
nuevas con datos de un plantel.

## Módulo y capas

```
src/Platform/
├── Domain/
│   ├── Operator.php                    (+ fallos de código y bloqueo)
│   ├── CodeFailure.php                 (nuevo, VO: fecha + IP)
│   ├── RecoveryCodeHasher.php          (nuevo, interfaz del dominio)
│   ├── RecoveryCodes.php               (recibe el hasher)
│   └── OperatorRepository.php          (+ lockedById, lockedByEmail)
├── Application/
│   ├── Port/OperatorLock.php           (nuevo)
│   ├── Port/ConsolidationPreviews.php  (nuevo)
│   ├── Port/OperatorMailer.php         (+ operatorBlocked)
│   ├── PlatformAccess.php              (+ withRecentTotp)
│   ├── Access/LogInOperatorHandler.php, ConfirmTotpHandler.php
│   ├── Access/AuthenticateConsoleOperatorHandler.php   (nuevo)
│   ├── Access/CodeAttempt.php          (nuevo, lo común de los cuatro accesos)
│   ├── Operators/*Handler.php          (bloquean y releen)
│   ├── Consolidation/*                 (vista previa exigida, bitácora)
│   └── Institutions/SetInstitutionActiveHandler.php
└── Infrastructure/
    ├── Persistence/DatabaseOperatorLock.php            (nuevo)
    ├── Persistence/EloquentOperatorRepository.php      (exige el bloqueo al guardar)
    ├── KeyedRecoveryCodeHasher.php                     (nuevo)
    ├── SessionConsolidationPreviews.php                (nuevo)
    └── Notifications/OperatorBlockedMail.php           (nuevo)

src/IdentityAccess/
├── Application/Consolidation/
│   ├── ConsolidationLock.php           (nuevo, puerto)
│   ├── AccountConsolidator.php         (bloquea, relee y deja los efectos para después del commit)
│   └── ResolveConsolidationCaseHandler.php (todo en una transacción; registra la vista previa)
└── Infrastructure/Persistence/DatabaseConsolidationLock.php (nuevo)

src/Tenancy/
├── Application/InstitutionStatusLock.php         (nuevo, puerto)
├── Application/InstitutionContexts.php           (+ writableOf)
├── Application/InstitutionUnavailable.php        (nuevo)
├── Application/Administration/SetInstitutionStatusHandler.php (bloqueo exclusivo)
└── Infrastructure/
    ├── Persistence/DatabaseInstitutionStatusLock.php (nuevo)
    └── Queue/RunInInstitution.php                    (descarta si está inactivo)

src/Students/
├── Infrastructure/TenantSwitchingTransferCopier.php  (usa writableOf)
└── Application/Transfer/ApproveTransferHandler.php   (traduce el rechazo)

app/
├── Console/Commands/ResolveConsolidationCase.php   (TOTP, terminal, confirmación)
├── Http/Controllers/Platform/ConsolidationController.php (caso de la ruta)
└── Providers/AppServiceProvider.php                (limitador platform-confirm)
```

## Modelo de dominio

| Elemento | Tipo | Invariantes / responsabilidad |
|----------|------|-------------------------------|
| `Operator` (se amplía) | Agregado | Guarda `codeFailures` (lista de `CodeFailure`, como mucho 10) y `blockedUntil`. `failCode(at, ip)` añade un fallo; con 10 seguidos fija `blockedUntil = at + 15 min`, sube `sessionVersion` (cierra sus sesiones, R03.5) y devuelve que acaba de bloquearse (R03.2). Si el bloqueo anterior ya venció, primero vacía los fallos (R03.9). `isBlockedAt(now)` responde si está bloqueado (R03.4). `useTotpStep` y el uso de un código de recuperación vacían los fallos (R03.8). `resetMfa()` vacía también los fallos y el bloqueo (R03.11). Ningún método levanta el bloqueo de otra forma (R03.10). |
| `CodeFailure` | Value Object | Fecha e IP de un código fallido, para el aviso (R03.6). Sin el código. |
| `RecoveryCodeHasher` | Interfaz de dominio | `hash(string $code): string`. La implementa Infrastructure con un secreto (NF02). Es interfaz de dominio, y no puerto de aplicación, porque la usa `RecoveryCodes`, que es dominio y no lee configuración (A-02). |
| `RecoveryCodes` (se amplía) | Value Object | `fromPlain`, `accepts` y `use` reciben el hasher. Normaliza el código igual que ahora. |
| `SuperAdminRules` | Servicio de dominio | Sin cambios. Lo que cambia es que el recuento llega releído bajo el bloqueo de la lista (R02.1, R02.2). |

## Casos de uso (Application)

| Caso de uso | Entrada | Salida | Criterios |
|-------------|---------|--------|-----------|
| `LogInOperatorHandler` (cambia) | correo, contraseña, código, IP | códigos de recuperación restantes | RSP-R01.1, R01.2, R01.3, R01.4, R01.5, R03.2, R03.3, R03.4, R03.6, R03.7, R03.8, R03.9 |
| `ConfirmTotpHandler` (cambia) | código, IP | — | RSP-R01.4, R03.1 (con el limitador), R03.2, R03.6 – R03.8 |
| `AuthenticateConsoleOperatorHandler` (nuevo) | correo, código o `null` si no hay terminal | `OperatorId` | RSP-R06.1, R06.2, R06.4, R06.5, R06.6, R06.7 |
| `SetOperatorActiveHandler`, `ChangeOperatorRoleHandler` (cambian) | operador, valor | — | RSP-R01.1 – R01.3, R02.1, R02.2, NF03 |
| `ResetOperatorMfaHandler` (cambia) | operador | — | RSP-R01.1, R01.2, R03.10, R03.11, NF03 |
| `RegenerateRecoveryCodesHandler` (cambia) | — | códigos nuevos | RSP-R01.6, NF02, NF03 |
| `AcceptInvitationHandler` (cambia) | token, contraseña, TOTP | — | RSP-R01.1 (bloquea como los demás) |
| `ListCasesQuery` (cambia) | — | casos | RSP-R07.1, R07.3 |
| `PreviewResolutionQuery` (cambia) | caso de la ruta, cuentas, decisión, motivo, referencia | plan | RSP-R05.1 (la recuerda), R05.4, R07.2 (vía IdentityAccess) |
| `ResolveCaseHandler` (cambia) | caso de la ruta, cuentas, decisión, motivo, referencia | resolución | RSP-R05.1 – R05.4, NF03 |
| `SetInstitutionActiveHandler` (cambia) | plantel, activo, motivo | — | RSP-NF03; con `SetInstitutionStatusHandler`, R08.1 (bloqueo exclusivo) |
| `ResolveConsolidationCaseHandler` (IdentityAccess, cambia) | `ResolveConsolidationCaseCommand` | `CaseResolution` | RSP-R04.1 – R04.4, R07.2, R07.3 |
| `AccountConsolidator` (IdentityAccess, cambia) | correo, comprobación del grupo, contraseña, contexto | cuenta superviviente | RSP-R04.1, R04.3, R04.4 |
| `InstitutionContexts::writableOf` (Tenancy, nuevo) | plantel | `TenantContext` o `InstitutionUnavailable` | RSP-R08.1, R08.5, R08.6 (`of` sigue igual para lecturas) |
| `ApproveTransferHandler` (Students, cambia) | solicitud | — | RSP-R08.1 – R08.4 |
| `RunInInstitution` (Tenancy, cambia) | trabajo | — | RSP-R08.5 |

### El acceso con código, paso a paso

`CodeAttempt` reúne lo que comparten el inicio de sesión, la confirmación y
la consola. Se escribe una vez y se prueba una vez:

1. Fuera de la transacción se lee el operador por correo (o por la sesión),
   solo para saber a quién bloquear. El inicio de sesión compara la
   contraseña y el código con el mismo trabajo de siempre, exista o no el
   correo (PLT-R01.3, V5).
2. Si el correo no existe o la contraseña es incorrecta: fallo genérico,
   sin bloqueo ni recuento (R03.3).
3. Transacción: se toma el bloqueo del operador (`OperatorLock`) y se
   **relee**. Con lo releído:
   - si no puede entrar (inactivo, sin TOTP) o está bloqueado: fallo
     genérico, sin contarlo (R01.2, R03.4);
   - si el paso TOTP ya se usó, o el código no es válido: `failCode(now,
     ip)`, guardar y confirmar la transacción. Después se lanza el fallo
     genérico (R01.4, R01.5, R03.2). Si acaba de bloquearse, se registra en
     la bitácora dentro de la transacción (R03.7) y, después del commit, se
     envía el aviso (R03.6);
   - si es correcto: `useTotpStep`, o el código de recuperación se marca
     usado; se guarda; en el inicio de sesión se abre la sesión con la
     versión releída (R01.3); se escribe la bitácora.

Una acción de gestión que se confirme antes del paso 3 se ve en la
relectura. Si se confirma después, encuentra el operador ya guardado bajo
el bloqueo y sube su versión, así que la sesión recién abierta muere en la
siguiente petición (`CurrentOperatorQuery`, sin cambios). En los dos órdenes
gana la acción de gestión (R01.1).

La consola (`AuthenticateConsoleOperatorHandler`) usa los mismos pasos con
estas diferencias:

- no hay contraseña;
- solo acepta un TOTP: un código de recuperación cuenta como fallo y se
  rechaza (R06.7);
- cada rechazo se registra en la bitácora de plataforma con origen
  «consola» (R06.2);
- con `code = null` (sin terminal) rechaza y registra el rechazo sin
  contarlo como fallo (R06.4).

### Gestión de operadores

`SetOperatorActiveHandler` y `ChangeOperatorRoleHandler`, en una sola
transacción:

1. bloqueo de la lista de operadores, y después el del operador;
2. releen el operador y cuentan los Superadministradores activos;
3. `SuperAdminRules::check`;
4. aplican y guardan.

La segunda de dos acciones simultáneas ve el recuento ya rebajado y se
rechaza con el mensaje de PLT-R03.5 (R02).

`ResetOperatorMfaHandler` y `RegenerateRecoveryCodesHandler` toman solo el
bloqueo del operador. Por eso un inicio de sesión con un código antiguo
queda en fila detrás de la regeneración, o delante de ella, pero nunca a la
vez (R01.6).

### Consolidaciones

`AccountConsolidator::consolidate(email, check, password, context)` hace
dentro de una transacción:

1. `ConsolidationLock::lockEmail(email)`;
2. relee el grupo con `byEmail`;
3. llama a `check(grupo)`, que es la regla de quien llama: «todas en
   revisión y son exactamente estas», o «pendiente» en IDG-R09. Si falla,
   lanza y no se escribe nada;
4. mueve las membresías, borra las demás cuentas, guarda la superviviente y
   registra en la bitácora.

**Solo después del commit** se revocan las sesiones y se envía el correo.
Un rechazo no envía nada ni cierra ninguna sesión (R04.4).

`ResolveConsolidationCaseHandler`:

- valida el operador, el motivo y la referencia;
- decisión `consolidate`: llama a `consolidate` con la comprobación de
  IDG-R11.5;
- decisión `separate`: en su propia transacción, bloquea el correo, relee,
  comprueba lo mismo y marca las cuentas;
- dos resoluciones a la vez quedan en fila sobre el correo. La segunda
  relee, ya no encuentra «en revisión» y se rechaza; el rechazo se registra
  como ahora (R04.1 – R04.3);
- en simulación (`dryRun`) hace la misma comprobación bajo el bloqueo, no
  escribe nada salvo la entrada `identity.consolidation.previewed`, con
  operador, decisión e identificadores (R07.2, R07.3), y no toca las
  cuentas.

`CompleteConsolidationHandler` (IDG-R09) pasa a llamar a la nueva firma con
su propia comprobación. Su comportamiento no cambia (NF07).

### Vista previa exigida (R05)

- `PreviewResolutionQuery` comprueba que el caso de la ruta está entre las
  cuentas (R05.4) y llama a la simulación. Si sale bien, la recuerda en
  `ConsolidationPreviews` con las cuentas ordenadas, la decisión y la hora.
- `ResolveCaseHandler` hace, por este orden:
  1. comprueba el operador y el TOTP reciente (NF03);
  2. comprueba que el caso de la ruta está entre las cuentas (R05.4);
  3. comprueba que hay una vista previa igual de los últimos 10 minutos.
     Si no la hay, registra `consolidation.resolve_refused` con el motivo
     `no_preview` y lanza `BusinessRuleViolation` en `preview` (R05.1,
     R05.2);
  4. resuelve;
  5. olvida la vista previa (R05.3).

  Si la resolución falla, la vista previa se conserva hasta que caduque.

La sesión del panel es del operador, así que una vista previa nunca sirve a
otro operador.

### Planteles inactivos (R08)

- `InstitutionContexts::writableOf(id)` toma el bloqueo **compartido** del
  estado del plantel y lo lee. Si está inactivo, lanza
  `InstitutionUnavailable`. `of(id)` no cambia: lo usan las lecturas de
  plataforma (`TenantScopedReader`, R08.6).
- `SetInstitutionStatusHandler` toma el bloqueo **exclusivo** antes de leer
  y cambiar el estado. Una desactivación espera a los traslados en curso, y
  un traslado posterior ve el plantel inactivo.
- `TenantSwitchingTransferCopier` usa `writableOf`. `ApproveTransferHandler`
  captura `InstitutionUnavailable` y lanza `BusinessRuleViolation` en
  `transfer`: «El plantel destino no puede recibir el traslado por ahora.»
  No da el motivo (R08.2, NF05). La transacción se revierte entera: la
  solicitud sigue pendiente, con su plazo, y el estudiante sigue activo
  (R08.1, R08.3). Al reactivarse, la misma aprobación pasa (R08.4).
- `RunInInstitution` usa `writableOf`. Si recibe `InstitutionUnavailable`,
  escribe en el registro de errores la clase del trabajo y el id del
  plantel, no llama a `$next` y devuelve sin lanzar, así que la cola no lo
  reintenta (R08.5).

## Puertos y adaptadores

| Puerto (interfaz) | Capa | Adaptador | Notas |
|-------------------|------|-----------|-------|
| `OperatorLock` | Platform Application | `DatabaseOperatorLock` | `lockOperator(OperatorId)` → (`1014`, id) y `lockRoster()` → (`1015`, 0). Exige una transacción abierta. Se mantiene como puerto para que los tests de aplicación comprueben, con un doble que cuenta, que se bloquea antes de releer (patrón de `CountingCalendarLock`). |
| `OperatorRepository` (se amplía) | Platform Domain | `EloquentOperatorRepository` | `save()` y `saveRecoveryCodes()` comprueban en `pg_locks` que la transacción tiene el bloqueo de ese operador; si no, `LogicException` (ADR 0014, regla 3). Guarda `code_failures` y `blocked_until`. |
| `RecoveryCodeHasher` | Platform Domain | `KeyedRecoveryCodeHasher` | `hash_hmac('sha256', hash('sha256', $normalizado), $secreto)`. El secreto es `PLATFORM_RECOVERY_CODE_KEY` y es obligatorio fuera de `local` y `testing`. No es `APP_KEY`: rotar esa clave no debe invalidar los códigos. |
| `ConsolidationPreviews` | Platform Application | `SessionConsolidationPreviews` | Vive en la sesión del panel (guard `platform`). Una sola entrada: cuentas ordenadas, decisión y hora. |
| `OperatorMailer` (se amplía) | Platform Application | `NotificationOperatorMailer` | `operatorBlocked(Operator $blocked, list<CodeFailure>, list<OperatorEmail> $superAdmins)` (R03.6). Se envía después del commit. |
| `ConsolidationLock` | IdentityAccess Application | `DatabaseConsolidationLock` | `lockEmail(EmailAddress)` → (`4011`, `hashtext(lower(email))`). |
| `InstitutionStatusLock` | Tenancy Application | `DatabaseInstitutionStatusLock` | `shared(InstitutionId)` y `exclusive(InstitutionId)` → (`1001`, id), con `pg_advisory_xact_lock_shared` y `pg_advisory_xact_lock`. |

Los adaptadores de bloqueo comparten las claves en una sola clase
`AdvisoryLockKeys` en `src/Shared/Infrastructure/`, junto a la de ADR 0007
(`7007`). Un test de arquitectura impide escribir `pg_advisory` fuera de
esos adaptadores.

## Adaptadores de entrada (`app/`)

- **Limitador `platform-confirm`** (`AppServiceProvider`): 5 por minuto,
  con la clave `operador de la sesión | IP`. Sustituye a `throttle:5,1` en
  `POST /confirmar-codigo` (R03.1).
- **`PlatformLoginController::store` y `PlatformTotpController::store`**
  pasan `$request->ip()` al caso de uso (R03.6).
- **`ConsolidationController`**: `preview` y `resolve` pasan el `{account}`
  de la ruta al caso de uso (R05.4). `resolve` sigue con
  `platform.recent-totp`.
- **`ResolveConsolidationCase`** (comando), en este orden:
  1. sin terminal interactiva (`! $this->input->isInteractive()`), llama al
     caso de uso con `code = null`, que registra el rechazo, y termina con
     `FAILURE` (R06.4);
  2. pide el TOTP con `$this->secret()`. No hay opción ni argumento para él
     (R06.3);
  3. `AuthenticateConsoleOperatorHandler`. Si falla, `FAILURE` (R06.1,
     R06.2);
  4. simulación: se imprime el plan, y la simulación queda en la bitácora
     (R07.2);
  5. si se pasó `--dry-run`, termina aquí (IDG-R11.4);
  6. `$this->confirm()` con «no» por defecto. Sin un «sí», termina sin
     cambios (R06.8);
  7. resolución real.

## Datos

Sin `BYPASSRLS` ni funciones `SECURITY DEFINER` (RSP-NF04, S-03). Los
bloqueos consultivos no necesitan privilegios.

Una migración de esquema y una de datos, ninguna en tablas de un plantel:

1. **`platform_operators`**: se añaden `code_failures jsonb NOT NULL
   DEFAULT '[]'` y `blocked_until timestamptz NULL`. Hay una restricción
   `CHECK (jsonb_array_length(code_failures) <= 10)`. La tabla ya está bajo
   `RowLevelSecurity::enableOutsideTenants` y no cambia. `down()` quita las
   dos columnas.
2. **`platform_operator_recovery_codes`**: cada `code_hash` (SHA-256 del
   código normalizado) pasa a `hmac(code_hash, secreto)`, en PHP y por
   lotes, dentro de una transacción. La longitud sigue siendo 64, así que no
   cambia el esquema. Los códigos vigentes siguen valiendo, porque la huella
   nueva se calcula a partir de la antigua sin conocer el código (NF02). Si
   falta el secreto, la migración falla antes de tocar nada.

   **Esta migración no tiene vuelta atrás:** la huella con secreto no se
   puede revertir. `down()` lanza una excepción que lo explica (ver el plan
   de rollback).

## Patrones aplicados

| Patrón | Fuerza que resuelve (C-05) |
|--------|----------------------------|
| Bloqueo pesimista (consultivo, por transacción) | Cuatro reglas que se comprueban sobre lo leído y otra petición puede invalidar entre la lectura y la escritura (ADR 0014). |
| Releer bajo el bloqueo | Lo leído antes del bloqueo puede estar viejo. Solo lo releído vale para decidir. |
| Template method ligero (`CodeAttempt`) | Tres entradas (inicio de sesión, confirmación y consola) con la misma secuencia de bloquear, releer, contar fallos y bloquear. Un solo lugar evita que una de las tres se quede sin cerrar. |
| Interfaz de dominio (`RecoveryCodeHasher`) | El value object necesita un secreto que el dominio no puede leer (A-02). |
| Estrategia (comprobación del grupo en `AccountConsolidator`) | Dos llamantes, el panel o la consola e IDG-R09, con reglas distintas que deben comprobarse bajo el mismo bloqueo. |
| Efectos después del commit | El correo y la revocación de sesiones no se pueden deshacer, así que no deben ocurrir si la transacción se revierte (R04.4). |

## Modelo de amenazas (STRIDE)

| Amenaza | Vector | Mitigación | Verificado por |
|---------|--------|------------|----------------|
| **S**poofing | Con la contraseña robada de un operador, adivinar su TOTP repartiendo intentos entre muchas IP. | Límite por operador e IP en las dos entradas, y bloqueo de 15 minutos tras 10 fallos, sea cual sea la IP (R03). | `OperatorLockoutTest` (feature), `OperatorTest` (unit) |
| **S**poofing | En la consola, nombrar a otro operador. | TOTP interactivo de ese operador, que nunca se pasa como argumento (R06). | `ConsoleOperatorTest` |
| **S**poofing | Reutilizar a la vez un TOTP o un código de recuperación. | Bloqueo del operador y relectura del último paso o del código usado (R01.4, R01.5, R06.6). | `OperatorLockTest` (Isolation, dos conexiones) |
| **T**ampering | Un inicio de sesión simultáneo deshace una desactivación o un restablecimiento. | Bloqueo y relectura. `save()` exige el bloqueo (R01.1 – R01.3). | `OperatorLockTest`; `EloquentOperatorRepositoryTest` › «save without the lock fails» |
| **T**ampering | Dos resoluciones a la vez fusionan cuentas mantenidas separadas. | Bloqueo del correo, relectura y comprobación dentro de la transacción (R04). | `ConsolidationLockTest` (Isolation) |
| **T**ampering | Escribir en un plantel recién desactivado. | Bloqueo compartido o exclusivo del estado y `writableOf` (R08). | `InstitutionStatusLockTest` (Isolation), `TransferToInactiveTest` |
| **R**epudiation | Mirar la lista de casos, o hacer una vista previa, sin dejar rastro. | Entradas `consolidation.cases_listed` e `identity.consolidation.previewed` (R07). | `ConsolidationAuditTest` |
| **R**epudiation | Resolver sin haber visto la vista previa. | El caso de uso exige una vista previa de los últimos 10 minutos y registra el rechazo (R05). | `ResolveCaseHandlerTest`, `ConsolidationPanelTest` |
| **I**nformation disclosure | Una copia de la base de datos revela los códigos de recuperación por fuerza bruta. | Huella con un secreto que no está en la base de datos (NF02). | `KeyedRecoveryCodeHasherTest`; `RecoveryCodeRehashMigrationTest` |
| **I**nformation disclosure | El bloqueo o un rechazo revelan si un correo es de un operador, o el motivo de una desactivación. | Mensaje único de PLT-R01.3; los fallos con contraseña incorrecta no cuentan; mensaje genérico en el traslado (NF05). | `OperatorLockoutTest` › «same answer», `TransferToInactiveTest` › «no reason» |
| **D**enial of service | Bloquear a un operador sabiendo solo su correo. | Solo cuenta un código fallido con la contraseña correcta, o desde una sesión abierta (R03.3). | `OperatorLockoutTest` › «wrong passwords never block» |
| **D**enial of service | Un interbloqueo entre gestión e inicio de sesión. | Orden fijo: lista de operadores, después operador; el inicio de sesión solo toma uno (ADR 0014). | `OperatorLockTest` › «management and login, both orders» |
| **E**levation of privilege | Una acción sensible desde una vía nueva que no pasa por la ruta. | `PlatformAccess::withRecentTotp` en cada caso de uso sensible (NF03). | `SensitiveActionsNeedRecentTotpTest` (unit, por handler) |
| **E**levation of privilege | Quedarse sin Superadministrador, o colarse entre dos degradaciones. | Bloqueo de la lista de operadores y recuento releído (R02). | `OperatorLockTest` › «last SuperAdmin» |

## Errores

| Excepción de dominio | HTTP | Mensaje al usuario |
|----------------------|------|--------------------|
| `BusinessRuleViolation` (`email`), fallo de acceso o bloqueo | 422 | «Datos de acceso incorrectos.» |
| `BusinessRuleViolation` (`code`), confirmación fallida | 422 | «El código no es correcto.» Si con ese fallo se bloquea, la sesión ya está cerrada y la siguiente petición lleva al inicio de sesión. |
| `BusinessRuleViolation` (`operator`), último Superadministrador | 422 | «La plataforma no puede quedarse sin un Superadministrador activo.» |
| `BusinessRuleViolation` (`preview`) | 422 | «Revisa la vista previa antes de resolver.» |
| `BusinessRuleViolation` (`accounts`), cuentas y caso de la ruta no coinciden | 422 | «Esas cuentas no son las de este caso.» |
| `CaseResolutionRefused` (ya existe, como `BusinessRuleViolation`) | 422 | El mensaje actual de IDG-R11.5. |
| `BusinessRuleViolation` (`transfer`), destino inactivo | 422 | «El plantel destino no puede recibir el traslado por ahora.» |
| `AuthorizationDenied`, sin TOTP reciente en el caso de uso | 403 | «Confirma tu código antes de hacer esto.» La ruta ya redirige antes; esto solo cubre otras vías. |
| `InstitutionUnavailable` (Tenancy) | — | No llega a HTTP: la capturan `ApproveTransferHandler` y `RunInInstitution`. Si se escapara, es un fallo de programación (500). |

## Estrategia de pruebas

- **Dominio (unit):**
  - `OperatorTest`: fallos, bloqueo al décimo, vencimiento, que el
    restablecimiento vacía los fallos, que no hay otra forma de
    desbloquear, y que el bloqueo sube la versión de sesión;
  - `RecoveryCodesTest` con un hasher de prueba.
- **Aplicación (unit, adaptadores en memoria):**
  - `CodeAttempt` y los tres accesos, con un `OperatorLock` que cuenta y
    comprueba que se bloquea antes de releer;
  - gestión: orden de los bloqueos (lista y después operador);
  - `ResolveCaseHandler`: vista previa ausente, caducada, de otra decisión
    o de otras cuentas;
  - cada handler sensible rechaza sin TOTP reciente (NF03);
  - `AccountConsolidator`: con el grupo cambiado, no escribe, no envía
    correo ni revoca sesiones.
- **Infraestructura (integración, Postgres):**
  - `EloquentOperatorRepository`: `save()` falla sin el bloqueo y guarda
    los fallos;
  - `KeyedRecoveryCodeHasher`;
  - la migración: un código creado antes sigue valiendo después;
  - `RunInInstitution`: descarta el trabajo y escribe en el registro.
- **Concurrencia (`tests/Isolation`, dos conexiones reales, NF01):** con el
  patrón de `EnrollmentConcurrencyTest`, una conexión PDO aparte toma el
  bloqueo y hace la acción rival sin confirmar. Se comprueba que el caso de
  uso de la aplicación espera (`pg_try_advisory_xact_lock` devuelve
  `false`) y, tras el commit rival, que decide con lo releído:
  - `OperatorLockTest`: desactivación contra inicio de sesión, en los dos
    órdenes; dos inicios con el mismo TOTP; regeneración contra un código
    antiguo; dos degradaciones del último par de Superadministradores;
  - `ConsolidationLockTest`: «separar» contra «consolidar», en los dos
    órdenes;
  - `InstitutionStatusLockTest`: desactivación contra aprobación de un
    traslado.

  Todos pasan también con PgBouncer (RLT-NF01).
- **Feature (HTTP y consola):**
  - `OperatorLockoutTest`: 10 fallos desde IP distintas, mismo mensaje,
    correo, bitácora y sesiones cerradas;
  - limitador de `/confirmar-codigo`;
  - `ConsolidationPanelTest`: POST directo a `/resolver` sin vista previa,
    y caso de la ruta distinto;
  - `ConsolidationAuditTest`;
  - `ConsoleOperatorTest`: sin terminal, TOTP incorrecto, usado, código de
    recuperación, confirmación «no», `--dry-run` y la bitácora;
  - `TransferToInactiveTest`: rechazo, sigue pendiente, aprobación tras
    reactivar y caducidad.
- **Arquitectura:**
  - `pg_advisory` solo en los adaptadores de bloqueo;
  - `OperatorRepository::save` no se llama desde ningún caso de uso que no
    reciba `OperatorLock` (regla complementaria del test de
    infraestructura).
- **Rendimiento:** PLT-NF06 y RLT-NF03 siguen en `--group=perf` sin
  cambios (NF06).

Los tests existentes que cambian a propósito (NF07) son los del comando de
consola de IDG-R11, que hoy no piden TOTP, y los del panel que resuelven
sin vista previa. Solo cambia su preparación; las aserciones siguen igual.

## Decisiones y alternativas

| Decisión | Alternativa descartada | Motivo |
|----------|------------------------|--------|
| 1. Bloqueos consultivos por transacción ([ADR 0014](../adr/0014-carreras-de-plataforma-e-identidad.md)) | `FOR UPDATE`, versión optimista, escrituras condicionales, `SERIALIZABLE` | Ver el ADR: un mecanismo para las cuatro carreras, también para un correo y para la lista de operadores. |
| 2. Fallos y bloqueo en la fila del operador (`jsonb` + `blocked_until`) | Tabla aparte de intentos, o leerlos de la bitácora | Viven bajo el mismo bloqueo que el resto del operador, en una sola escritura. La bitácora no guarda el operador de un acceso fallido (S-05), y leerla para decidir la acoplaría a la autenticación. |
| 3. Huella `HMAC(sha256(código))` con un secreto propio, migrada sobre la huella actual | Volver a generar todos los códigos; usar `APP_KEY`; un hash de contraseña (bcrypt) | Regenerar obliga a todos los operadores a actuar, y el requisito lo descarta. Rotar `APP_KEY` invalidaría los códigos. Con bcrypt no se puede migrar sin el código en claro, y obliga a comparar 10 hashes lentos en cada inicio de sesión. |
| 4. Vista previa en la sesión del panel | Tabla de vistas previas, o un token firmado en el formulario | La sesión ya es del operador y caduca con él. Un token en el formulario lo puede reenviar el propio cliente sin haber visto nada. |
| 5. `AccountConsolidator` recibe la comprobación del grupo | Bloquear en cada llamante y dejar el consolidador como está | El bloqueo, la relectura y la escritura deben ir en la misma transacción. Si cada llamante lo hiciera por su cuenta, el próximo podría olvidarlo. |
| 6. El bloqueo del estado del plantel es compartido para escribir y exclusivo para cambiarlo | Comprobar el estado sin bloqueo | Sin bloqueo queda una ventana entre leer «activo» y confirmar la escritura. Con el bloqueo compartido, los traslados no se esperan entre sí. |
| 7. El trabajo de un plantel inactivo se descarta en el middleware | Lanzar una excepción (la cola reintenta) o `release()` | La decisión de producto es descartar sin reintentar (RSP-R08.5). |
| 8. La consola rechaza sin terminal | Leer el TOTP de una variable de entorno | Una variable de entorno quedaría en el historial o en los scripts, que es lo que evita R06.3. |

## Riesgos y plan de rollback

**Riesgos**

- **Olvidar un bloqueo en un caso de uso nuevo.** Lo cubren
  `save()`, que exige el bloqueo, y el test de arquitectura.
- **Esperas en el inicio de sesión** mientras se gestiona al operador. Son
  de milisegundos. Las transacciones con bloqueo no hacen nada lento: el
  correo y la revocación van después del commit.
- **Falta `PLATFORM_RECOVERY_CODE_KEY` en un entorno.** La migración falla
  antes de tocar nada y la aplicación no arranca fuera de `local` y
  `testing`. Se añade a `.env.example` y al procedimiento de despliegue.
- **Perder o rotar `PLATFORM_RECOVERY_CODE_KEY`** invalida todos los códigos
  de recuperación. Los operadores siguen entrando con su TOTP y pueden
  regenerarlos. Si alguno perdió el TOTP, se le restablece el segundo
  factor (PLT-R03.7). Se documenta junto al secreto.
- **Tests de concurrencia frágiles.** Siguen el patrón ya estable de
  `EnrollmentConcurrencyTest`, que decide con `pg_try_advisory_xact_lock`
  y no con tiempos.

**Rollback**

- **Código:** se revierte el PR. El esquema nuevo (dos columnas con valor
  por defecto) es compatible con el código anterior, que lo ignora.
- **Columnas:** `down()` las quita sin perder nada que no sea de esta spec.
- **Huellas de los códigos de recuperación:** no se pueden revertir. Con el
  código anterior, los códigos de recuperación dejan de valer; el TOTP
  sigue funcionando. Si hace falta volver atrás:
  - opción A: restaurar `platform_operator_recovery_codes` desde el
    respaldo tomado justo antes de migrar (paso del despliegue);
  - opción B: que cada operador regenere sus códigos.

  `down()` lanza una excepción con estas instrucciones, en lugar de dejar
  huellas que no corresponden a ningún código.
- **Orden de despliegue:**
  1. configurar `PLATFORM_RECOVERY_CODE_KEY`;
  2. respaldar la tabla de códigos;
  3. migrar;
  4. desplegar.
