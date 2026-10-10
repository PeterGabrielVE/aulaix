---
spec: 000-fundaciones-hexagonales
prefijo: FND
estado: aprobada
fecha: 2026-10-10
---

# Requisitos — Fundaciones hexagonales

## Contexto

Antes de mover cualquier módulo a arquitectura hexagonal (ADR 0001) hacen
falta los cimientos compartidos: dónde vive el código, qué reglas de
dependencia se verifican automáticamente, el kernel común y cómo se traducen
los errores de dominio a HTTP. Sin esto, cada módulo inventaría su propia
variante y las reglas de la constitución (A-01 a A-09) quedarían solo en el
papel.

Es una spec **técnica**: sus «usuarios» son quienes desarrollan, y CI.

## Alcance

- **Incluye:** namespace `src/`, kernel `Shared`, tests de arquitectura,
  registro de módulos, mapeo de excepciones, validación de specs en CI y
  migración piloto del cliente de IA (ya es un puerto con adaptador).
- **Excluye:** el refactor de Tenancy (SPEC-001), Identidad y acceso
  (SPEC-002) y Catálogos (SPEC-003).

## Glosario

| Término | Significado |
|---------|-------------|
| Módulo | Contexto acotado en `src/<Módulo>/` con capas Domain, Application e Infrastructure. |
| Puerto | Interfaz que el núcleo define y la infraestructura implementa. |
| Adaptador | Implementación concreta de un puerto (Eloquent, HTTP, correo…). |
| Test de caracterización | Test existente que fija el comportamiento actual durante un refactor. |

## Requisitos

### FND-R01 — Ubicación del código de negocio

**Historia:** Como desarrollador, quiero un lugar único y predecible para el
código de cada módulo, para saber dónde va cada cosa sin preguntar.

