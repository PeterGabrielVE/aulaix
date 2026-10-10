---
spec: 001-tenancy
estado: borrador
fecha: 2026-10-10
diseño: design.md
depende-de: 000-fundaciones-hexagonales (implementada)
---

# Tareas — Tenancy (refactor hexagonal)

Regla transversal: al cerrar **cada** tarea, `php artisan test --compact`
(incluido `--group=rls`) sigue en verde.

## Fase 1 — Dominio

- [ ] **T-01** — `AulaX\Shared\Domain\InstitutionId`.
  - Test: `tests/Unit/Shared/InstitutionIdTest.php` › rechaza ≤ 0
- [ ] **T-02** — `Subdomain` + `InvalidSubdomain`.
  - Criterios: TEN-R02.1 – TEN-R02.3
  - Test: `tests/Unit/Tenancy/SubdomainTest.php` (datasets válidos, inválidos y reservados)
- [ ] **T-03** — `InstitutionStatus` (mover enum), `InstitutionProfile`, `Institution`.
  - Criterios: TEN-R01.1, TEN-R01.4
  - Test: `tests/Unit/Tenancy/InstitutionTest.php`
- [ ] **T-04** — Puerto `InstitutionRepository`.

## Fase 2 — Infraestructura

- [ ] **T-05** — Mover `App\Models\Institution` → `InstitutionModel` con
  `#[UseFactory]`; el mutator de `subdomain` usa `Subdomain::normalize()`.
  Actualizar `use` en factories, seeders, `User` y tests.
  - Criterios: TEN-R01.*, TEN-R02.1
  - Test: `tests/Feature/InstitutionTest.php`, `SubdomainResolutionTest.php` (sin cambios de lógica)
- [ ] **T-06** — `InstitutionMapper` + `EloquentInstitutionRepository`.
  - Criterios: TEN-R03.1, TEN-R03.3
  - Test: `tests/Feature/Tenancy/EloquentInstitutionRepositoryTest.php`
- [ ] **T-07** — Mover `RowLevelSecurity` y `BelongsToInstitution` a
  `Tenancy\Infrastructure\Persistence`; la macro `belongsToInstitution` pasa a
  `TenancyServiceProvider`. Actualizar los `use` de migraciones y tests.
  - Criterios: TEN-R05.*, TEN-NF01 – TEN-NF03
  - Test: `php artisan test --group=rls` + `migrate:fresh` limpio

## Fase 3 — Aplicación

- [ ] **T-08** — Puerto `CurrentTenant` + `InMemoryCurrentTenant`; sustituir
  `App\Support\CurrentTenant` en todos los consumidores.
  - Test: `tests/Unit/Tenancy/InMemoryCurrentTenantTest.php`
- [ ] **T-09** — `TenantActivator` + `TenantActivationHook` +
  `PostgresTenantActivator`; hook temporal `SpatiePermissionTeamHook` en
  `app/Providers`.
  - Criterios: TEN-R05.6
  - Test: `tests/Feature/Tenancy/TenantActivatorTest.php` (fija y limpia la RLS, ejecuta hooks); `RolePermissionTest.php`
- [ ] **T-10** — `ResolveTenantHandler` + `InstitutionNotFound`.
  - Criterios: TEN-R03.1 – TEN-R03.4
  - Test: `tests/Unit/Tenancy/ResolveTenantHandlerTest.php` (repositorio en memoria)
- [ ] **T-11** — `SearchInstitutionsHandler` + `EloquentInstitutionDirectory`.
  - Criterios: TEN-R04.3, TEN-R04.4
  - Test: `tests/Unit/Tenancy/SearchInstitutionsHandlerTest.php`; `TenantResolutionTest.php`

## Fase 4 — Adaptadores de entrada

- [ ] **T-12** — Reescribir `ResolveTenant` sobre `ResolveTenantHandler` +
  `TenantActivator`.
  - Criterios: TEN-R03.*, TEN-R04.1, TEN-R04.2
  - Test: `TenantResolutionTest.php`, `SubdomainResolutionTest.php`, `AuthenticationTest.php`
- [ ] **T-13** — `InstitutionController::search` y `HandleInertiaRequests`
  sobre los puertos.
  - Test: `TenantResolutionTest.php` › «the institution search endpoint only returns active institutions»
- [ ] **T-14** — Borrar las clases antiguas de `app/` y añadir `ResolveTenant`
  e `InstitutionController` a `MIGRATED_CONTROLLERS`.
  - Test: suite `Architecture`

## Fase 5 — Hallazgos (solo si se aprueban)

- [ ] **T-15** — TEN-H01: `throttle` en `/api/institutions/search` + criterio
  nuevo en `requirements.md` + test.
- [ ] **T-16** — TEN-H02: escapar comodines `LIKE` en `EloquentInstitutionDirectory` + test.

## Verificación final

- [ ] `php artisan test --compact` en verde (incluido `--group=rls`).
- [ ] Suite `Architecture` en verde; ninguna referencia a `App\Support\*`,
  `App\Concerns\*` ni `App\Models\Institution`.
- [ ] `vendor/bin/pint --dirty` sin cambios.
- [ ] `/security-review` (toca tenancy, S-12).
- [ ] `estado: implementada` en `design.md` y `tasks.md`.
