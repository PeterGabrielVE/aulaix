---
spec: 000-fundaciones-hexagonales
estado: implementando
fecha: 2026-10-10
diseño: design.md
---

# Tareas — Fundaciones hexagonales

> Las rutas de los tests siguen la convención de `testing-best-practices`: el
> test replica la ruta de la clase que prueba. Los adaptadores que dependen de
> Laravel (reloj, transacciones, eventos) se prueban en `tests/Feature`, no en
> `tests/Unit`.

## Fase 1 — Estructura y reglas

- [x] **T-01** — Añadir `"AulaX\\": "src/"` al autoload de `composer.json`,
  crear `src/` y ejecutar `composer dump-autoload`. `src/` se añade también a
  `<source>` de `phpunit.xml`.
  - Criterios: FND-R01.1
  - Test: cualquier test de `src/`, p. ej. `tests/Unit/Shared/Domain/AggregateRootTest.php`

- [x] **T-02** — Suite `Architecture` en `phpunit.xml` y
  `tests/Architecture/LayersTest.php`, con helpers que descubren los módulos de `src/`.
  - Criterios: FND-R01.2, FND-R02.1 – FND-R02.6
  - Test: un test por regla. Verificado en rojo con una clase temporal que usaba
    `Illuminate`, `now()`, `Infrastructure` y no declaraba `strict_types`.
  - Las reglas entre módulos se saltan explícitamente mientras no hay otro
    módulo que comparar. FND-R02.6 se salta hasta que SPEC-001 migre el primer
    controlador.

## Fase 2 — Kernel compartido

- [x] **T-03** — Jerarquía de excepciones de dominio y trait `AggregateRoot`.
  - Criterios: FND-R03.1
  - Test: `tests/Unit/Shared/Domain/AggregateRootTest.php`, `tests/Unit/Shared/Domain/DomainExceptionTest.php`
- [x] **T-04** — Puerto `Clock` + `SystemClock`, que usa el reloj de Laravel (`Date::now()`) para que `travelTo()` funcione en los tests.
  - Criterios: FND-R03.2
  - Test: `tests/Feature/Shared/Infrastructure/SystemClockTest.php`
- [x] **T-05** — Puerto `TransactionManager` + `LaravelTransactionManager`.
  - Criterios: FND-R03.3
  - Test: `tests/Feature/Shared/Infrastructure/LaravelTransactionManagerTest.php`
- [x] **T-06** — Puerto `EventBus` + `LaravelEventBus` con `afterCommit`.
  - Criterios: FND-R03.4, FND-R03.5
  - Test: `tests/Feature/Shared/Infrastructure/LaravelEventBusTest.php`
- [x] **T-07** — `SharedServiceProvider` registrado en `bootstrap/providers.php`.
  - Criterios: FND-R04.1
  - Test: sin test propio; los tests de T-04 a T-06 resuelven cada puerto desde el contenedor.

## Fase 3 — Errores y specs

- [x] **T-08** — Mapeo de excepciones de dominio en `bootstrap/app.php` (`Exceptions::map`).
  - Criterios: FND-R05.1 – FND-R05.5
  - Test: `tests/Feature/Shared/DomainExceptionRenderingTest.php`
- [ ] **T-09** — `tests/Architecture/SpecsTest.php`. **Parcial.**
  - Criterios: FND-R06.1 – FND-R06.4
  - Hecho: existencia y estados válidos, IDs únicos, matriz que apunta a tests
    existentes (`test`, `it` y `arch`), trazabilidad completa en specs
    implementadas. Verificado en rojo con una referencia rota, un ID duplicado y
    un criterio sin trazar.
  - Hecho: test de IAM-R02.8 (bloqueo tras 5 intentos fallidos) en
    `tests/Feature/Auth/AuthenticationTest.php` y en la matriz de SPEC-002.
  - **Pendiente de decisión (P-04):** la regla de orden entre fases de
    FND-R06.1 («`tasks.md` exige `design.md` aprobado») haría fallar las specs
    001 – 003, que tienen sus tres documentos en borrador. Propuesta: sustituirla
    por «un documento no puede tener un estado más avanzado que el de la fase
    anterior». Hasta que se decida, queda como `todo` en el test.

## Fase 4 — Piloto

- [x] **T-10** — Mover `App\Contracts\AIServiceClient` →
  `AulaX\Ai\Application\AIServiceClient` y `App\Services\AI\HttpAIServiceClient`
  → `AulaX\Ai\Infrastructure\HttpAIServiceClient` (con `git mv`); binding en
  `AiServiceProvider`; actualizados `CheckAiServiceHealth`, `config/services.php`,
  `README.md`, `.env.example` y los `use` del test.
  - Criterios: FND-R07.1, FND-R07.2
  - Test: `tests/Feature/AIServiceClientTest.php` (sin cambios de lógica, Q-03);
    `php artisan ai:health` responde contra el servicio real.

## Verificación final

- [x] `php artisan test --compact` en verde: 200 tests, 1 `todo`, 4 saltados;
  dividido como en CI: 184 sin `rls` + 16 de `rls`.
- [x] Suite `Architecture` en verde.
- [x] Pint sin cambios pendientes.
- [x] Matriz de trazabilidad completa en `requirements.md`.
- [ ] `estado: implementada` en los tres archivos (tras decidir T-09).
