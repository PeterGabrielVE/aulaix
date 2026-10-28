---
spec: 015-rls-transaccion
estado: implementada
fecha: 2026-10-26
requisitos: requirements.md
---

# Diseño — RLS por transacción y cierre de la revisión de seguridad

## Resumen

El plantel activo pasa de la conexión a la transacción (ADR 0011):

- se fija solo con `set_config(..., true)`;
- cada petición de un plantel corre entera en una transacción;
- todo cambio de plantel a mitad de una unidad de trabajo pasa por
  `TenantActivator::within()`, que restaura y comprueba.

Además se cierran tres hallazgos de la revisión de seguridad:

- **Traspaso:** se liga a un secreto del navegador, guardado en una cookie
  del dominio padre (IdentityAccess).
- **`transfer_requests`:** un *trigger* sin privilegios impide cambiar el
  origen, y la política `INSERT` impide fijarlo al crear (Students y
  Tenancy).
- **Invitaciones:** se rechazan mientras el correo espera consolidación
  (IdentityAccess, dominio).

Módulos:

- **Tenancy:** activación, RLS y cola.
- **IdentityAccess:** traspaso e invitaciones.
- **Students y Platform:** pasan a `within()`.
- **`app/`:** middleware y configuración.

## Módulo y capas

```
src/Tenancy/
├── Application/
│   ├── TenantActivator.php        activate() (solo ResolveTenant) + within() (todo cambio de plantel)
│   ├── CurrentTenant.php          sin cambios; su implementación pasa a «scoped»
│   └── InstitutionJob.php         interfaz: un trabajo de la cola que actúa sobre un plantel (institutionId())
└── Infrastructure/
    ├── Persistence/RowLevelSecurity.php    setInstitution/clearInstitution → applyLocal(?int) con set_config(..., true);
    │                                       currentSetting(); política addressed_insert con origen nulo
    ├── PostgresTenantActivator.php         activate() exige transacción abierta; within() con savepoint, restauración y comprobación
    ├── Queue/RunInInstitution.php          middleware de trabajo: ejecuta handle() con within()
    ├── Queue/EnsureNoTenantOnJobStart.php  escuchador de JobProcessing (RLT-R03.1)
    └── TenancyServiceProvider.php          InMemoryCurrentTenant «scoped»; registra el escuchador

src/IdentityAccess/
├── Domain/Account/
│   ├── Account.php                          ensureCanJoinInstitutions(): lanza InvitationBlockedByConsolidation
│   └── InvitationBlockedByConsolidation.php BusinessRuleViolation en «email», un mensaje por estado
├── Application/
│   ├── Port/BrowserBinding.php    secreto del navegador: issue() en el dominio central, presented() en el plantel
│   ├── Port/HandoffTokens.php     issue(…, browserHash) / consume(…, ?browserSecret)
│   ├── Auth/IssueHandoffHandler.php    pide el secreto y lo liga al token
│   ├── Auth/ConsumeHandoffHandler.php  compara el secreto; no sustituye otra sesión (RLT-R04.4)
│   ├── Auth/JoinInstitutionHandler.php y AcceptInvitationHandler.php  ensureCanJoinInstitutions(); within()
│   └── Port/Authenticator.php     + currentAccountId()
└── Infrastructure/
    ├── Auth/CookieBrowserBinding.php        cookie «aulax_browser» en .APP_DOMAIN, HttpOnly, Secure, SameSite=Lax, de sesión
    ├── Auth/CacheHandoffTokens.php          el payload guarda el hash del secreto
    └── Persistence/EloquentInstitutionScope.php, EloquentAccountMerger.php → within()

src/Students/  LinkGuardianAccountHandler, TenantSwitchingTransferCopier → within()
src/Platform/  TenantScopedReader → within() (sigue siendo de solo lectura y siempre revertida)

app/Http/Middleware/ResolveTenant.php   transacción de la petición + activate()
config/queue.php                        after_commit = true en todas las conexiones
database/migrations/…_guard_transfer_request_origin.php   trigger + política INSERT
docker-compose.yml, .github/workflows/ci.yml, phpunit.pgbouncer.xml   PgBouncer en modo transacción (RLT-NF01)
```

## Modelo de dominio

