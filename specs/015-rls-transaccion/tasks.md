---
spec: 015-rls-transaccion
estado: implementando
fecha: 2026-10-26
diseño: design.md
---

# Tareas — RLS por transacción y cierre de la revisión de seguridad

Reglas:

- Una tarea = un cambio pequeño, revisable y que deja la suite en verde.
- Cada tarea cita los criterios que cubre y el test que la verifica.
- TDD (Q-02): primero el test en rojo.
- Marca `[x]` al terminar y añade el test a la matriz de `requirements.md`.
- Q-03 / RLT-NF04: los tests existentes solo cambian su preparación, salvo
  los que se indican.

Tres PRs:

- **A:** el plantel por transacción.
- **B:** PgBouncer en desarrollo y en CI.
- **C:** traspaso, traslados e invitaciones.

A va antes que B, porque el test de B solo pasa con A. C es independiente
de los dos.

## PR A — El plantel vive en la transacción

### Fase 1 — Aislamiento real y núcleo de Tenancy

- [x] **T-01** — Suite de aislamiento sin transacción envolvente.
  - Qué se hace:
    - crear `tests/Isolation/`, con su configuración en `tests/Pest.php`:
      `DatabaseTruncation`, `group=rls`;
    - escribir `TenantPerTransactionTest`, que hace peticiones HTTP
      seguidas sobre la misma conexión.

    Con el ajuste por conexión actual, el test queda en rojo: «una petición
    de B, en la misma conexión que una de A, no ve nada de A» y «tras una
    petición que acaba en 500 la conexión queda sin plantel».
  - Criterios: RLT-R01.2, R01.3
  - Test: `tests/Isolation/TenantPerTransactionTest.php`
  - Hecho cuando: el test existe y falla por la fuga, no por la
    preparación.
- [x] **T-02** — `RowLevelSecurity::applyLocal(?int)`, que usa
  `set_config(..., true)`, y `currentSetting()`. Desaparecen
  `setInstitution` y `clearInstitution`.
  - Regla de arquitectura: ningún `set_config(..., false)` sobre
    `app.current_institution_id` en `src/`; `applyLocal` solo desde
    `PostgresTenantActivator`.
  - Criterios: RLT-R01.2, NF02
  - Test: `tests/Feature/Tenancy/Infrastructure/PostgresTenantActivatorTest.php`
    › «el ajuste desaparece al terminar la transacción»;
    `tests/Architecture/LayersTest.php`
- [x] **T-03** — `TenantActivator::within()` y `activate()` que exige una
  transacción abierta.
  - Qué hace `within()`:
    1. abre un *savepoint* o una transacción;
    2. fija el plantel;
    3. ejecuta el callable;
    4. restaura el plantel anterior;
    5. comprueba el plantel activo, y si no es el esperado lanza
       `TenantLeak` dentro de la transacción.

    El plantel en memoria y los *hooks* se restauran en `finally`.
  - Criterios: RLT-R02.1, R02.2, R02.3
  - Test: `PostgresTenantActivatorTest`:
    - «`activate()` fuera de una transacción lanza»;
    - «`within()` vuelve al anterior, también si falla»;
    - «un plantel que queda cambiado revierte y lanza `TenantLeak`».

    Los helpers `tenant()` y `useTenant()` de `tests/Pest.php` siguen
    funcionando dentro de `RefreshDatabase`.

### Fase 2 — Peticiones

- [x] **T-04** — `ResolveTenant` abre la transacción de la petición, activa
  el plantel y confirma o revierte.
  - Confirma si la respuesta es menor que 500. Revierte si es 5xx o si
    escapa una excepción.
  - Prioridad de middleware en `bootstrap/app.php`.
  - Regla de arquitectura: ningún middleware de ruta con base de datos va
    antes de `ResolveTenant` en el grupo del plantel.
  - Criterios: RLT-R01.1 – R01.3, R01.5, R01.6
  - Test: `tests/Isolation/TenantPerTransactionTest.php` en verde:
    - «una petición de B no ve nada de A»;
    - «tras un 500 no queda plantel»;
    - «un *savepoint* revertido conserva el plantel de la petición»;
    - «el dominio central no ve nada».

    Más un test que falla si una ruta del plantel devuelve un
    `StreamedResponse`.
  - Hecho cuando: la suite completa sigue en verde sin cambiar la lógica de
    ningún test (RLT-NF04).
