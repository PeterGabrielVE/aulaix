---
spec: 000-fundaciones-hexagonales
estado: borrador
fecha: 2026-10-10
requisitos: requirements.md
---

# Diseño — Fundaciones hexagonales

## Resumen

Se añade el namespace `AulaX\` → `src/` y el kernel `Shared` con los puertos
`Clock`, `TransactionManager` y `EventBus` y sus adaptadores Laravel. Las
reglas de la constitución se codifican como tests de arquitectura de Pest, y
las excepciones de dominio se traducen a HTTP en `bootstrap/app.php`. El
cliente de IA se mueve a `src/Ai` como piloto.

## Estructura

```
src/
├── Shared/
│   ├── Domain/
│   │   ├── DomainException.php          abstracta; base de todas
│   │   ├── BusinessRuleViolation.php    → 422
│   │   ├── AuthorizationDenied.php      → 403
│   │   ├── NotFound.php                 → 404
│   │   ├── Conflict.php                 → 409
│   │   ├── DomainEvent.php              interfaz: occurredAt()
│   │   ├── AggregateRoot.php            trait: recordThat() / releaseEvents()
│   │   └── Clock.php                    puerto: now(): DateTimeImmutable
│   ├── Application/
│   │   ├── TransactionManager.php       puerto: run(callable): mixed
│   │   └── EventBus.php                 puerto: publish(DomainEvent ...$events): void
│   └── Infrastructure/
│       ├── SystemClock.php
│       ├── LaravelTransactionManager.php     DB::transaction
│       ├── LaravelEventBus.php               Event::dispatch tras commit
│       └── SharedServiceProvider.php
└── Ai/
    ├── Application/
    │   └── AIServiceClient.php          puerto (movido desde app/Contracts)
    └── Infrastructure/
        ├── HttpAIServiceClient.php      adaptador (movido desde app/Services/AI)
        └── AiServiceProvider.php
```

`composer.json`:

```json
"autoload": { "psr-4": { "App\\": "app/", "AulaX\\": "src/", ... } }
```

`bootstrap/providers.php` registra `SharedServiceProvider` y un provider por
módulo.

## Kernel compartido

```php
// src/Shared/Domain/DomainException.php
abstract class DomainException extends \DomainException
{
    /** Campo del formulario al que se asocia el error (422), si aplica. */
    public function field(): ?string { return null; }
}

// src/Shared/Domain/AggregateRoot.php
trait AggregateRoot
{
    /** @var list<DomainEvent> */
    private array $recordedEvents = [];

    protected function recordThat(DomainEvent $event): void { $this->recordedEvents[] = $event; }

    /** @return list<DomainEvent> */
    public function releaseEvents(): array
    {
        [$events, $this->recordedEvents] = [$this->recordedEvents, []];
        return $events;
    }
}
```

Flujo de un caso de uso de escritura:

```
Handler ── TransactionManager::run(fn) ──► agregado->acción() ──► repository->save()
   └── EventBus::publish(...agregado->releaseEvents())   (el adaptador espera al commit)
```

`LaravelEventBus` despacha con `DB::afterCommit()`, así una transacción
revertida no publica eventos (FND-R03.5).

## Tests de arquitectura

`tests/Architecture/LayersTest.php` (nueva suite `Architecture` en
`phpunit.xml`):

```php
arch('domain is framework-free')
    ->expect('AulaX')->classes()            // filtrado a */Domain/* en el helper
    ->not->toUse(['Illuminate', 'Spatie', 'Inertia', 'Carbon', 'App'])
    ->not->toUse(['now', 'config', 'app', '__', 'request', 'auth']);

arch('application does not use the framework')
    ->expect(applicationNamespaces())
    ->not->toUse(['Illuminate', 'Spatie', 'Inertia', 'App']);

arch('core never reaches infrastructure')
    ->expect([...domainNamespaces(), ...applicationNamespaces()])
    ->not->toUse(infrastructureNamespaces());

arch('modules are isolated')            // un caso por módulo vía dataset
    ->expect("AulaX\\{$module}")
    ->not->toUse(otherModulesInternals($module));

