---
spec: 000-fundaciones-hexagonales
estado: borrador
fecha: 2026-10-10
diseño: design.md
---

# Tareas — Fundaciones hexagonales

## Fase 1 — Estructura y reglas

- [ ] **T-01** — Añadir `"AulaX\\": "src/"` al autoload de `composer.json`,
  crear `src/` y ejecutar `composer dump-autoload`.
  - Criterios: FND-R01.1
  - Test: `tests/Architecture/LayersTest.php` › «src is strict» (pasa sin clases)
  - Hecho cuando: una clase de prueba en `src/Shared` se resuelve con el autoloader.

- [ ] **T-02** — Crear la suite `Architecture` en `phpunit.xml` y
  `tests/Architecture/LayersTest.php` con los helpers que descubren módulos.
  - Criterios: FND-R01.2, FND-R02.1 – FND-R02.5
  - Test: un test por regla; se comprueba en rojo añadiendo temporalmente una
    clase que la incumple.
  - Hecho cuando: cada regla falla con su violación y pasa sin ella.

## Fase 2 — Kernel compartido

- [ ] **T-03** — Jerarquía de excepciones de dominio y trait `AggregateRoot`.
  - Criterios: FND-R03.1
  - Test: `tests/Unit/Shared/AggregateRootTest.php`
- [ ] **T-04** — Puerto `Clock` + `SystemClock`.
  - Criterios: FND-R03.2
  - Test: `tests/Unit/Shared/SystemClockTest.php`
- [ ] **T-05** — Puerto `TransactionManager` + `LaravelTransactionManager`.
  - Criterios: FND-R03.3
  - Test: `tests/Feature/Shared/TransactionManagerTest.php` › revierte ante una excepción
- [ ] **T-06** — Puerto `EventBus` + `LaravelEventBus` con `afterCommit`.
  - Criterios: FND-R03.4, FND-R03.5
  - Test: `tests/Feature/Shared/EventBusTest.php` › publica tras commit / no publica tras rollback
- [ ] **T-07** — `SharedServiceProvider` registrado en `bootstrap/providers.php`.
  - Criterios: FND-R04.1
  - Test: resolver cada puerto del contenedor devuelve su adaptador.

## Fase 3 — Errores y specs

- [ ] **T-08** — Mapeo de excepciones de dominio en `bootstrap/app.php`.
  - Criterios: FND-R05.1 – FND-R05.5
  - Test: `tests/Feature/Shared/DomainExceptionRenderingTest.php` (una ruta de
    prueba por tipo de excepción)
- [ ] **T-09** — `tests/Architecture/SpecsTest.php`.
  - Criterios: FND-R06.1 – FND-R06.4
  - Test: el propio test; se comprueba en rojo con una matriz que apunta a un
    test inexistente.
  - Antes de activarlo: añadir el único criterio implementado sin test,
    IAM-R02.8 (bloqueo tras 5 intentos fallidos), en
    `tests/Feature/Auth/AuthenticationTest.php` y en la matriz de SPEC-002.

## Fase 4 — Piloto

- [ ] **T-10** — Mover `App\Contracts\AIServiceClient` →
  `AulaX\Ai\Application\AIServiceClient` y `App\Services\AI\HttpAIServiceClient`
  → `AulaX\Ai\Infrastructure\HttpAIServiceClient`; binding en
  `AiServiceProvider`; actualizar `CheckAiServiceHealth` y los `use` del test.
  - Criterios: FND-R07.1, FND-R07.2
  - Test: `tests/Feature/AIServiceClientTest.php` (sin cambios de lógica, Q-03)

## Verificación final

- [ ] `php artisan test --compact` en verde (incluido `--group=rls`).
- [ ] Suite `Architecture` en verde.
- [ ] `vendor/bin/pint --dirty` sin cambios.
- [ ] Matriz de trazabilidad completa en `requirements.md`.
- [ ] `estado: implementada` en los tres archivos.