| Elemento | Tipo | Invariantes / responsabilidad |
|----------|------|-------------------------------|
| `Account::ensureCanJoinInstitutions()` | Regla del agregado | Una cuenta cuyo correo está pendiente, en revisión o separado no se une a un plantel nuevo. Lanza `InvitationBlockedByConsolidation` con el mensaje de su estado (RLT-R06.1, R06.2, R06.5). |
| `InvitationBlockedByConsolidation` | Excepción de dominio (`BusinessRuleViolation`, campo `email`) | `pending`: «Antes de aceptar, unifica tus cuentas con este correo.», con el enlace a la consolidación. `review`: «Soporte está revisando las cuentas con este correo. Podrás aceptar la invitación cuando termine.» `separated`: «Hay varias cuentas con este correo. Pide al plantel que te invite con otro correo.» |
| `TenantContext` | Value Object (existente) | Sin cambios. |

La política de `transfer_requests` (RLT-R05) es un invariante de la base de
datos, no del dominio. Se comprueba con SQL directo, como el resto de la RLS
(S-01).

## Casos de uso (Application)

| Caso de uso | Entrada | Salida | Criterios |
|-------------|---------|--------|-----------|
| `TenantActivator::activate` (cambia) | `TenantContext` | — (exige transacción abierta) | RLT-R01.1, R01.5 |
| `TenantActivator::within` (nuevo) | `TenantContext`, callable | lo que devuelva el callable | RLT-R02.1 – R02.3, R03.2, R03.3 |
| `IssueHandoffHandler` (cambia) | cuenta, plantel | enlace ligado al navegador | RLT-R04.1 |
| `ConsumeHandoffHandler` (cambia) | token | sesión del plantel, o rechazo | RLT-R04.2 – R04.4 |
| `JoinInstitutionHandler` (cambia) | cuenta, subdominio, correo invitado | membresía, o rechazo por consolidación | RLT-R06.1 – R06.5 |
| `AcceptInvitationHandler` (cambia) | correo, contraseña, token | sin cambio de regla (solo pasa a `within()`); una cuenta existente sigue yendo a `JoinInstitutionHandler` | RLT-R02.1 |
| `LinkGuardianAccountHandler`, `TenantSwitchingTransferCopier`, `EloquentInstitutionScope`, `EloquentAccountMerger`, `TenantScopedReader` (cambian) | — | igual que hoy, con `within()` | RLT-R02.1 – R02.3, R03.3 |

Notas:

- **`within()` paso a paso:**
  1. `TransactionManager::run` (transacción o *savepoint*);
  2. `set_config(var, destino, true)`;
  3. se ejecuta el callable;
  4. `set_config(var, anterior, true)`;
  5. se lee `current_setting`, y si no coincide con el anterior lanza
     `TenantLeak` (`LogicException`) **dentro** de la transacción.

  El plantel en memoria y los *hooks* (equipo de permisos) se restauran en
  `finally`. Si el callable lanza una excepción, el *savepoint* se revierte y
  PostgreSQL deshace el `set_config`.
- **Rechazo por consolidación:** `JoinInstitutionHandler` llama a
  `ensureCanJoinInstitutions()` **antes** de `within()`. Así el rechazo no
  toca ningún plantel ni la invitación, que sigue válida con su plazo
  (RLT-R06.3). Cuando la consolidación termina (`consolidation = null`), la
  regla deja pasar (RLT-R06.4).
- **`ConsumeHandoffHandler`:**
  1. `BrowserBinding::presented()` debe existir, y su hash debe coincidir
     con el del token (`hash_equals`). Si no, `HandoffRejected`, igual que
     un enlace caducado (RLT-R04.2, R04.3).
  2. Si ya hay una sesión en ese plantel con **otra** cuenta,
     `HandoffRejected` sin tocarla (RLT-R04.4). Con la misma cuenta, solo
     redirige.

  El token se quema solo después de pasar las dos comprobaciones. Así, un
  enlace abierto por la persona equivocada no deja sin traspaso a su
  titular, que aún puede usarlo dentro de los 60 s.

## Puertos y adaptadores

| Puerto (interfaz) | Capa | Adaptador | Notas |
|-------------------|------|-----------|-------|
| `TenantActivator` (existente, + `within`) | Tenancy Application | `PostgresTenantActivator` | Único sitio que escribe la variable del plantel. |
| `BrowserBinding` (nuevo) | IdentityAccess Application | `CookieBrowserBinding` | Secreto aleatorio de 32 bytes, en una cookie cifrada del dominio padre que vive lo que la sesión del navegador. Se rota al iniciar y al cerrar la sesión central. Hace falta porque cada plantel tiene su propia cookie de sesión (`SESSION_DOMAIN=null`): la sesión central no llega al subdominio. |
| `HandoffTokens` (existente, cambia firma) | IdentityAccess Application | `CacheHandoffTokens` | El payload cifrado añade `browser` (SHA-256 del secreto). |
| `Authenticator` (existente, + `currentAccountId`) | IdentityAccess Application | `LaravelAuthenticator` | Para RLT-R04.4. |

