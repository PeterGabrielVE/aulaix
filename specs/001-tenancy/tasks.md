---
spec: 001-tenancy
estado: implementada
versión: 2
fecha: 2026-10-11
diseño: design.md
depende-de: 000-fundaciones-hexagonales (implementada), 003-catalogos (implementada)
---

# Tareas — Tenancy (refactor hexagonal)

Regla transversal: al cerrar **cada** tarea, `php artisan test --compact`
(incluido `--group=rls` y la suite `Architecture`) sigue en verde. Los
movimientos de archivos se hacen con `git mv`.

## Fase 1 — Dominio

- [x] **T-01** — `AulaX\Shared\Domain\InstitutionId`.
  - Test: `tests/Unit/Shared/Domain/InstitutionIdTest.php` › rechaza ≤ 0
- [x] **T-02** — `Subdomain` + `InvalidSubdomain`.
  - Criterios: TEN-R02.1 – TEN-R02.3
  - Test: `tests/Unit/Tenancy/Domain/SubdomainTest.php` (los mismos datasets
    válidos, inválidos y reservados que `SubdomainResolutionTest`)
- [x] **T-03** — Mover `InstitutionStatus` a `Tenancy\Domain`; crear
  `InstitutionProfile` e `Institution`.
  - Criterios: TEN-R01.1, TEN-R01.4
  - Test: `tests/Unit/Tenancy/Domain/InstitutionTest.php`
- [x] **T-04** — Puerto `InstitutionRepository`.

## Fase 2 — Infraestructura

- [x] **T-05** — Mover `App\Models\Institution` → `InstitutionModel`
  (`$table`, `#[UseFactory]`); el mutator de `subdomain` usa
  `Subdomain::normalize()`; **se elimina `users()`** (sin consumidores).
  Actualizar los `use` de factories, seeders, `User`, la migración
  `2026_10_09_215951_…` y los tests (alias `InstitutionModel as Institution`).
  - Criterios: TEN-R01.*, TEN-R02.1
  - Test: `InstitutionTest.php`, `SubdomainResolutionTest.php` (sin cambios de lógica)
- [x] **T-06** — `InstitutionMapper` + `EloquentInstitutionRepository`.
  - Criterios: TEN-R03.1, TEN-R03.3
  - Test: `tests/Feature/Tenancy/Infrastructure/Persistence/EloquentInstitutionRepositoryTest.php`
- [x] **T-07** — Mover `RowLevelSecurity` y `BelongsToInstitution` a
  `Tenancy\Infrastructure\Persistence`; la macro `belongsToInstitution` pasa a
  `TenancyServiceProvider`. Actualizar los `use` de migraciones, seeders y tests.
  - Criterios: TEN-R05.*, TEN-NF01 – TEN-NF03
  - Test: `php artisan test --group=rls` + `migrate:fresh --seed` limpio
- [x] **T-08** — Excepción explícita en `LayersTest` para
  `InstitutionModel → ParishModel` con `ignoring()`.
  - Test: suite `Architecture`; verificada en rojo sin la excepción

## Fase 3 — Aplicación

- [x] **T-09** — Puerto `CurrentTenant` (solo lectura: `context()`,
  `institutionId()`) + `TenantContext` + `InMemoryCurrentTenant`. Sustituir
  `App\Support\CurrentTenant` en sus consumidores (`UserRequest`,
  `LoginRequest`, `HandleInertiaRequests`, `BelongsToInstitution`).
  - Test: `tests/Unit/Tenancy/Infrastructure/InMemoryCurrentTenantTest.php`
- [x] **T-10** — `TenantActivator` + `TenantActivationHook` +
  `PostgresTenantActivator`; hook temporal `SpatiePermissionTeamHook` en
  `app/Providers` (se moverá a IdentityAccess con SPEC-004). El helper
  `tenant()` de `tests/Pest.php` pasa a usar el activador.
  - Criterios: TEN-R05.6
  - Test: `tests/Feature/Tenancy/Infrastructure/PostgresTenantActivatorTest.php`
    (fija y limpia la RLS, ejecuta los hooks); `RolePermissionTest.php`
- [x] **T-11** — `ResolveTenantHandler` + `InstitutionNotFound`.
  - Criterios: TEN-R03.1 – TEN-R03.4
  - Test: `tests/Unit/Tenancy/Application/ResolveTenantHandlerTest.php` (repositorio en memoria)
- [x] **T-12** — `SearchInstitutionsHandler` + `EloquentInstitutionDirectory`.
  - Criterios: TEN-R04.3, TEN-R04.4
  - Test: `tests/Unit/Tenancy/Application/SearchInstitutionsHandlerTest.php`; `TenantResolutionTest.php`

## Fase 4 — Adaptadores de entrada

- [x] **T-13** — Reescribir `ResolveTenant` sobre `ResolveTenantHandler` +
  `TenantActivator`.
  - Criterios: TEN-R03.*, TEN-R04.1, TEN-R04.2
  - Test: `TenantResolutionTest.php`, `SubdomainResolutionTest.php`, `AuthenticationTest.php`
- [x] **T-14** — `InstitutionController::search` sobre `SearchInstitutionsHandler`.
  - Test: `TenantResolutionTest.php` › «the institution search endpoint only returns active institutions»
- [x] **T-15** — Borrar las clases antiguas de `app/` y añadir `ResolveTenant`
  e `InstitutionController` a `MIGRATED_CONTROLLERS`.
  - Test: suite `Architecture`

## Fase 5 — Hallazgos (aprobados el 2026-10-11)

- [x] **T-16** — TEN-H01 → TEN-R04.5: `throttle:30,1` en `/api/institutions/search`.
  - Test: `TenantResolutionTest.php` › «the institution search is rate limited per client»
- [x] **T-17** — TEN-H02 → TEN-R04.6: escapar ``, `%` y `_` en `EloquentInstitutionDirectory`.
  - Test: `TenantResolutionTest.php` › «the institution search treats % and _ literally»

## Verificación final

- [x] `php artisan test --compact` en verde: 304 tests (16 de `rls`).
- [x] Suite `Architecture` en verde; ninguna referencia a `App\Support\*`,
  `App\Concerns\*`, `App\Models\Institution` ni `App\Enums\InstitutionStatus`.
- [x] `migrate:fresh --seed` sobre una base limpia funciona.
- [x] Pint sin cambios pendientes.
- [x] `/security-review` (toca tenancy, S-12): sin hallazgos de confianza alta o media (2026-10-11).
- [x] `estado: implementada` en `design.md` y `tasks.md`.