- [x] **T-05** — Los seis cambios de plantel pasan a `within()`:
  - `AcceptInvitationHandler` y `JoinInstitutionHandler`;
  - `EloquentAccountMerger` y `EloquentInstitutionScope`;
  - `LinkGuardianAccountHandler` y `TenantSwitchingTransferCopier`;
  - `TenantScopedReader`.

  Regla de arquitectura: `activate()` y `deactivate()` solo desde
  `ResolveTenant`.
  - Criterios: RLT-R02.1 – R02.3
  - Test: los existentes de traslados (`TransferTest`), vínculo
    (`GuardianAccountTest`), invitaciones, consolidación y
    `TenantScopedReaderTest`, sin cambiar su lógica; más
    `tests/Isolation/TenantSwitchTest.php` › «un cambio de plantel que falla
    a mitad deja el plantel de la petición»; `LayersTest`.

### Fase 3 — Cola, consola y rendimiento

- [x] **T-06** — Cola:
  - `InMemoryCurrentTenant` pasa a *scoped*;
  - escuchador de `JobProcessing`;
  - interfaz `InstitutionJob` y middleware `RunInInstitution`;
  - `after_commit => true` en `config/queue.php`.
  - Criterios: RLT-R03.1, R03.2
  - Test: `tests/Isolation/QueueTenantTest.php`:
    - «un trabajo empieza sin plantel aunque el anterior lo dejara»;
    - «un `InstitutionJob` actúa solo en su plantel y lo suelta»;
    - «un trabajo encolado en una petición no corre antes del `COMMIT`».

    El trabajo de prueba vive en `tests/Fixtures/Tenancy/`.
- [x] **T-07** — Consola: `identity:close-due-accounts` recorre los
  planteles con `within()`, mediante `EloquentInstitutionScope`.
  - Criterios: RLT-R03.3
  - Test: `tests/Isolation/ConsoleTenantTest.php` › «un comando que
    recorre dos planteles aplica a cada uno solo su RLS»; y
    `CloseDueAccountsTest`, el existente, sin cambiar su lógica.
- [x] **T-08** — Coste de la transacción de petición.
  - Criterios: RLT-NF03
  - Test: `tests/Feature/Tenancy/RlsOverheadTest.php` (`group=perf`):
    - p95 de una petición de un plantel, con y sin la transacción, con 5 ms
      como máximo de diferencia;
    - los tests `perf` existentes siguen en verde.

## PR B — PgBouncer en modo transacción

### Fase 4 — Infraestructura de pruebas

- [ ] **T-09** — PgBouncer en desarrollo y en CI:
  - servicio `pgbouncer` (1.21 o posterior, `pool_mode=transaction`,
    `max_prepared_statements`) en `docker-compose.yml`;
  - `phpunit.pgbouncer.xml`, con `DB_HOST=pgbouncer`;
  - job `rls-pgbouncer` en `.github/workflows/ci.yml`, que corre
    `--group=rls` y `tests/Isolation`.
  - Criterios: RLT-R01.4, NF01
  - Test: `tests/Isolation/PgBouncerLeakTest.php` (solo con
    `phpunit.pgbouncer.xml`) › «lo que fija un cliente dentro de su
    transacción no lo ve otro por el mismo pool», con dos clientes y
    `pool_size=1`. Se comprueba en rojo con el ajuste por conexión antiguo.
  - Hecho cuando: `--group=rls` y `tests/Isolation` pasan contra PgBouncer
    en local y en CI.

## PR C — Traspaso, traslados e invitaciones

### Fase 5 — Dominio y aplicación

- [ ] **T-10** — `Account::ensureCanJoinInstitutions()` y la excepción
  `InvitationBlockedByConsolidation`, con un mensaje por estado.
  - Criterios: RLT-R06.1, R06.2, R06.5, NF05
  - Test: `tests/Unit/IdentityAccess/Domain/AccountTest.php`, con los
    cuatro estados (`null`, `pending`, `review`, `separated`).
- [ ] **T-11** — Puerto `BrowserBinding`.
  - Cambia la firma de `HandoffTokens`: `issue(…, browserHash)` y
    `consume(…, ?browserSecret)`.
  - `IssueHandoffHandler` y `ConsumeHandoffHandler`:
    - el token se liga al navegador y se compara con `hash_equals`;
    - el token solo se quema tras validar el navegador;
    - no se sustituye una sesión de otra cuenta (+
      `Authenticator::currentAccountId()`).
  - Criterios: RLT-R04.1 – R04.4
  - Test: `tests/Unit/IdentityAccess/Application/HandoffBindingTest.php`,
    con un `BrowserBinding` falso.
- [ ] **T-12** — `JoinInstitutionHandler` llama a
  `ensureCanJoinInstitutions()` antes de `within()`; la invitación no se
  toca.
  - Criterios: RLT-R06.1 – R06.4
  - Test: `tests/Unit/IdentityAccess/Application/JoinInstitutionTest.php`
    › «rechaza sin activar ningún plantel ni tocar la invitación».