**Por qué `BrowserBinding` es un puerto:** tiene un adaptador real (cookie)
y permite probar `Issue`/`ConsumeHandoffHandler` en unidad, sin HTTP.

## Adaptadores de entrada (`app/`)

- **`ResolveTenant`:**
  1. resuelve el plantel;
  2. `DB::beginTransaction()`;
  3. `activate()`;
  4. `$next`;
  5. `commit()` si el estado es menor que 500, y `rollBack()` si no o si
     escapa una excepción, que relanza.

  Debe ser el **primer** middleware de ruta que toque la base de datos en el
  grupo del plantel: se fija con `$middleware->priority()` en
  `bootstrap/app.php`. Un test de arquitectura comprueba el orden.
- **Controladores de traspaso:** `Central\InstitutionsController`,
  `Central\LoginController` y `AcceptInvitationController::join` no
  cambian; el secreto lo pide el caso de uso a su puerto. `HandoffController`
  tampoco cambia.
- **Cierre de sesión central:** `AuthenticatedSessionController::destroy`
  rota el secreto del navegador.
- **Cola:**
  - `after_commit => true` en `config/queue.php`;
  - el escuchador `JobProcessing` vive en `TenancyServiceProvider`;
  - los trabajos que implementen `InstitutionJob` llevan el middleware
    `RunInInstitution`.

  Hoy ningún trabajo lo implementa; lo prueba un trabajo de prueba en
  `tests/Fixtures`.

## Datos

Migración `…_guard_transfer_request_origin`:

- **Política `addressed_insert`:** se recrea con
  `WITH CHECK (institution_id = actual AND origin_institution_id IS NULL AND status = 'pending')`.
  Al crear, nadie fija el origen (RLT-R05.1).
- **Función `guard_transfer_request_update()`:** función de *trigger*
  plpgsql, sin `SECURITY DEFINER` (corre como el rol de la aplicación,
  RLT-NF02). Se dispara `BEFORE UPDATE ON transfer_requests FOR EACH ROW`:
  - `institution_id` e `identification` no cambian nunca;
  - si cambia `origin_institution_id`, solo se admite cuando el anterior es
    nulo, la fila está `pending`, el nuevo es el plantel activo y ese
    plantel no es el destino (RLT-R05.2);
  - si no, `RAISE EXCEPTION` con `ERRCODE = 'insufficient_privilege'`
    (RLT-R05.1, R05.3).
- **`down()`:** borra el *trigger* y la función, y restaura la política
  anterior.
- `RowLevelSecurity::enableAddressedByIdentification` incorpora ambas
  cosas, para que las tablas futuras del mismo tipo nazcan protegidas.
  `ADDRESSED_POLICIES` no cambia de nombres.

No hay migración de datos: las solicitudes existentes ya cumplen las reglas.
Una con un origen que no la respondió no puede existir por la aplicación, y
el test lo comprueba.

## Patrones aplicados

| Patrón | Fuerza que resuelve (C-05) |
|--------|----------------------------|
| **Unit of Work por petición** (transacción de petición en `ResolveTenant`) | Sentencias sueltas con RLS en un pool en modo transacción. Convierte el plantel en una propiedad de la transacción, que PostgreSQL limpia solo. |
| **Execute Around** (`within()`) | Seis cambios de plantel con la restauración copiada a mano. Uno solo, que restaura, comprueba y falla cerrando. |
| **Fail closed** (`activate()` sin transacción lanza; `TenantLeak`) | Un olvido no debe convertirse en una fuga silenciosa. |
| **Middleware de trabajo** (`RunInInstitution`) | El plantel de un trabajo viaja con él, no con el proceso que lo ejecuta. |
| **Trigger de transición** | `WITH CHECK` no ve el valor anterior. Solo un *trigger* compara `OLD` y `NEW` sin privilegios extra. |

## Modelo de amenazas (STRIDE)

