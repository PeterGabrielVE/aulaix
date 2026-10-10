---
spec: 003-catalogos
estado: implementada
versión: 2
fecha: 2026-10-10
diseño: design.md
depende-de: 000-fundaciones-hexagonales (implementada)
---

# Tareas — Catálogos globales (refactor hexagonal)

Regla transversal: al cerrar cada tarea, `php artisan test --compact` sigue
en verde (incluida la suite `Architecture`).

## Fase 1 — Dominio

- [x] **T-01** — Mover `EducationLevel` a `Catalogs\Domain` (con `git mv`) y
  actualizar sus `use` (seeder, modelo, test).
  - Test: `GlobalCatalogsTest.php` (solo cambian los `use`)
- [x] **T-02** — `CatalogCode` + `InvalidCatalog`.
  - Test: `tests/Unit/Catalogs/Domain/CatalogCodeTest.php`
- [x] **T-03** — `SubjectEntry` + `SubjectCatalog`.
  - Criterios: CAT-R02.1
  - Test: `tests/Unit/Catalogs/Domain/Subject/SubjectCatalogTest.php`
- [x] **T-04** — `StateEntry`, `MunicipalityEntry`, `ParishEntry`,
  `GeographicCatalog`, `LegacyPlaceholder`.
  - Criterios: CAT-R01.1, CAT-R03.2
  - Test: `tests/Unit/Catalogs/Domain/Geography/GeographicCatalogTest.php`, `LegacyPlaceholderTest.php`

## Fase 2 — Aplicación

- [x] **T-05** — Puertos `*Source` y `*Store`; `SyncSubjectsHandler` y
  `SyncGeographyHandler` sobre `TransactionManager`.
  - Criterios: CAT-R03.1 – CAT-R03.3
  - Test: `tests/Unit/Catalogs/Application/*HandlerTest.php` con dobles en
    memoria (una fuente inválida no escribe; la geografía solo borra el
    catálogo provisional)

## Fase 3 — Infraestructura

- [x] **T-06** — Mover los cuatro modelos a `Catalogs\Infrastructure\Persistence`
  (con `git mv`); actualizar `App\Models\Institution::parish()` y los `use`
  de los tests.
  - Test: `InstitutionTest.php`, `GlobalCatalogsTest.php`
- [x] **T-07** — `JsonGeographySource`, `MppeSubjectSource`,
  `EloquentGeographyStore`, `EloquentSubjectStore`, `CatalogsServiceProvider`.
  - Criterios: CAT-R01.*, CAT-R02.1
  - Test: `tests/Feature/Catalogs/Infrastructure/Source/JsonGeographySourceTest.php`
- [x] **T-08** — `GeographicCatalogSeeder` y `SubjectSeeder` delegan en los handlers.
  - Criterios: CAT-R03.1 – CAT-R03.3, CAT-R04.*, CAT-NF01
  - Test: `GlobalCatalogsTest.php` completo, sin cambios de lógica

## Verificación final

- [x] `php artisan test --compact` en verde (incluido `--group=rls`).
- [x] Suite `Architecture` en verde; ninguna referencia a
  `App\Models\{State,Municipality,Parish,Subject}` ni a `App\Enums\EducationLevel`.
- [x] `migrate:fresh --seed` sobre una base limpia produce 24 / 335 / 1.140 y 20 materias; volver a ejecutar `CatalogSeeder` no cambia nada.
- [x] Pint sin cambios pendientes.
- [x] `estado: implementada` en `design.md` y `tasks.md`.