- **FND-R01.1** — El sistema deberá cargar automáticamente (PSR-4) las clases
  del namespace `AulaX\` desde `src/`.
- **FND-R01.2** — El sistema deberá organizar cada módulo en
  `src/<Módulo>/{Domain,Application,Infrastructure}`.

### FND-R02 — Reglas de dependencia verificadas

**Historia:** Como responsable técnico, quiero que CI rechace código que rompa
las capas, para que la arquitectura no se degrade con el tiempo.

- **FND-R02.1** — Si una clase de una capa `Domain` usa `Illuminate`,
  `Spatie`, `Inertia`, `Carbon` o `App`, entonces la suite de tests deberá
  fallar.
- **FND-R02.2** — Si una clase de una capa `Application` usa `Illuminate`,
  `Spatie`, `Inertia` o `App`, entonces la suite de tests deberá fallar.
- **FND-R02.3** — Si una clase de `Domain` o `Application` usa una clase de
  `Infrastructure`, entonces la suite de tests deberá fallar.
- **FND-R02.4** — Si un módulo usa el `Domain` o la `Infrastructure` de otro
  módulo distinto de `Shared`, entonces la suite de tests deberá fallar.
- **FND-R02.5** — Si un archivo de `src/` no declara `strict_types=1`,
  entonces la suite de tests deberá fallar.
- **FND-R02.6** — Si un controlador de `app/Http/Controllers` usa un modelo
  Eloquent directamente, entonces la suite de tests deberá fallar. Esta regla
  se activa por módulo a medida que se migra.

### FND-R03 — Kernel compartido

**Historia:** Como desarrollador, quiero piezas comunes probadas para
construir módulos sin repetir infraestructura.

- **FND-R03.1** — El sistema deberá ofrecer una excepción base de dominio de
  la que deriven todas las excepciones de negocio.
- **FND-R03.2** — El sistema deberá ofrecer un puerto `Clock`, para que el
  dominio obtenga la hora actual sin depender del framework.
- **FND-R03.3** — El sistema deberá ofrecer un puerto `TransactionManager`
  cuya implementación ejecute un bloque de forma atómica y revierta todos
  sus cambios si el bloque lanza una excepción.
- **FND-R03.4** — El sistema deberá ofrecer un puerto `EventBus`, y su
  implementación deberá publicar los eventos de dominio después de confirmar
  la transacción en curso, no antes.
- **FND-R03.5** — Cuando una transacción se revierte, el sistema no deberá
  publicar los eventos de dominio registrados dentro de ella.

### FND-R04 — Registro de módulos

- **FND-R04.1** — El sistema deberá registrar los enlaces puerto → adaptador
  de cada módulo en un `ServiceProvider` propio de ese módulo.

### FND-R05 — Errores de dominio en HTTP

**Historia:** Como usuario, quiero mensajes de error claros y coherentes,
sin detalles internos.

- **FND-R05.1** — Cuando un caso de uso lanza una excepción de dominio por
  una regla de negocio incumplida, el sistema deberá tratarla como un error de
  validación del campo que indique la excepción (o de `general` si no indica
  ninguno): 422 en peticiones JSON, y redirección al formulario con el error
  en las demás.
- **FND-R05.2** — Cuando un caso de uso lanza una excepción de autorización
  de dominio, el sistema deberá responder 403.
- **FND-R05.3** — Cuando un caso de uso lanza una excepción de «no
  encontrado», el sistema deberá responder 404.
- **FND-R05.4** — Cuando un caso de uso lanza una excepción de conflicto de
  estado, el sistema deberá responder 409.
- **FND-R05.5** — Si se produce una excepción que no es de dominio, entonces
  el sistema no deberá mostrar su mensaje ni su traza al usuario fuera del
  entorno local.

### FND-R06 — Specs verificadas en CI

**Historia:** Como responsable técnico, quiero que la trazabilidad
spec → test no se rompa en silencio.

- **FND-R06.1** — Si una carpeta de `specs/NNN-*` no contiene
  `requirements.md`, o si alguno de sus archivos (`requirements.md`,
  `design.md`, `tasks.md`) no tiene un frontmatter `estado` válido, entonces
  la suite de tests deberá fallar. `design.md` exige que `requirements.md`
  esté `aprobada`, y `tasks.md` exige lo mismo de `design.md`.
- **FND-R06.2** — Si dos criterios de aceptación comparten ID, entonces la
  suite de tests deberá fallar.
- **FND-R06.3** — Si la matriz de trazabilidad referencia un archivo de test
  o un nombre de test que no existe, entonces la suite de tests deberá fallar.
- **FND-R06.4** — Mientras una spec esté en estado `implementada`, si alguno
  de sus criterios no aparece en la matriz de trazabilidad, entonces la suite
  de tests deberá fallar.

### FND-R07 — Piloto: integración con el servicio de IA

**Historia:** Como desarrollador, quiero validar la estructura con un caso
real y sin riesgo antes de migrar los módulos con reglas.

- **FND-R07.1** — Cuando se consulta la salud del servicio de IA y este
  responde con éxito, el sistema deberá devolver su respuesta decodificada.
- **FND-R07.2** — Si el servicio de IA no responde, entonces el sistema
  deberá devolver «sin respuesta» y registrar una advertencia, sin propagar
  el error.

## Requisitos no funcionales

- **FND-NF01** — Los tests de arquitectura y de specs se ejecutan en menos de
  5 segundos en total.
- **FND-NF02** — Al terminar cada tarea, la suite completa sigue en verde
  (Q-03).

## Fuera de alcance y preguntas abiertas

- Bus de comandos: los controladores invocan los handlers directamente por
  inyección. Se añadirá un bus si aparecen necesidades transversales
  (auditoría, reintentos).
- Outbox transaccional para eventos: se pospone hasta que haya colas reales
  (`QUEUE_CONNECTION=sync` hoy).

## Matriz de trazabilidad

| Criterio | Test(s) |
|----------|---------|
| FND-R01.1 | `tests/Unit/Shared/Domain/AggregateRootTest.php` › «releases the recorded events in the order they happened» |
| FND-R01.2 | `tests/Architecture/LayersTest.php` › «a domain layer does not depend on the framework» |
| FND-R02.1 | `tests/Architecture/LayersTest.php` › «a domain layer does not depend on the framework» |
| FND-R02.2 | `tests/Architecture/LayersTest.php` › «an application layer does not depend on the framework» |
| FND-R02.3 | `tests/Architecture/LayersTest.php` › «the core never reaches into infrastructure» |
| FND-R02.4 | `tests/Architecture/LayersTest.php` › «a module only uses the domain and infrastructure of Shared»; `tests/Architecture/LayersTest.php` › «Shared depends on no other module» |
| FND-R02.5 | `tests/Architecture/LayersTest.php` › «every file in src declares strict types» |
| FND-R02.6 | `tests/Architecture/LayersTest.php` › «a migrated controller does not use Eloquent or infrastructure directly» (sin controladores migrados todavía; se activa con SPEC-001) |
| FND-R03.1 | `tests/Unit/Shared/Domain/DomainExceptionTest.php` › «a business rule violation names the form field it belongs to»; `tests/Unit/Shared/Domain/DomainExceptionTest.php` › «a business rule violation without a field belongs to no form field» |
| FND-R03.2 | `tests/Feature/Shared/Infrastructure/SystemClockTest.php` › «the clock follows the application time, so tests can move it» |
| FND-R03.3 | `tests/Feature/Shared/Infrastructure/LaravelTransactionManagerTest.php` › «keeps the changes and returns the result when the operation succeeds»; `tests/Feature/Shared/Infrastructure/LaravelTransactionManagerTest.php` › «undoes every change and rethrows when the operation fails» |
| FND-R03.4 | `tests/Feature/Shared/Infrastructure/LaravelEventBusTest.php` › «publishes the events of a transaction only once it commits»; `tests/Feature/Shared/Infrastructure/LaravelEventBusTest.php` › «publishes an event right away when no transaction is open» |
| FND-R03.5 | `tests/Feature/Shared/Infrastructure/LaravelEventBusTest.php` › «does not publish the events of a transaction that rolls back» |
| FND-R04.1 | `tests/Feature/Shared/Infrastructure/LaravelEventBusTest.php` › «publishes an event right away when no transaction is open»; `tests/Feature/AIServiceClientTest.php` › «health returns the decoded payload when the AI service responds» |
| FND-R05.1 | `tests/Feature/Shared/DomainExceptionRenderingTest.php` › «shows a broken business rule as a validation error on its form field»; `tests/Feature/Shared/DomainExceptionRenderingTest.php` › «returns 422 with the field error for a broken business rule in JSON»; `tests/Feature/Shared/DomainExceptionRenderingTest.php` › «files a broken business rule without a field under the general error» |
| FND-R05.2 | `tests/Feature/Shared/DomainExceptionRenderingTest.php` › «returns 403 when the domain denies the action» |
| FND-R05.3 | `tests/Feature/Shared/DomainExceptionRenderingTest.php` › «returns 404 when the domain does not find the resource» |
| FND-R05.4 | `tests/Feature/Shared/DomainExceptionRenderingTest.php` › «returns 409 with its message when the action conflicts with the current state» |
| FND-R05.5 | `tests/Feature/Shared/DomainExceptionRenderingTest.php` › «does not reveal the message of an unexpected error outside debug mode» |
| FND-R06.1 | `tests/Architecture/SpecsTest.php` › «every spec has requirements and valid states in its documents» (la regla de orden entre fases está pendiente, ver `tasks.md` T-09) |
| FND-R06.2 | `tests/Architecture/SpecsTest.php` › «criterion IDs are unique across all specs» |
| FND-R06.3 | `tests/Architecture/SpecsTest.php` › «the traceability matrix only points at tests that exist» |
| FND-R06.4 | `tests/Architecture/SpecsTest.php` › «an implemented spec traces every acceptance criterion to a test» |
| FND-R07.1 | `tests/Feature/AIServiceClientTest.php` › «health returns the decoded payload when the AI service responds» |
| FND-R07.2 | `tests/Feature/AIServiceClientTest.php` › «health returns null when the AI service is unreachable» |