| Amenaza | Vector | Mitigación | Verificado por |
|---------|--------|------------|----------------|
| **S**poofing | Login CSRF: alguien envía su enlace de traspaso y la víctima entra en la sesión del atacante. | El token lleva el hash del secreto del navegador que lo pidió, en una cookie HttpOnly del dominio padre. Sin ella, el enlace se rechaza igual que uno caducado. No sustituye una sesión de otra cuenta. | `HandoffBindingTest`: «otro navegador no entra», «no revela el motivo», «no sustituye otra sesión»; unit de `ConsumeHandoffHandler` |
| **S**poofing | Un plantel se presenta como origen de una solicitud que no respondió. | Política `INSERT` con origen nulo; *trigger* de transición. | `TransferRequestOriginTest` (`group=rls`, SQL directo) |
| **T**ampering | Escritura en un plantel con el plantel de otra petición (conexión compartida o pool). | Plantel solo en la transacción; una transacción por petición; `within()` con comprobación. | `TenantPerTransactionTest` (`tests/Isolation`, sin transacción envolvente); suite `rls` por PgBouncer en CI |
| **T**ampering | Un trabajo de la cola escribe con el plantel del trabajo anterior. | `CurrentTenant` *scoped*; comprobación en `JobProcessing`; `RunInInstitution`. | `QueueTenantTest` |
| **R**epudiation | Invitación aceptada por una cuenta histórica que no es del titular. | Rechazo mientras el correo espera consolidación; queda una sola cuenta responsable. | `InvitationConsolidationTest` |
| **I**nformation disclosure | Lectura de filas de otro plantel por una conexión reutilizada o una sentencia que cae en otra conexión del pool. | Ver Tampering. Sin plantel, nada es visible (TEN-R05.1). | `TenantPerTransactionTest`; `PgBouncerLeakTest` (dos clientes, `pool_size=1`) |
| **I**nformation disclosure | El rechazo del traspaso o de la invitación revela otra cuenta. | Mismo rechazo que un enlace caducado; los mensajes de consolidación hablan solo del correo de la propia cuenta (RLT-NF05). | `HandoffBindingTest`, `InvitationConsolidationTest` |
| **D**enial of service | Transacciones largas que retienen bloqueos o agotan el pool. | Peticiones de plantel cortas; nada externo y largo dentro (a la cola, tras el `COMMIT`). Pool dimensionado por peticiones concurrentes (ADR 0011). | `RlsOverheadTest` (`group=perf`) y los umbrales de rendimiento existentes |
| **D**enial of service | Un enlace abierto por un tercero quema el token del titular. | El token se quema solo tras validar el navegador. | `HandoffBindingTest`: «el titular aún puede usarlo» |
| **E**levation of privilege | Código nuevo que fija el plantel por conexión o activa sin restaurar. | Reglas de arquitectura: sin `set_config(..., false)` sobre la variable; `activate()` solo desde `ResolveTenant`; `within()` en el resto. Ni `BYPASSRLS` ni `SECURITY DEFINER` (S-03). | `tests/Architecture/LayersTest.php`; `RlsRoleTest` existente |

## Errores

| Excepción de dominio | HTTP | Mensaje al usuario |
|----------------------|------|--------------------|
| `HandoffRejected` (existente; ahora también por otro navegador u otra sesión) | 302 al login central con el plantel elegido | Ninguno específico (RLT-R04.3) |
| `InvitationBlockedByConsolidation` | 422 (campo `email`) | Uno por estado (ver Modelo de dominio) |
| `TenantLeak` (`LogicException`) | 500, con la transacción revertida | Error genérico |
| Violación del *trigger* o la política (`42501`) | 500; la aplicación nunca la provoca | Error genérico |

## Estrategia de pruebas

- **Dominio (unit):** `Account::ensureCanJoinInstitutions` en los cuatro
  estados (`null`, `pending`, `review`, `separated`).
- **Aplicación (unit, adaptadores en memoria):**
  - `IssueHandoffHandler` y `ConsumeHandoffHandler`, con un `BrowserBinding`
    falso: liga, rechaza, no quema y no sustituye;
  - `JoinInstitutionHandler`: rechaza antes de activar ningún plantel.