arch('src is strict')->expect('AulaX')->toUseStrictTypes();
```

Los helpers (`domainNamespaces()` …) descubren los módulos listando `src/`,
para que un módulo nuevo quede cubierto sin tocar el test.

FND-R02.6 se activa por módulo con una lista explícita
(`MIGRATED_CONTROLLERS`) que crece en cada spec de refactor.

## Validación de specs

`tests/Architecture/SpecsTest.php` lee `specs/NNN-*/`:

1. Comprueba que existe `requirements.md`, que todo archivo presente tiene un
   `estado` válido, y que cada fase solo existe si la anterior está
   `aprobada` (o en un estado posterior).
2. Extrae los IDs de criterios (`/\*\*([A-Z]{3}-R\d{2}\.\d+)\*\*/`) y exige
   que sean únicos.
3. Por cada fila de la matriz, comprueba que el archivo existe y que define
   un `test(...)`/`it(...)` con ese nombre. Compara el nombre ya desescapado y
   acepta comillas simples o dobles: en el código aparecen tanto
   `'another tenant\'s users'` como `"another role's home screen"`.
4. Si `estado: implementada`, exige que todo criterio aparezca en la matriz.

No añade dependencias ni un job nuevo: corre dentro de la suite.

## Mapeo de errores

En `bootstrap/app.php`, `withExceptions`:

| Excepción | HTTP | Respuesta |
|-----------|------|-----------|
| `BusinessRuleViolation` | 422 | `ValidationException::withMessages([$e->field() ?? 'general' => $e->getMessage()])` (Inertia muestra el error en el formulario) |
| `AuthorizationDenied` | 403 | `abort(403)` |
| `NotFound` | 404 | `abort(404)` |
| `Conflict` | 409 | `abort(409, $e->getMessage())` |
| Otra | 500 | Página genérica (`APP_DEBUG=false` fuera de local) |

## Patrones aplicados

| Patrón | Fuerza que resuelve |
|--------|---------------------|
| Puertos y adaptadores | Aislar el núcleo de Laravel (A-02, A-03). |
| Domain Event + publicación tras commit | Efectos secundarios (correos) solo si la escritura se confirma. |
| Trait `AggregateRoot` | Registrar eventos sin una clase base que limite la herencia. |
| Jerarquía de excepciones de dominio | Un único punto de traducción a HTTP (C-06). |

## Modelo de amenazas (STRIDE)

| Amenaza | Vector | Mitigación | Verificado por |
|---------|--------|------------|----------------|
| Information disclosure | Excepción no controlada muestra traza o SQL | Solo las excepciones de dominio exponen su mensaje; el resto es genérico (FND-R05.5) | Test feature de 500 con `APP_DEBUG=false` |
| Tampering | Evento publicado tras un rollback (p. ej. correo de invitación de un usuario que no se creó) | `afterCommit` en `LaravelEventBus` | FND-R03.5 |
| Elevation of privilege | Código de dominio que salta la RLS con SQL crudo | El dominio no puede usar `Illuminate` (FND-R02.1); la RLS sigue activa | Arch tests + `--group=rls` |

## Estrategia de pruebas

- **Arquitectura:** `tests/Architecture/LayersTest.php` y `SpecsTest.php`.
- **Unit:** `AggregateRoot` (registrar y liberar eventos), excepciones
  (`field()`).
- **Integración:** `LaravelTransactionManager` revierte en una excepción;
  `LaravelEventBus` no publica tras un rollback y sí tras un commit.
- **Feature:** mapeo de errores con una ruta de prueba registrada en el test;
  `AIServiceClientTest` existente, sin cambios de lógica.

## Decisiones y alternativas

| Decisión | Alternativa descartada | Motivo |
|----------|------------------------|--------|
| Tests `arch()` de Pest | deptrac | Ya está instalado Pest; deptrac sería una dependencia nueva. |
| Handlers invocados directamente | Bus de comandos | YAGNI: sin middleware transversal todavía. |
| Eventos con `Event::dispatch` de Laravel | Bus propio | Reutiliza listeners y colas de Laravel desde el adaptador. |
| Validación de specs como test Pest | Script en Node + job de CI | Sin herramientas nuevas; falla en local igual que en CI. |

## Riesgos y plan de rollback

- **Riesgo bajo:** solo añade código y mueve dos clases del cliente de IA.
- **Rollback:** revertir el PR; ningún dato ni migración implicados.