### Fase 6 — Infraestructura y HTTP

- [ ] **T-13** — `CookieBrowserBinding` y los cambios de HTTP.
  - La cookie `aulax_browser`: dominio `.APP_DOMAIN`, HttpOnly, Secure
    según la configuración, SameSite=Lax, de sesión, cifrada.
  - Se rota al iniciar y al cerrar la sesión central.
  - `CacheHandoffTokens` guarda el hash del secreto.
  - Criterios: RLT-R04.1 – R04.4, NF05
  - Test: `tests/Feature/IdentityAccess/HandoffBindingTest.php`:
    - «otro navegador va al login central con el plantel elegido»;
    - «no revela el motivo»;
    - «no sustituye otra sesión»;
    - «el titular aún puede usar el enlace».

    `CacheHandoffTokensTest` y `HandoffTest` solo cambian su preparación:
    reciben la cookie.
- [ ] **T-14** — Migración `…_guard_transfer_request_origin`, en su propia
  tarea:
  - política `addressed_insert` con origen nulo y estado `pending`;
  - *trigger* `guard_transfer_request_update()` sin `SECURITY DEFINER`;
  - `RowLevelSecurity::enableAddressedByIdentification` incorpora ambas
    cosas.
  - Criterios: RLT-R05.1 – R05.4, NF02
  - Test: `tests/Feature/Students/TransferRequestOriginTest.php`
    (`group=rls`, SQL directo):
    - «el destino no fija el origen al crear»;
    - «el destino no cambia el origen»;
    - «el origen solo se pone a sí mismo al responder»;
    - «nadie cambia el origen ya respondido»;
    - «las escrituras de SPEC-009 siguen pasando».

    Además: `migrate:rollback` de la migración y vuelta a `migrate`, y
    `TransferTest`, el existente, sin cambiar su lógica.
- [ ] **T-15** — HTTP de las invitaciones bloqueadas: el mensaje de cada
  estado, con el enlace a la consolidación cuando está pendiente.
  - Criterios: RLT-R06.1 – R06.5, NF05
  - Test: `tests/Feature/IdentityAccess/InvitationConsolidationTest.php`:
    - «pendiente: ofrece consolidar y no se une»;
    - «revisión: avisa de soporte»;
    - «separadas: pide otro correo»;
    - «la invitación sigue válida y se acepta tras consolidar».

### Fase 7 — Documentación

- [ ] **T-16** — Cambios en otras specs y ADRs:
  - SPEC-001: nota sobre TEN-R05.6;
  - SPEC-004: IDG-NF03 e IDG-R04.5;
  - SPEC-009: nota en su `design.md`;
  - ADR 0003, 0006, 0008 y 0010;
  - roadmap: se cierran los cuatro hallazgos.

  Se aplica en el PR que cambia cada comportamiento (P-04): A para TEN y
  los ADR 0003, 0006 y 0010; C para IDG, EST y el ADR 0008.
  - Criterios: —
  - Test: `tests/Architecture/SpecsTest.php`

## Verificación final

- [ ] `php artisan test --compact` en verde (incluidos `--group=rls`,
  `--group=perf` y `tests/Isolation`), y tests de navegador en verde.
- [ ] `--group=rls` y `tests/Isolation` en verde contra PgBouncer
  (`phpunit.pgbouncer.xml`) y en el job de CI.
- [ ] Tests de arquitectura en verde:
  - sin `set_config(..., false)` sobre el plantel;
  - `activate()` solo desde `ResolveTenant`;
  - orden de middleware.
- [ ] `vendor/bin/pint --dirty` sin cambios; `npm run build` sin errores.
- [ ] `migrate:fresh --seed` y rollback de la migración nueva.
- [ ] Matriz de trazabilidad completa.
- [ ] `/security-review` de cada PR (tenancy y autenticación; S-12).
- [ ] `estado` de requirements/design/tasks actualizado.

## Cobertura de criterios

| Grupo | Tareas |
|-------|--------|
| RLT-R01 | T-01, T-02, T-04, T-09 |
| RLT-R02 | T-03, T-05 |
| RLT-R03 | T-06, T-07 |
| RLT-R04 | T-11, T-13 |
| RLT-R05 | T-14 |
| RLT-R06 | T-10, T-12, T-15 |
| RLT-NF01 | T-09 |
| RLT-NF02 | T-02, T-14 |
| RLT-NF03 | T-08 |
| RLT-NF04 | T-04, T-05, T-07, T-13, T-14 y verificación final |
| RLT-NF05 | T-10, T-13, T-15 |