- **Infraestructura (feature, base real):**
  - `PostgresTenantActivatorTest`:
    - `activate()` sin transacción lanza;
    - `within()` restaura el anterior, también con excepción;
    - un `set_config` forzado dentro de `within()` produce `TenantLeak` y la
      reversión;
  - `TransferRequestOriginTest` (`rls`): las cuatro reglas con SQL directo,
    y las escrituras de SPEC-009 siguen pasando (RLT-R05.4);
  - `QueueTenantTest`: un trabajo empieza sin plantel tras otro que lo
    dejó; `RunInInstitution` activa el suyo y lo suelta.
- **Aislamiento real (`tests/Isolation`, `group=rls`):** usa
  `DatabaseTruncation`, no `RefreshDatabase`, para que no haya una
  transacción envolvente que oculte las fugas. `TenantPerTransactionTest`
  comprueba que:
  - tras una petición, con éxito o con 500, la conexión queda sin plantel;
  - la petición del plantel B, en la misma conexión, no ve nada de A;
  - una petición que revierte un *savepoint* sigue con su plantel;
  - el dominio central no ve nada.
- **PgBouncer (`tests/Isolation/PgBouncerLeakTest`, solo en el job de CI con
  PgBouncer):** dos clientes por un pool de una conexión. Lo que fija uno
  dentro de su transacción no lo ve el otro. Con el ajuste por conexión
  antiguo, el test fallaría: se comprueba en rojo antes del cambio.
- **HTTP (feature):**
  - `HandoffBindingTest`: un navegador sin la cookie va al login central;
    no revela el motivo; otra sesión no se sustituye; el titular aún puede
    usar el enlace;
  - `InvitationConsolidationTest`: los tres estados y la invitación sigue
    válida; tras consolidar se acepta.
- **Rendimiento (`group=perf`):** `RlsOverheadTest` mide el p95 de una
  petición de plantel con y sin la transacción de petición, con 5 ms como
  máximo de diferencia (RLT-NF03). Los tests `perf` existentes siguen
  pasando.
- **Arquitectura:**
  - sin `set_config(..., false)` sobre la variable del plantel (búsqueda en
    `src/`);
  - `activate()` solo desde `ResolveTenant` (y `tests/`);
  - `RowLevelSecurity::applyLocal` solo desde `PostgresTenantActivator`;
  - `ResolveTenant` es el primer middleware de ruta con base de datos del
    grupo del plantel.
- **Regresión (RLT-NF04):** toda la suite, `rls`, `perf` y navegador en
  verde sin cambiar la lógica de los tests existentes.
  - Los helpers `tenant()`, `useTenant()` y `leaveTenant()` de
    `tests/Pest.php` siguen funcionando: los tests ya corren dentro de la
    transacción de `RefreshDatabase`.
  - Solo cambian los tests que esta spec modifica expresamente:
    `CacheHandoffTokensTest` y `HandoffTest` reciben la cookie del
    navegador en su preparación.

## Decisiones y alternativas

| Decisión | Alternativa descartada | Motivo |
|----------|------------------------|--------|
| Transacción por petición (ADR 0011) | Limpiar al terminar; envolver cada sentencia | Ver ADR 0011. |
| Confirmar las respuestas 4xx | Revertir todo lo que no sea 2xx/3xx | Un rechazo de dominio (422) puede haber registrado algo a propósito, p. ej. un rechazo en la bitácora. Lo que hizo el caso de uso ya se revirtió en su *savepoint*. |
| Cookie del dominio padre para ligar el traspaso | Compartir la sesión entre subdominios (`SESSION_DOMAIN=.APP_DOMAIN`) | Compartir la sesión rompería el aislamiento de la sesión de cada plantel (TEN, IDG-R03.9). La cookie solo lleva un secreto aleatorio, inútil sin un token emitido para él. |
| Quemar el token solo tras validar el navegador | Quemarlo siempre al primer uso | Si no, un tercero que abre el enlace deja al titular sin traspaso. Sigue siendo de un solo uso para el navegador correcto. |
| *Trigger* de transición sin privilegios | `WITH CHECK` más estricto, solo | `WITH CHECK` no ve el valor anterior: no distingue «responder» de «cambiar el origen». |
| Preparaciones emuladas en PDO detrás de un pool (`DB_EMULATE_PREPARES=true`, `max_prepared_statements = 0`) | PgBouncer 1.21+ con `max_prepared_statements` | El pool reutiliza una consulta preparada antes de un cambio de esquema y PostgreSQL responde «cached plan must not change result type»: en los tests, porque las migraciones pasan por el pool; en producción, tras cada despliegue con migraciones. Con las emuladas, los valores se siguen escapando y el servidor no guarda planes entre clientes. *Cambiada en el PR B (P-04, aprobada el 2026-10-27); antes era la alternativa descartada.* |

