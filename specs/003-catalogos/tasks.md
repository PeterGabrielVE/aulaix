---
spec: 003-catalogos
estado: borrador
fecha: 2026-10-10
diseño: design.md
depende-de: 000-fundaciones-hexagonales (implementada)
---

# Tareas — Catálogos globales (refactor hexagonal)

Regla transversal: al cerrar cada tarea, `php artisan test --compact` sigue
en verde.

## Fase 1 — Dominio

- [ ] **T-01** — Mover `EducationLevel`; crear `CatalogCode`, `SubjectEntry`, `GeographicEntry`.
  - Test: `tests/Unit/Catalogs/*EntryTest.php`
- [ ] **T-02** — `CatalogDiff`.
  - Criterios: CAT-R03.1, CAT-R03.3
  - Test: `tests/Unit/Catalogs/CatalogDiffTest.php`

## Fase 2 — Aplicación

- [ ] **T-03** — Puertos `*Source`, `*Store` y handlers `SyncSubjects` / `SyncGeography`.
  - Criterios: CAT-R01.*, CAT-R02.1, CAT-R03.*
  - Test: handlers con adaptadores en memoria

## Fase 3 — Infraestructura

- [ ] **T-04** — Mover los cuatro modelos a `Catalogs\Infrastructure\Persistence`;
  actualizar `InstitutionModel::parish()` y los `use` de los tests.
  - Test: `InstitutionTest.php`, `GlobalCatalogsTest.php`
- [ ] **T-05** — `JsonGeographySource`, `MppeSubjectSource`, `Eloquent*Store`.
  - Test: `JsonGeographySourceTest.php` (incluye JSON inválido)
- [ ] **T-06** — `GeographicCatalogSeeder` y `SubjectSeeder` delegan en los handlers.
  - Criterios: CAT-R03.1 – CAT-R03.3, CAT-NF01
  - Test: `GlobalCatalogsTest.php` completo
- [ ] **T-07** — Query services `Eloquent*Catalog` + `CatalogsServiceProvider`.
  - Criterios: CAT-R01.3, CAT-R04.1
- [ ] **T-08** — Excepción explícita en el test de arquitectura para
  `InstitutionModel → ParishModel`.

## Verificación final

- [ ] `php artisan test --compact` en verde (incluido `--group=rls`).
- [ ] Suite `Architecture` en verde; ninguna referencia a `App\Models\{State,Municipality,Parish,Subject}`.
- [ ] `vendor/bin/pint --dirty` sin cambios.
- [ ] `estado: implementada` en `design.md` y `tasks.md`.