## Cambios en otras specs y módulos

- **SPEC-001:** TEN-R05.6 se precisa: el plantel se aplica por transacción.
  Nota en `design.md` de 001.
- **SPEC-004:**
  - IDG-NF03: el token se liga también al navegador que lo pidió;
  - IDG-R04.5: excepción de RLT-R06.
- **SPEC-009:** EST-R07 sin cambios de comportamiento. Nota de la política
  nueva en su `design.md`.
- **ADR 0003 y ADR 0006:** nota de que el ajuste es por transacción (ADR
  0011).
- **ADR 0008:** la tabla de políticas añade el `INSERT` con origen nulo y el
  *trigger*.
- **ADR 0010:** `TenantScopedReader` usa `within()`.
- **Roadmap:** se cierran los cuatro hallazgos de la revisión del
  2026-10-23.

## Riesgos y plan de rollback

| Riesgo | Mitigación |
|--------|------------|
| Algún middleware lee la base de datos antes de `ResolveTenant` en el grupo del plantel y deja de ver datos | Prioridad de middleware fijada y test de arquitectura; la suite feature completa lo detectaría. |
| Una sentencia que falla fuera de un *savepoint* aborta la petición entera | Los repositorios que capturan `QueryException` ya lo hacen dentro de `TransactionManager::run`. La suite, que ya corre dentro de una transacción, lo confirma. |
| Bloqueos más largos (calendario, `FOR UPDATE`) | Peticiones cortas; `RlsOverheadTest` y los umbrales `perf`. |
| Respuestas en streaming que lean la base de datos tras devolverse | Hoy no hay ninguna; regla en el ADR y un test que falla si una ruta del plantel devuelve un `StreamedResponse`. |
| Trabajos en cola encolados antes de confirmar | `after_commit => true`. |
| PgBouncer en CI añade minutos y piezas | Job aparte, solo `--group=rls` y `tests/Isolation`. |

**Rollback:**

- El cambio es de código, salvo una migración reversible: el *trigger* y la
  política. Revertir el despliegue vuelve al ajuste por conexión, y
  `migrate:rollback` restaura la política.
- La cookie del navegador es inocua sin el código.
- Los enlaces de traspaso emitidos en el minuto del despliegue dejan de
  valer, en cualquiera de los dos sentidos.
- No hay datos que migrar ni que restaurar.

## Notas de implementación

### PR A

- **Se conservan `RowLevelSecurity::setInstitution()` y
  `clearInstitution()`,** en lugar de renombrarlos a `applyLocal()`. Ahora
  usan `set_config(..., true)`, así que valen solo para su transacción. Unos
  30 tests existentes los usan en su preparación y, dentro de
  `RefreshDatabase`, se comportan igual (Q-03). Se añade `currentSetting()`.
  Una regla de arquitectura limita su uso al activador y a las migraciones.
- **`TenantActivator::forRequest()`,** además de `activate()` y `within()`.
  `ResolveTenant` es un adaptador migrado y no puede usar
  `Illuminate\Database` (regla de arquitectura). La transacción de la
  petición la abre y la cierra el activador:
  - `forRequest(contexto, $handle, $keep)` abre la transacción, activa el
    plantel y ejecuta `$handle`;
  - confirma si `$keep($respuesta)` es verdadero (estado menor que 500), y
    revierte si no o si hay una excepción.

  Una regla de arquitectura prohíbe llamar a mano a `activate()` y
  `deactivate()` en `src/`, `app/` y `database/`.
- **El *streaming* se comprueba en tiempo de ejecución:** `ResolveTenant`
  lanza una excepción si una petición de plantel responde con un
  `StreamedResponse`, que se leería ya fuera de la transacción.
- **El seeder `InstitutionSeeder` usa `within()`.** `db:seed` no corre en
  una transacción. Las migraciones de PostgreSQL sí, así que las de
  SPEC-004 que cambian de plantel no cambian.
- **`tests/Isolation` no usa `DatabaseTruncation`.** Su `CASCADE` llega a
  `audit_entries`, que es inmutable. `IsolationTestCase` borra lo que cada
  test creó (planteles, sus años, lapsos y membresías, y las cuentas
  nuevas) y revierte cualquier transacción que un test dejara abierta. La
  suite es un *testsuite* más de `phpunit.xml`, en el grupo `rls`.
- **Los seis cambios de plantel pasan a `within()`.**
  - `LinkGuardianAccountHandler` pierde la dependencia de `CurrentTenant`,
    así que su test de unidad cambia solo su construcción.
  - `AcceptInvitationHandler` y `JoinInstitutionHandler` pierden
    `TransactionManager`: `within()` ya abre la transacción.
  - `TenantScopedReader` mantiene su transacción de solo lectura, siempre
    revertida, y cambia de plantel con `within()` dentro de ella.
- **Cola:**
  - `EnsureNoTenantOnJobStart` revierte, y reporta como `TenantLeak`,
    cualquier transacción que un trabajo anterior dejara abierta. Es lo
    único que puede quedar, porque el plantel solo vive en la transacción.
  - `after_commit => true` en todas las conexiones de la cola, también en
    `sync`.
- **Coste:** `RlsOverheadTest` mide `forRequest()` frente a la misma
  consulta sin él, sobre confirmaciones reales (`tests/Isolation`, grupos
  `rls` y `perf`).

### PR B

- **Preparaciones emuladas detrás del pool** (cambio de la decisión de
  diseño sobre PgBouncer, aprobado el 2026-10-27, P-04). Con
  `max_prepared_statements`, PgBouncer reutilizaba entre clientes una
  consulta preparada antes de las migraciones de los tests, y PostgreSQL
  respondía «cached plan must not change result type». `config/database.php`
  activa `PDO::ATTR_EMULATE_PREPARES` con `DB_EMULATE_PREPARES=true`, que solo
  se pone detrás de un pool (`phpunit.pgbouncer.xml`); `pgbouncer.ini` lleva
  `max_prepared_statements = 0`. Sin la variable, nada cambia.
- **PgBouncer 1.22 de Alpine 3.20**, con `USER nobody`, en el perfil
  `pgbouncer` de `docker-compose.yml`. En CI se instala con `apt` en el job
  `rls-pgbouncer`, que corre `--group=rls`; `tests/Isolation` forma parte de
  ese grupo.
- **`PgBouncerLeakTest` se salta** salvo por el puerto 6432. Su segundo test
  deja en verde la prueba en rojo que pedía T-09: con el ajuste por
  conexión antiguo, el plantel sí llega al siguiente cliente del pool.

### PR C

- **Sin tests de unidad para `JoinInstitutionHandler` ni para los
  manejadores del traspaso** (T-11, T-12). IdentityAccess no tiene
  adaptadores en memoria para sus nueve dependencias. Los cubren
  `InvitationConsolidationTest` y `HandoffBindingTest`, contra la base de
  datos real: comprueban que se rechaza sin crear la membresía ni tocar la
  invitación, y que el enlace no se quema.
- **El puerto `BrowserBinding`** tiene `secret()`, `presented()` y
  `rotate()`. `CookieBrowserBinding` no guarda estado: lee la petición en
  curso y deja el secreto nuevo en sus atributos. Un enlace *scoped* habría
  arrastrado la primera petición entre las de un test.
- **El secreto se rota al cerrar sesión** (`LaravelAuthenticator::logOut`),
  no al iniciarla. Al iniciar sesión se crea si falta.
- **`HandoffTokens`** recibe el hash del secreto como parámetro opcional,
  vacío por omisión. Así `CacheHandoffTokensTest` no cambia. Los
  manejadores siempre pasan un hash real, y el token se compara con
  `hash_equals` antes de quemarse.
- **El rechazo lleva al login central con `?plantel=`** (RLT-R04.2). Por
  eso cambia la URL esperada en cuatro aserciones de `HandoffTest`. Es el
  único cambio de lógica en tests existentes, y lo exige este requisito.
- **En los tests, `Tests\TestCase`** guarda la cookie del navegador entre
  peticiones, como haría un navegador; `forgetBrowserCookies()` simula otro.
- **La pantalla de la invitación** recibe el estado de consolidación de la
  cuenta y, si está pendiente, enlaza a la consolidación. También muestra
  el error del botón «Aceptar la invitación», que antes no se veía.
- **`RowLevelSecurity::guardAddressedOrigin()`** y su inversa crean la
  política `INSERT` y el *trigger*. `enableAddressedByIdentification()` no
  los llama, para no cambiar la migración de SPEC-009: una tabla nueva de
  este tipo llama a los dos.
